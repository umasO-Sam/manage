<?php

namespace App\Http\Controllers;

use App\Mail\LeaveRequestNotificationMail;
use App\Models\LeaveRequest;
use App\Models\Staff;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class LeaveRequestController extends Controller
{
    use AuthorizesRequests;

    public function create(): View
    {
        return view('leave-requests.create', [
            'approvers' => Staff::where('id', '!=', Auth::id())->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', LeaveRequest::class);

        $data = $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', array_keys(LeaveRequest::TYPES))],
            'approver_id' => [
                'required', 'integer', 'exists:staff,id',
                function ($attribute, $value, $fail) {
                    if ((int) $value === Auth::id()) {
                        $fail('承認者には自分以外を選択してください。');
                    }
                },
            ],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'granularity' => ['nullable', 'in:full_day,half_day,hours'],
            'reason_code' => ['nullable', 'string', 'max:50'],
            'reason_detail' => ['nullable', 'string', 'max:255'],
            'order_no' => ['nullable', 'string', 'max:255'],
            'work_location' => ['nullable', 'string', 'max:255'],
            'substitute_holiday_date' => ['nullable', 'date'],
            'no_substitute_needed' => ['nullable', 'boolean'],
            'actual_worked_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'compensatory_date' => ['nullable', 'date'],
        ]);

        $fields = $this->buildTypeFields($data);

        $leaveRequest = LeaveRequest::create([
            'staff_id' => Auth::id(),
            'type' => $data['type'],
            'approver_id' => $data['approver_id'],
            'remarks' => $data['remarks'] ?? null,
            'status' => LeaveRequest::STATUS_PENDING,
            ...$fields,
        ]);

        $this->sendNotification(
            $leaveRequest->approver->email,
            new LeaveRequestNotificationMail($leaveRequest, '休暇・勤務申請が届きました')
        );

        return redirect()->route('leave-requests.index')->with('status', 'leave-request-created');
    }

    /**
     * typeごとに必須項目・日数の自動算出ルールが異なるため、ここで一括して検証・整形する。
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function buildTypeFields(array $data): array
    {
        return match ($data['type']) {
            'paid_leave' => $this->buildPaidLeaveFields($data),
            'ceremonial_leave' => $this->buildDateRangeFields($data, 'ceremonial_leave', required: ['reason_code']),
            'special_leave_paid' => $this->buildDateRangeFields($data, 'special_leave_paid', required: ['reason_code']),
            'special_leave_unpaid' => $this->buildDateRangeFields($data, 'special_leave_unpaid', required: ['reason_code']),
            'volunteer_leave' => $this->buildDateRangeFields($data, 'volunteer_leave', required: ['reason_code']),
            'juror_leave', 'telework', 'banked_paid_leave' => $this->buildDateRangeFields($data, $data['type'], required: []),
            'holiday_work' => $this->buildHolidayWorkFields($data),
            'compensatory_leave' => $this->buildCompensatoryLeaveFields($data),
            default => throw ValidationException::withMessages(['type' => '未対応の申請種別です。']),
        };
    }

    private function buildPaidLeaveFields(array $data): array
    {
        if (empty($data['granularity'])) {
            throw ValidationException::withMessages(['granularity' => '有給休暇の粒度（1日/半日/2時間）を選択してください。']);
        }

        $dayCount = match ($data['granularity']) {
            'full_day' => 1.0,
            'half_day' => 0.5,
            'hours' => 0.25, // 所定労働時間8時間・2時間単位付与のため 2/8
            default => null,
        };

        return [
            'start_date' => $data['start_date'],
            'end_date' => $data['start_date'],
            'granularity' => $data['granularity'],
            'hours' => $data['granularity'] === 'hours' ? 2.0 : null,
            'day_count' => $dayCount,
        ];
    }

    /**
     * 事由に固定日数があればそこから終了日を自動算出し、無ければ入力された
     * 日付範囲から日数を自動計算する(結婚5日など会社側で日数が決まっている
     * 事由と、忌引きの続柄別日数のように都度変わる事由の両方に対応するため)。
     *
     * @param  array<int, string>  $required
     */
    private function buildDateRangeFields(array $data, string $type, array $required): array
    {
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw ValidationException::withMessages([$field => '必須項目が入力されていません。']);
            }
        }

        $reasonCode = $data['reason_code'] ?? null;

        if (in_array('reason_code', $required, true) && ! isset(LeaveRequest::REASONS[$type][$reasonCode])) {
            throw ValidationException::withMessages(['reason_code' => '無効な事由です。']);
        }

        $fixedDays = LeaveRequest::REASONS[$type][$reasonCode]['day_count'] ?? null;

        if ($fixedDays !== null) {
            $endDate = Carbon::parse($data['start_date'])->addDays((int) $fixedDays - 1)->format('Y-m-d');

            return [
                'start_date' => $data['start_date'],
                'end_date' => $endDate,
                'reason_code' => $reasonCode,
                'reason_detail' => $data['reason_detail'] ?? null,
                'day_count' => $fixedDays,
            ];
        }

        $endDate = $data['end_date'] ?? $data['start_date'];
        $dayCount = Carbon::parse($data['start_date'])->diffInDays(Carbon::parse($endDate)) + 1;

        return [
            'start_date' => $data['start_date'],
            'end_date' => $endDate,
            'reason_code' => $reasonCode,
            'reason_detail' => $data['reason_detail'] ?? null,
            'day_count' => (float) $dayCount,
        ];
    }

    private function buildHolidayWorkFields(array $data): array
    {
        foreach (['order_no', 'work_location'] as $field) {
            if (empty($data[$field])) {
                throw ValidationException::withMessages([$field => '注番・勤務地は必須です。']);
            }
        }

        $noSubstituteNeeded = (bool) ($data['no_substitute_needed'] ?? false);

        if (! $noSubstituteNeeded && empty($data['substitute_holiday_date'])) {
            throw ValidationException::withMessages(['substitute_holiday_date' => '振替休日とする日を指定するか、「振り替えない」にチェックしてください。']);
        }

        return [
            'start_date' => $data['start_date'],
            'end_date' => $data['start_date'],
            'order_no' => $data['order_no'],
            'work_location' => $data['work_location'],
            'substitute_holiday_date' => $noSubstituteNeeded ? null : $data['substitute_holiday_date'],
            'no_substitute_needed' => $noSubstituteNeeded,
        ];
    }

    private function buildCompensatoryLeaveFields(array $data): array
    {
        foreach (['order_no', 'work_location', 'actual_worked_hours'] as $field) {
            if (! isset($data[$field]) || $data[$field] === '') {
                throw ValidationException::withMessages([$field => '注番・勤務地・実際に勤務した時間は必須です。']);
            }
        }

        $hours = (float) $data['actual_worked_hours'];
        $eligible = $hours >= 6.0;

        if (! $eligible && ! empty($data['compensatory_date'])) {
            throw ValidationException::withMessages(['compensatory_date' => '代休は実際に6時間以上勤務した場合のみ指定できます。']);
        }

        if ($eligible && empty($data['compensatory_date'])) {
            throw ValidationException::withMessages(['compensatory_date' => '代休日を指定してください。']);
        }

        return [
            'start_date' => $data['start_date'],
            'end_date' => $data['start_date'],
            'order_no' => $data['order_no'],
            'work_location' => $data['work_location'],
            'actual_worked_hours' => $hours,
            'compensatory_date' => $eligible ? $data['compensatory_date'] : null,
        ];
    }

    public function index(): View
    {
        return view('leave-requests.index', [
            'leaveRequests' => LeaveRequest::with('approver')
                ->where('staff_id', Auth::id())
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function withdraw(LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorize('withdraw', $leaveRequest);

        $leaveRequest->update(['status' => LeaveRequest::STATUS_WITHDRAWN]);

        return redirect()->route('leave-requests.index')->with('status', 'leave-request-withdrawn');
    }

    public function approvals(): View
    {
        return view('leave-requests.approvals', [
            'leaveRequests' => LeaveRequest::with('staff')
                ->where('approver_id', Auth::id())
                ->where('status', LeaveRequest::STATUS_PENDING)
                ->orderBy('start_date')
                ->get(),
        ]);
    }

    public function show(LeaveRequest $leaveRequest): View
    {
        $this->authorize('view', $leaveRequest);

        return view('leave-requests.show', ['leaveRequest' => $leaveRequest]);
    }

    public function decide(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorize('decide', $leaveRequest);

        $data = $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'rejection_reason' => ['nullable', 'string', 'max:2000', 'required_if:action,reject'],
        ]);

        $leaveRequest->update([
            'status' => $data['action'] === 'approve' ? LeaveRequest::STATUS_APPROVED : LeaveRequest::STATUS_REJECTED,
            'rejection_reason' => $data['action'] === 'reject' ? $data['rejection_reason'] : null,
            'approved_at' => now(),
        ]);

        $this->sendNotification(
            $leaveRequest->staff->email,
            new LeaveRequestNotificationMail(
                $leaveRequest,
                $data['action'] === 'approve' ? '申請が承認されました' : '申請が却下されました'
            )
        );

        return redirect()->route('leave-requests.approvals')->with('status', 'leave-request-decided');
    }

    /**
     * メール送信に失敗しても、既に成功しているDB更新まで失敗扱い（500エラー）に
     * しない。CardController::sendNotification()と同じ方針。
     */
    private function sendNotification(string $toEmail, LeaveRequestNotificationMail $mail): void
    {
        try {
            Mail::to($toEmail)->send($mail);
        } catch (Throwable $e) {
            Log::error("通知メールの送信に失敗しました（宛先: {$toEmail}）: {$e->getMessage()}");
        }
    }
}
