<?php

namespace App\Http\Controllers;

use App\Models\DailyReport;
use App\Models\Holiday;
use App\Models\LaborCost;
use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * 本人カレンダー(月間、土曜始まり)。休日マスタ(祝日・会社休日・有給休暇取得推奨日)と、
 * 自分自身の休暇・勤務申請(承認待ち・承認済み)、作業日報の登録状況を重ねて表示する。
 * 日付をクリックすると、その日を対象日にした新規申請画面や作業日報へ遷移する。
 */
class PersonalCalendarController extends Controller
{
    public function show(Request $request): View
    {
        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $offsetFromSaturday = ($monthStart->dayOfWeek + 1) % 7;
        $gridStart = $monthStart->copy()->subDays($offsetFromSaturday);
        $lastDayOffset = (Carbon::FRIDAY - $monthEnd->dayOfWeek + 7) % 7;
        $gridEnd = $monthEnd->copy()->addDays($lastDayOffset);

        $holidaysByDate = Holiday::whereDate('date', '>=', $gridStart->toDateString())
            ->whereDate('date', '<=', $gridEnd->toDateString())
            ->get()
            ->keyBy(fn (Holiday $h) => $h->date->format('Y-m-d'));

        $leaveRequestsByDate = $this->buildLeaveRequestsByDate($gridStart, $gridEnd);
        $backgroundOverrides = $this->buildBackgroundOverrides($gridStart, $gridEnd);
        $dailyReportStatusByDate = $this->buildDailyReportStatusByDate($gridStart, $gridEnd);

        $weeks = [];
        $cursor = $gridStart->copy();
        while ($cursor->lte($gridEnd)) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $dateKey = $cursor->format('Y-m-d');
                $week[] = [
                    'date' => $cursor->copy(),
                    'inMonth' => $cursor->month === $monthStart->month,
                    'holiday' => $holidaysByDate->get($dateKey),
                    'leaveRequests' => $leaveRequestsByDate->get($dateKey, collect()),
                    'backgroundOverride' => $backgroundOverrides->get($dateKey),
                    'dailyReportStatus' => $dailyReportStatusByDate->get($dateKey),
                ];
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        return view('my-calendar.show', [
            'year' => $year,
            'month' => $month,
            'monthStart' => $monthStart,
            'weeks' => $weeks,
        ]);
    }

    /**
     * 自分自身の承認待ち・承認済み申請を、対象日ごとに展開する。休日勤務申請の振替休日、
     * 代休申請の代休日は、対象期間(start_date〜end_date)とは別の日付なので個別に拾い、
     * それぞれ役割(main/substitute/compensatory)を付けてバッジの文言を出し分ける。
     *
     * @return Collection<string, Collection<int, array{request: LeaveRequest, role: string}>>
     */
    private function buildLeaveRequestsByDate(Carbon $gridStart, Carbon $gridEnd): Collection
    {
        $requests = LeaveRequest::where('staff_id', Auth::id())
            ->whereIn('status', [LeaveRequest::STATUS_PENDING, LeaveRequest::STATUS_APPROVED])
            ->where(function ($query) use ($gridStart, $gridEnd) {
                $query->where(function ($q) use ($gridStart, $gridEnd) {
                    $q->whereDate('start_date', '<=', $gridEnd->toDateString())
                        ->where(function ($q2) use ($gridStart) {
                            $q2->whereNull('end_date')->orWhereDate('end_date', '>=', $gridStart->toDateString());
                        });
                })
                    ->orWhere(function ($q) use ($gridStart, $gridEnd) {
                        $q->whereDate('substitute_holiday_date', '>=', $gridStart->toDateString())
                            ->whereDate('substitute_holiday_date', '<=', $gridEnd->toDateString());
                    })
                    ->orWhere(function ($q) use ($gridStart, $gridEnd) {
                        $q->whereDate('compensatory_date', '>=', $gridStart->toDateString())
                            ->whereDate('compensatory_date', '<=', $gridEnd->toDateString());
                    });
            })
            ->get();

        $byDate = collect();
        $push = function (string $key, LeaveRequest $leaveRequest, string $role) use (&$byDate) {
            $byDate->put($key, $byDate->get($key, collect())->push(['request' => $leaveRequest, 'role' => $role]));
        };

        foreach ($requests as $leaveRequest) {
            $cursor = $leaveRequest->start_date->copy();
            $end = $leaveRequest->end_date ?? $leaveRequest->start_date;
            while ($cursor->lte($end)) {
                $push($cursor->format('Y-m-d'), $leaveRequest, 'main');
                $cursor->addDay();
            }

            if ($leaveRequest->substitute_holiday_date) {
                $push($leaveRequest->substitute_holiday_date->format('Y-m-d'), $leaveRequest, 'substitute');
            }
            if ($leaveRequest->compensatory_date) {
                $push($leaveRequest->compensatory_date->format('Y-m-d'), $leaveRequest, 'compensatory');
            }
        }

        return $byDate;
    }

