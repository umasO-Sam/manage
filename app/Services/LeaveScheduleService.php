<?php

namespace App\Services;

use App\Models\LeaveRequest;
use Illuminate\Support\Carbon;

/**
 * 承認済みの休暇・勤務申請から「終日休みの日」を割り出す。作業日報一覧で、その日は
 * 日報の提出が要らないことを示すために使う。
 *
 * 勤務状況一覧(WorkStatusController)が承認待ちも含めて種別を表示するのに対し、
 * こちらは承認済みだけを見る。承認待ちの申請は却下される可能性があり、日報が不要と
 * 早合点させたくないため(RUNBOOKの取消フローと同じく、承認済みかどうかで判定する)。
 */
class LeaveScheduleService
{
    /**
     * @param  array<int, int>  $staffIds
     * @return array<int, array<string, bool>> staff_id => [work_date => true]
     */
    public function fullDayOffDatesByStaff(Carbon $rangeStart, Carbon $rangeEnd, array $staffIds): array
    {
        $rangeStartStr = $rangeStart->toDateString();
        $rangeEndStr = $rangeEnd->toDateString();

        $requests = LeaveRequest::where('status', LeaveRequest::STATUS_APPROVED)
            ->whereIn('staff_id', $staffIds)
            ->where(function ($query) use ($rangeStartStr, $rangeEndStr) {
                $query->where(function ($q) use ($rangeStartStr, $rangeEndStr) {
                    $q->whereDate('start_date', '<=', $rangeEndStr)
                        ->where(function ($q2) use ($rangeStartStr) {
                            $q2->whereNull('end_date')->orWhereDate('end_date', '>=', $rangeStartStr);
                        });
                })
                    ->orWhere(function ($q) use ($rangeStartStr, $rangeEndStr) {
                        $q->whereDate('substitute_holiday_date', '>=', $rangeStartStr)
                            ->whereDate('substitute_holiday_date', '<=', $rangeEndStr);
                    })
                    ->orWhere(function ($q) use ($rangeStartStr, $rangeEndStr) {
                        $q->whereDate('compensatory_date', '>=', $rangeStartStr)
                            ->whereDate('compensatory_date', '<=', $rangeEndStr);
                    });
            })
            ->get();

        $result = [];

        foreach ($requests as $leaveRequest) {
            if ($leaveRequest->start_date !== null && $leaveRequest->isFullDayOff()) {
                $mainStart = Carbon::parse($leaveRequest->start_date)->max($rangeStart);
                $mainEnd = Carbon::parse($leaveRequest->end_date ?? $leaveRequest->start_date)->min($rangeEnd);
                for ($d = $mainStart->copy(); $d->lte($mainEnd); $d->addDay()) {
                    $result[$leaveRequest->staff_id][$d->format('Y-m-d')] = true;
                }
            }

            // 振替休日・代休日はその日を丸ごと休むため、申請の種別によらず終日休み。
            foreach ([$leaveRequest->substitute_holiday_date, $leaveRequest->compensatory_date] as $date) {
                $dateString = $date?->format('Y-m-d');
                if ($dateString !== null && $dateString >= $rangeStartStr && $dateString <= $rangeEndStr) {
                    $result[$leaveRequest->staff_id][$dateString] = true;
                }
            }
        }

        return $result;
    }
}
