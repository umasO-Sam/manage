<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * 本人カレンダー(月間、土曜始まり)。休日マスタ(祝日・会社休日・有給休暇取得推奨日)と、
 * 自分自身の休暇・勤務申請(承認待ち・承認済み)を重ねて表示する。日付をクリックすると
 * その日を対象日にした新規申請画面(leave-requests.create)へ遷移する。
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

        $holidaysByDate = Holiday::whereBetween('date', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->get()
            ->keyBy(fn (Holiday $h) => $h->date->format('Y-m-d'));

        $leaveRequestsByDate = $this->buildLeaveRequestsByDate($gridStart, $gridEnd);

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
     * 自分自身の承認待ち・承認済み申請を、対象期間(start_date〜end_date)の各日付に展開する。
     *
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, LeaveRequest>>
     */
    private function buildLeaveRequestsByDate(Carbon $gridStart, Carbon $gridEnd)
    {
        $requests = LeaveRequest::where('staff_id', Auth::id())
            ->whereIn('status', [LeaveRequest::STATUS_PENDING, LeaveRequest::STATUS_APPROVED])
            ->whereDate('start_date', '<=', $gridEnd->toDateString())
            ->where(function ($query) use ($gridStart) {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', $gridStart->toDateString());
            })
            ->get();

        $byDate = collect();
        foreach ($requests as $leaveRequest) {
            $cursor = $leaveRequest->start_date->copy();
            $end = $leaveRequest->end_date ?? $leaveRequest->start_date;
            while ($cursor->lte($end)) {
                $key = $cursor->format('Y-m-d');
                $byDate->put($key, $byDate->get($key, collect())->push($leaveRequest));
                $cursor->addDay();
            }
        }

        return $byDate;
    }
}
