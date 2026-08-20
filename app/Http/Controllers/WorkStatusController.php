<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * 勤務状況一覧。基準日(既定は今日)から前1週間・先4週間(35日)を横軸に、全社員(部署順・表示順)を
 * 縦軸に一覧表示する。基準日は4週間(28日)単位で前後に動かせるほか、日付を直接指定して飛べる。
 * 休暇・休日出勤の種別のみを表示する(作業日報の提出・確認状況は作業日報一覧画面で確認する)。
 * 承認待ち(オレンジ)・承認済み(緑)の色分けは、権限によらず全員に見せる。
 */
class WorkStatusController extends Controller
{
    /** 前後に動かす単位(4週間)。 */
    private const SHIFT_DAYS = 28;

    public function index(Request $request): View
    {
        $today = Carbon::today();
        $anchor = $this->parseDate($request->query('date')) ?? $today->copy();

        $rangeStart = $anchor->copy()->subDays(7);
        $rangeEnd = $anchor->copy()->addDays(27);

        $dates = [];
        for ($d = $rangeStart->copy(); $d->lte($rangeEnd); $d->addDay()) {
            $dates[] = $d->format('Y-m-d');
        }

        $holidaysByDate = Holiday::whereDate('date', '>=', $rangeStart->toDateString())
            ->whereDate('date', '<=', $rangeEnd->toDateString())
            ->get()->keyBy(fn (Holiday $h) => $h->date->format('Y-m-d'));

        return view('work-status.index', [
            'dates' => $dates,
            'today' => $today->format('Y-m-d'),
            'anchor' => $anchor->format('Y-m-d'),
            'prevAnchor' => $anchor->copy()->subDays(self::SHIFT_DAYS)->format('Y-m-d'),
            'nextAnchor' => $anchor->copy()->addDays(self::SHIFT_DAYS)->format('Y-m-d'),
            'holidaysByDate' => $holidaysByDate,
            'staffGroups' => Staff::forRoster()->get()->groupBy('department'),
            'leaveEntriesByStaffAndDate' => $this->buildLeaveEntriesByStaffAndDate($rangeStart, $rangeEnd),
        ]);
    }

    /**
     * 不正な日付指定は無視して既定(今日)に戻す。
     */
    private function parseDate(?string $date): ?Carbon
    {
        if (! $date) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * 期間内にかかる全社員の休暇・勤務申請(承認待ち・承認済み)。休日勤務申請の振替休日、
     * 代休申請の代休日は、対象日がその日と一致する場合のみ役割を切り替える。
     *
     * @return array<int, array<string, array<int, array{request: LeaveRequest, role: string}>>>
     */
    private function buildLeaveEntriesByStaffAndDate(Carbon $rangeStart, Carbon $rangeEnd): array
    {
        $rangeStartStr = $rangeStart->toDateString();
        $rangeEndStr = $rangeEnd->toDateString();

        $requests = LeaveRequest::whereIn('status', [...LeaveRequest::PENDING_STATUSES, LeaveRequest::STATUS_APPROVED])
            ->where(function ($query) use ($rangeStartStr, $rangeEndStr) {
                $query->where(function ($q) use ($rangeStartStr, $rangeEndStr) {
                    $q->whereDate('start_date', '<=', $rangeEndStr)
                        ->where(function ($q2) use ($rangeStartStr) {
                            $q2->whereNull('end_date')->orWhereDate('end_date', '>=', $rangeStartStr);
                        });
                })
                    ->orWhere(function ($q) use ($rangeStartStr, $rangeEndStr) {
                        $q->whereDate('substitute_holiday_date', '>=', $rangeStartStr)->whereDate('substitute_holiday_date', '<=', $rangeEndStr);
                    })
                    ->orWhere(function ($q) use ($rangeStartStr, $rangeEndStr) {
                        $q->whereDate('compensatory_date', '>=', $rangeStartStr)->whereDate('compensatory_date', '<=', $rangeEndStr);
                    });
            })
            ->get();

        $result = [];

        foreach ($requests as $leaveRequest) {
            $mainStart = Carbon::parse($leaveRequest->start_date)->max($rangeStart);
            $mainEnd = Carbon::parse($leaveRequest->end_date ?? $leaveRequest->start_date)->min($rangeEnd);
            for ($d = $mainStart->copy(); $d->lte($mainEnd); $d->addDay()) {
                $result[$leaveRequest->staff_id][$d->format('Y-m-d')][] = ['request' => $leaveRequest, 'role' => 'main'];
            }

            $substituteDate = $leaveRequest->substitute_holiday_date?->format('Y-m-d');
            if ($substituteDate && $substituteDate >= $rangeStartStr && $substituteDate <= $rangeEndStr) {
                $result[$leaveRequest->staff_id][$substituteDate][] = ['request' => $leaveRequest, 'role' => 'substitute'];
            }

            $compensatoryDate = $leaveRequest->compensatory_date?->format('Y-m-d');
            if ($compensatoryDate && $compensatoryDate >= $rangeStartStr && $compensatoryDate <= $rangeEndStr) {
                $result[$leaveRequest->staff_id][$compensatoryDate][] = ['request' => $leaveRequest, 'role' => 'compensatory'];
            }
        }

        return $result;
    }
}
