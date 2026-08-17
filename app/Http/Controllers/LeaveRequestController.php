<?php

namespace App\Http\Controllers;

use App\Mail\LeaveRequestNotificationMail;
use App\Models\LeaveRequest;
use App\Models\OperationLog;
use App\Models\OrderNumber;
use App\Models\Staff;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class LeaveRequestController extends Controller
{
    use AuthorizesRequests;

    public function create(Request $request): View
    {
        return $this->createView($request, null);
    }

    /**
     * 勤怠管理者による代理申請。本人がPCを使えない・入院しているなどで自分では
     * 出せない場合に、勤怠管理者が代わりに申請する(作業日報の代理提出と同じ考え方)。
     * ルートに attendance.manager を掛けているのでここでの権限チェックは不要。
     */
    public function createProxy(Request $request): View
    {
        $targetId = (int) $request->query('target_staff_id');
        $target = $targetId !== 0 ? Staff::forRoster()->find($targetId) : null;

        return $this->createView($request, $target);
    }

    /**
     * 申請フォーム。対象者(target)を渡すと代理申請の画面になり、有給残日数も
     * その担当者のものを出す(自分の残日数を見ながら他人の有給を申請すると事故になる)。
     * 対象者が未選択の代理申請では、選ぶまで残日数の欄を出さない。
     */
    private function createView(Request $request, ?Staff $target): View
    {
        $isProxy = $request->routeIs('leave-requests.proxy.create');

        return view('leave-requests.create', [
            // 上長は自分自身も承認者に選べる(上長本人の申請を承認できる者が
            // 他にいない運用があるため)。上長でなければ自分は候補に出ない。
            'approvers' => Staff::where('is_supervisor', true)->orderBy('name')->get(),
            'orderNumbers' => OrderNumber::forDropdown()->get()
                ->map(fn (OrderNumber $o) => ['code' => $o->code, 'label' => $o->displayLabel()])
                ->values(),
            'hiddenOrderNumbers' => OrderNumber::hiddenFromDropdown()->get()
                ->map(fn (OrderNumber $o) => ['value' => $o->code, 'label' => $o->displayLabel()])
                ->values(),
            'paidLeaveBalance' => ($target ?? Auth::user())->paidLeaveBalance(),
            'prefillDate' => $this->parseDateQuery($request->query('date')),
            'isProxy' => $isProxy,
            'targetStaff' => $target,
            // 自分の分は「代理」ではないので候補から外す。
            'proxyTargets' => $isProxy
                ? Staff::forRoster()->where('id', '!=', Auth::id())->get()
                : collect(),
        ]);
    }

    /**
     * カレンダー画面からの日付クリック遷移(?date=)用。不正な値はプリフィルなしとして無視する。
     */
    private function parseDateQuery(?string $date): ?string
    {
        if (! $date) {
            return null;
        }

        try {
            return Carbon::parse($date)->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', LeaveRequest::class);

        $data = $request->validate([
            // 代理申請の対象者。勤怠管理者だけが指定でき、無ければ自分の申請になる。
            'target_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'type' => ['required', 'string', 'in:'.implode(',', array_keys(LeaveRequest::TYPES))],
            'approver_id' => [
                'required', 'integer', 'exists:staff,id',
                function ($attribute, $value, $fail) {
                    if (! Staff::where('id', $value)->where('is_supervisor', true)->exists()) {
                        $fail('承認者には上長フラグが設定された担当者を選択してください。');
                    }
                },
            ],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'granularity' => ['nullable', 'in:full_day,half_day,hours'],
            'half_day_period' => ['nullable', 'in:am,pm'],
            'reason_code' => ['nullable', 'string', 'max:50'],
            'reason_detail' => ['nullable', 'string', 'max:255'],
            'order_no' => ['nullable', 'string', 'max:255'],
            'work_location' => ['nullable', 'string', 'max:255'],
            'substitute_holiday_date' => ['nullable', 'date'],
            'no_substitute_needed' => ['nullable', 'boolean'],
            'actual_worked_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'compensatory_date' => ['nullable', 'date'],
            'funeral_venue_address' => ['nullable', 'string', 'max:255'],
            'funeral_venue_phone' => ['nullable', 'string', 'max:50'],
            'wake_datetime' => ['nullable', 'date'],
            'funeral_datetime' => ['nullable', 'date'],
            'flowers_declined' => ['nullable', 'boolean'],
            'telegram_declined' => ['nullable', 'boolean'],
        ]);

        // 代理申請。勤怠管理者以外が target_staff_id を送ってきても自分の申請として扱う
        // (画面には出ないが、値を足せば他人名義で出せてしまうため)。
        $target = null;
        if (! empty($data['target_staff_id']) && Auth::user()->canManageAttendance()) {
            $candidate = Staff::find($data['target_staff_id']);
            $target = $candidate !== null && $candidate->id !== Auth::id() ? $candidate : null;
        }

        // 有給の残日数などは「申請者」で判定する。代理申請でログイン者を見てしまうと、
        // 対象者に残数があるのに勤怠管理者の残数で弾かれる(逆に、勤怠管理者に残数が
        // あれば対象者の残数を超えて申請できてしまう)。
        $fields = $this->buildTypeFields($data, $target ?? Auth::user());

        $leaveRequest = LeaveRequest::create([
            'staff_id' => $target?->id ?? Auth::id(),
            'proxy_staff_id' => $target !== null ? Auth::id() : null,
            'type' => $data['type'],
            'approver_id' => $data['approver_id'],
            'remarks' => $data['remarks'] ?? null,
            'status' => LeaveRequest::STATUS_PENDING,
            ...$fields,
        ]);

        OperationLog::record(
            $target !== null ? OperationLog::ACTION_LEAVE_REQUEST_PROXY_CREATE : OperationLog::ACTION_LEAVE_REQUEST_CREATE,
            $leaveRequest,
            $leaveRequest->staff_id,
            $target !== null ? Auth::user()->name.'が'.$target->name.'の分を代理申請' : null
        );

        $this->sendNotification(
            $leaveRequest->approver->email,
            new LeaveRequestNotificationMail($leaveRequest, '休暇・勤務申請が届きました')
        );

        // 代理申請は本人の知らないところで申請が立つため、本人にも知らせる。
        if ($target !== null) {
            $this->sendNotification(
                $target->email,
                new LeaveRequestNotificationMail($leaveRequest, '代理で休暇・勤務申請が行われました')
            );
        }

        return redirect()->route('leave-requests.index')
            ->with('status', $target !== null ? 'leave-request-proxy-created' : 'leave-request-created')
            ->with('proxy_target_name', $target?->name);
    }

    /**
     * typeごとに必須項目・日数の自動算出ルールが異なるため、ここで一括して検証・整形する。
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function buildTypeFields(array $data, Staff $applicant): array
    {
        return match ($data['type']) {
            'paid_leave' => $this->buildPaidLeaveFields($data, $applicant),
            'ceremonial_leave' => $this->buildCeremonialLeaveFields($data),
            'special_leave_paid' => $this->buildDateRangeFields($data, 'special_leave_paid', required: ['reason_code']),
            'special_leave_unpaid' => $this->buildDateRangeFields($data, 'special_leave_unpaid', required: ['reason_code']),
            'volunteer_leave' => $this->buildDateRangeFields($data, 'volunteer_leave', required: ['reason_code']),
            'juror_leave', 'telework', 'banked_paid_leave' => $this->buildDateRangeFields($data, $data['type'], required: []),
            'holiday_work' => $this->buildHolidayWorkFields($data),
            'compensatory_leave' => $this->buildCompensatoryLeaveFields($data),
            default => throw ValidationException::withMessages(['type' => '未対応の申請種別です。']),
        };
    }

    /**
     * 忌引きの場合のみ、通夜・葬儀の日程や会場情報(花・電報の手配用、任意入力)を追加で保持する。
     */
    private function buildCeremonialLeaveFields(array $data): array
    {
        $fields = $this->buildDateRangeFields($data, 'ceremonial_leave', required: ['reason_code']);

        if (($data['reason_code'] ?? null) === 'funeral') {
            $fields = [
                ...$fields,
                'funeral_venue_address' => $data['funeral_venue_address'] ?? null,
                'funeral_venue_phone' => $data['funeral_venue_phone'] ?? null,
                'wake_datetime' => $data['wake_datetime'] ?? null,
                'funeral_datetime' => $data['funeral_datetime'] ?? null,
                'flowers_declined' => (bool) ($data['flowers_declined'] ?? false),
                'telegram_declined' => (bool) ($data['telegram_declined'] ?? false),
            ];
        }

        return $fields;
    }

    private function buildPaidLeaveFields(array $data, Staff $applicant): array
    {
        if (empty($data['granularity'])) {
            throw ValidationException::withMessages(['granularity' => '有給休暇の粒度（1日/半日/2時間）を選択してください。']);
        }

        // 半日・2時間はどちらも午前/午後の単位で取得する（2時間は始業側/終業側の2時間）。
        if (in_array($data['granularity'], ['half_day', 'hours'], true) && empty($data['half_day_period'])) {
            throw ValidationException::withMessages([
                'half_day_period' => $data['granularity'] === 'half_day'
                    ? '半休の午前/午後を選択してください。'
                    : '2時間有休の午前/午後を選択してください。',
            ]);
        }

        $dayCount = match ($data['granularity']) {
            'full_day' => 1.0,
            'half_day' => 0.5,
            'hours' => 0.25, // 所定労働時間8時間・2時間単位付与のため 2/8
            default => null,
        };

        $remaining = $applicant->paidLeaveBalance()['remainingTotal'];
        if ($dayCount > $remaining) {
            throw ValidationException::withMessages([
                'granularity' => "有給休暇の残日数が不足しています（残り{$remaining}日）。",
            ]);
        }

        return [
            'start_date' => $data['start_date'],
            'end_date' => $data['start_date'],
            'granularity' => $data['granularity'],
            'half_day_period' => in_array($data['granularity'], ['half_day', 'hours'], true) ? $data['half_day_period'] : null,
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
        $canProxy = Auth::user()->canManageAttendance();

        return view('leave-requests.index', [
            'leaveRequests' => LeaveRequest::with('approver')
                ->where('staff_id', Auth::id())
                ->orderByDesc('id')
                ->get(),
            'canProxy' => $canProxy,
            // 自分が代理で出した分。本人の一覧にしか出ないと、出した側が結果を追えない。
            'proxyRequests' => $canProxy
                ? LeaveRequest::with(['approver', 'staff'])
                    ->where('proxy_staff_id', Auth::id())
                    ->orderByDesc('id')
                    ->get()
                : collect(),
        ]);
    }

    public function withdraw(LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorize('withdraw', $leaveRequest);

        $leaveRequest->update(['status' => LeaveRequest::STATUS_WITHDRAWN]);

        OperationLog::record(OperationLog::ACTION_LEAVE_REQUEST_WITHDRAW, $leaveRequest, $leaveRequest->staff_id);

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
            // 承認済みのあとに出された取消申請も同じ画面で捌く。
            'cancelRequests' => LeaveRequest::with('staff')
                ->where('approver_id', Auth::id())
                ->where('status', LeaveRequest::STATUS_APPROVED)
                ->where('cancel_status', LeaveRequest::CANCEL_REQUESTED)
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

        $approved = $data['action'] === 'approve';

        // 状態更新と操作ログは必ず対で残す(片方だけ成立して履歴が欠けるのを防ぐ)。
        DB::transaction(function () use ($leaveRequest, $data, $approved) {
            $leaveRequest->update([
                'status' => $approved ? $this->statusAfterSupervisorApproval($leaveRequest) : LeaveRequest::STATUS_REJECTED,
                'rejection_reason' => $approved ? null : $data['rejection_reason'],
                'approved_at' => now(),
                'supervisor_approved_at' => $approved ? now() : null,
            ]);

            OperationLog::record(
                $approved ? OperationLog::ACTION_LEAVE_REQUEST_APPROVE : OperationLog::ACTION_LEAVE_REQUEST_REJECT,
                $leaveRequest,
                $leaveRequest->staff_id,
                $approved ? null : $data['rejection_reason']
            );
        });

        $this->notifyAfterSupervisorDecision($leaveRequest, $approved);

        return redirect()->route('leave-requests.approvals')->with('status', 'leave-request-decided');
    }

    /**
     * 上長が承認したあとの状態。休日勤務は勤怠管理者の確認を経て初めて承認済みになる。
     * ここで承認済みにしてしまうと、勤務状況一覧や振替休日が未確定のまま確定扱いになる。
     */
    private function statusAfterSupervisorApproval(LeaveRequest $leaveRequest): string
    {
        return $leaveRequest->needsAttendanceApproval()
            ? LeaveRequest::STATUS_PENDING_ATTENDANCE
            : LeaveRequest::STATUS_APPROVED;
    }

    /**
     * 上長の判断後の通知。勤怠管理者の確認が要る申請は、本人ではなく勤怠管理者へ回す
     * (本人にはまだ結果が出ていないため「承認されました」とは言えない)。
     */
    private function notifyAfterSupervisorDecision(LeaveRequest $leaveRequest, bool $approved): void
    {
        if ($approved && $leaveRequest->isPendingAttendance()) {
            foreach ($this->attendanceManagers() as $manager) {
                $this->sendNotification(
                    $manager->email,
                    new LeaveRequestNotificationMail($leaveRequest, '上長承認済みの休日勤務申請の確認をお願いします')
                );
            }

            $this->sendNotification(
                $leaveRequest->staff->email,
                new LeaveRequestNotificationMail($leaveRequest, '上長が承認しました（勤怠管理者の確認待ちです）')
            );

            return;
        }

        $this->sendNotification(
            $leaveRequest->staff->email,
            new LeaveRequestNotificationMail(
                $leaveRequest,
                $approved ? '申請が承認されました' : '申請が却下されました'
            )
        );

        // 承認済みになった申請は勤怠管理者にも知らせる。勤務状況や有給残に効いてくるため、
        // 決まった時点で把握できるようにする。却下は誰の予定も動かないので送らない。
        if ($approved) {
            $this->notifyAttendanceManagers($leaveRequest, '申請が承認されました（勤怠管理者へのお知らせ）');
        }
    }

    /**
     * 勤怠管理者への「お知らせ」。自分が操作した分は送らない(自分の操作の控えが届いても
     * 行動を求めないため)。確認・反映を依頼する通知は行動を求めるので、そちらは
     * 操作者を除かず全員に送っている。
     */
    private function notifyAttendanceManagers(LeaveRequest $leaveRequest, string $headline): void
    {
        foreach ($this->attendanceManagers() as $manager) {
            if ($manager->id === Auth::id()) {
                continue;
            }

            $this->sendNotification($manager->email, new LeaveRequestNotificationMail($leaveRequest, $headline));
        }
    }

    /**
     * 反映確認・休日勤務の確認を依頼する相手。担当が1人に固定されていないため全員に送る。
     *
     * @return \Illuminate\Support\Collection<int, Staff>
     */
    private function attendanceManagers()
    {
        return Staff::where('is_attendance_manager', true)->orWhere('is_administrator', true)->get();
    }

    /**
     * 勤怠管理者による休日勤務申請の確認。ここで承認して初めて承認済みになる。
     * 差し戻す場合は却下として扱い、本人と上長の双方に理由を伝える
     * (上長が一度通した判断を覆すため、上長にも届かないと経緯が追えない)。
     */
    public function attendanceDecide(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorize('attendanceDecide', $leaveRequest);

        $data = $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'rejection_reason' => ['nullable', 'string', 'max:2000', 'required_if:action,reject'],
        ], ['rejection_reason.required_if' => '差し戻しの理由を入力してください。']);

        $approved = $data['action'] === 'approve';

        DB::transaction(function () use ($leaveRequest, $data, $approved) {
            $leaveRequest->update([
                'status' => $approved ? LeaveRequest::STATUS_APPROVED : LeaveRequest::STATUS_REJECTED,
                'rejection_reason' => $approved ? null : $data['rejection_reason'],
                'approved_at' => now(),
            ]);

            OperationLog::record(
                $approved ? OperationLog::ACTION_LEAVE_REQUEST_ATTENDANCE_APPROVE : OperationLog::ACTION_LEAVE_REQUEST_ATTENDANCE_REJECT,
                $leaveRequest,
                $leaveRequest->staff_id,
                $approved ? null : $data['rejection_reason']
            );
        });

        if ($approved) {
            $this->sendNotification(
                $leaveRequest->staff->email,
                new LeaveRequestNotificationMail($leaveRequest, '申請が承認されました')
            );
        } else {
            // 差し戻しは本人と、承認した上長の双方に伝える。
            foreach ([$leaveRequest->staff->email, $leaveRequest->approver->email] as $email) {
                $this->sendNotification(
                    $email,
                    new LeaveRequestNotificationMail($leaveRequest, '休日勤務申請が勤怠管理者により差し戻されました')
                );
            }
        }

        return redirect()->route('leave-requests.cancellations')->with('status', 'leave-request-attendance-decided');
    }

    /**
     * 承認待ちの申請をまとめて承認する。却下は理由が要るため一括にはしない。
     *
     * 自分が承認者でないものや、すでに処理済みのものが紛れていても黙って
     * 通さないよう、1件ずつ権限を確かめてから更新する。
     */
    public function bulkApprove(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ], ['ids.required' => '承認する申請を選択してください。']);

        $leaveRequests = LeaveRequest::with(['staff', 'approver'])
            ->whereIn('id', $data['ids'])
            ->where('approver_id', Auth::id())
            ->where('status', LeaveRequest::STATUS_PENDING)
            ->get();

        if ($leaveRequests->isEmpty()) {
            return redirect()->route('leave-requests.approvals')
                ->withErrors(['ids' => '承認できる申請がありませんでした。ほかの端末で処理済みの可能性があります。']);
        }

        DB::transaction(function () use ($leaveRequests) {
            foreach ($leaveRequests as $leaveRequest) {
                $this->authorize('decide', $leaveRequest);

                $leaveRequest->update([
                    // 休日勤務は一括承認でも勤怠管理者の確認へ回す(単票の承認と同じ扱い)。
                    'status' => $this->statusAfterSupervisorApproval($leaveRequest),
                    'rejection_reason' => null,
                    'approved_at' => now(),
                    'supervisor_approved_at' => now(),
                ]);

                OperationLog::record(
                    OperationLog::ACTION_LEAVE_REQUEST_APPROVE,
                    $leaveRequest,
                    $leaveRequest->staff_id
                );
            }
        });

        foreach ($leaveRequests as $leaveRequest) {
            $this->notifyAfterSupervisorDecision($leaveRequest, true);
        }

        return redirect()->route('leave-requests.approvals')
            ->with('status', 'leave-requests-bulk-approved')
            ->with('bulkApprovedCount', $leaveRequests->count());
    }

    /**
     * 承認済み申請の取消を本人が申請する。理由は必須(上長と勤怠管理者が
     * 可否を判断する材料になるため)。
     */
    public function requestCancel(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorize('requestCancel', $leaveRequest);

        $data = $request->validate(
            ['cancel_reason' => ['required', 'string', 'max:2000']],
            ['cancel_reason.required' => '取消の理由を入力してください。']
        );

        DB::transaction(function () use ($leaveRequest, $data) {
            $leaveRequest->update([
                'cancel_status' => LeaveRequest::CANCEL_REQUESTED,
                'cancel_reason' => $data['cancel_reason'],
                'cancel_rejection_reason' => null,
                'cancel_requested_at' => now(),
            ]);

            OperationLog::record(
                OperationLog::ACTION_LEAVE_REQUEST_CANCEL_REQUEST,
                $leaveRequest,
                $leaveRequest->staff_id,
                $data['cancel_reason']
            );
        });

        $this->sendNotification(
            $leaveRequest->approver->email,
            new LeaveRequestNotificationMail($leaveRequest, '承認済み申請の取消申請が届きました')
        );

        return redirect()->route('leave-requests.index')->with('status', 'leave-request-cancel-requested');
    }

    /**
     * 上長が取消を認めるか差し戻すかを判断する。認めた場合は確定させず、
     * 勤怠管理者の反映確認へ回す。
     */
    public function decideCancel(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorize('decideCancel', $leaveRequest);

        $data = $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'cancel_rejection_reason' => ['nullable', 'string', 'max:2000', 'required_if:action,reject'],
        ], ['cancel_rejection_reason.required_if' => '差し戻しの理由を入力してください。']);

        $approved = $data['action'] === 'approve';

        DB::transaction(function () use ($leaveRequest, $data, $approved) {
            $leaveRequest->update($approved
                ? ['cancel_status' => LeaveRequest::CANCEL_PENDING_REFLECTION]
                : [
                    'cancel_status' => null,
                    'cancel_rejection_reason' => $data['cancel_rejection_reason'],
                ]);

            OperationLog::record(
                $approved ? OperationLog::ACTION_LEAVE_REQUEST_CANCEL_APPROVE : OperationLog::ACTION_LEAVE_REQUEST_CANCEL_REJECT,
                $leaveRequest,
                $leaveRequest->staff_id,
                $approved ? null : $data['cancel_rejection_reason']
            );
        });

        if ($approved) {
            // 反映確認は勤怠管理者全員に依頼する(担当が1人に固定されていないため)。
            foreach ($this->attendanceManagers() as $manager) {
                $this->sendNotification(
                    $manager->email,
                    new LeaveRequestNotificationMail($leaveRequest, '取消の反映確認をお願いします')
                );
            }
        } else {
            $this->sendNotification(
                $leaveRequest->staff->email,
                new LeaveRequestNotificationMail($leaveRequest, '取消申請が差し戻されました')
            );
        }

        return redirect()->route('leave-requests.approvals')->with('status', 'leave-request-cancel-decided');
    }

    /**
     * 勤怠管理者の反映確認。法律やルールに照らして取り消してよければ反映し、
     * 別の申請を出し直してもらうべきなら差し戻す。
     */
    public function reflectCancel(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorize('reflectCancel', $leaveRequest);

        $data = $request->validate([
            'action' => ['required', 'in:reflect,send_back'],
            'cancel_rejection_reason' => ['nullable', 'string', 'max:2000', 'required_if:action,send_back'],
        ], ['cancel_rejection_reason.required_if' => '差し戻しの理由を入力してください。']);

        $reflected = $data['action'] === 'reflect';

        DB::transaction(function () use ($leaveRequest, $data, $reflected) {
            // 反映して初めてstatusを変える。ここまでは承認済みのままなので、
            // 勤務状況一覧や有給残日数からは消えない。
            $leaveRequest->update($reflected
                ? [
                    'status' => LeaveRequest::STATUS_CANCELLED,
                    'cancel_status' => null,
                    'cancelled_at' => now(),
                ]
                : [
                    'cancel_status' => null,
                    'cancel_rejection_reason' => $data['cancel_rejection_reason'],
                ]);

            OperationLog::record(
                $reflected ? OperationLog::ACTION_LEAVE_REQUEST_CANCEL_REFLECT : OperationLog::ACTION_LEAVE_REQUEST_CANCEL_SEND_BACK,
                $leaveRequest,
                $leaveRequest->staff_id,
                $reflected ? null : $data['cancel_rejection_reason']
            );
        });

        // 本人と、取消を認めた上長の双方に結果を伝える。
        foreach ([$leaveRequest->staff->email, $leaveRequest->approver->email] as $email) {
            $this->sendNotification(
                $email,
                new LeaveRequestNotificationMail(
                    $leaveRequest,
                    $reflected ? '申請の取消が確定しました' : '取消が差し戻されました'
                )
            );
        }

        return redirect()->route('leave-requests.cancellations')->with('status', 'leave-request-cancel-reflected');
    }

    /**
     * 勤怠管理者の確認画面。取消の反映確認と、上長承認済みの休日勤務申請の承認を
     * 1画面にまとめる(どちらも勤怠管理者だけが判断する仕事のため)。
     */
    public function cancellations(): View
    {
        return view('leave-requests.cancellations', [
            'leaveRequests' => LeaveRequest::with(['staff', 'approver'])
                ->where('status', LeaveRequest::STATUS_APPROVED)
                ->where('cancel_status', LeaveRequest::CANCEL_PENDING_REFLECTION)
                ->orderBy('cancel_requested_at')
                ->get(),
            'attendanceApprovals' => LeaveRequest::with(['staff', 'approver'])
                ->where('status', LeaveRequest::STATUS_PENDING_ATTENDANCE)
                ->orderBy('start_date')
                ->get(),
        ]);
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