    /**
     * 承認済みの休日勤務申請のみ、出勤日を白背景・振替休日を薄い赤背景にする(申請結果が
     * 確定していない承認待ち・却下・取消済みの間は通常通りの休日表示のままにする)。
     * 代休申請は同じ「出勤日+休みの日」の組み合わせだが、背景色は変更しない仕様のため対象外。
     *
     * @return Collection<string, string> Y-m-d形式の日付 => 'work_day'|'substitute_holiday'
     */
    private function buildBackgroundOverrides(Carbon $gridStart, Carbon $gridEnd): Collection
    {
        $approvedHolidayWork = LeaveRequest::where('staff_id', Auth::id())
            ->where('type', 'holiday_work')
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->where(function ($query) use ($gridStart, $gridEnd) {
                $query->where(function ($q) use ($gridStart, $gridEnd) {
                    $q->whereDate('start_date', '>=', $gridStart->toDateString())
                        ->whereDate('start_date', '<=', $gridEnd->toDateString());
                })
                    ->orWhere(function ($q) use ($gridStart, $gridEnd) {
                        $q->whereDate('substitute_holiday_date', '>=', $gridStart->toDateString())
                            ->whereDate('substitute_holiday_date', '<=', $gridEnd->toDateString());
                    });
            })
            ->get();

        $overrides = collect();
        foreach ($approvedHolidayWork as $leaveRequest) {
            $overrides->put($leaveRequest->start_date->format('Y-m-d'), 'work_day');
            if ($leaveRequest->substitute_holiday_date) {
                $overrides->put($leaveRequest->substitute_holiday_date->format('Y-m-d'), 'substitute_holiday');
            }
        }

        return $overrides;
    }

    /**
     * 作業日報の登録状況を日付ごとに判定する。draft(下書き・未提出)、
     * pending_confirmation(提出済みだが資材管理担当者の確認待ち)、
     * confirmed(確認済み、またはLaborCostが発生しない内容で提出済み)の3状態。
     *
     * @return Collection<string, string>
     */
    private function buildDailyReportStatusByDate(Carbon $gridStart, Carbon $gridEnd): Collection
    {
        $reports = DailyReport::where('staff_id', Auth::id())
            ->whereDate('work_date', '>=', $gridStart->toDateString())
            ->whereDate('work_date', '<=', $gridEnd->toDateString())
            ->get();

        $hasProvisionalByReportId = LaborCost::whereIn('daily_report_id', $reports->pluck('id'))
            ->where('is_provisional', true)
            ->pluck('daily_report_id')
            ->unique();

        return $reports->mapWithKeys(function (DailyReport $report) use ($hasProvisionalByReportId) {
            $status = ! $report->isSubmitted()
                ? 'draft'
                : ($hasProvisionalByReportId->contains($report->id) ? 'pending_confirmation' : 'confirmed');

            return [$report->work_date->format('Y-m-d') => $status];
        });
    }
}
