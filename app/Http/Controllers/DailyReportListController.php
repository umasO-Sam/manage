<?php

namespace App\Http\Controllers;

use App\Models\DailyReport;
use App\Models\Holiday;
use App\Models\LaborCost;
use App\Models\Staff;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * 作業日報一覧。勤務状況一覧と同じ表示方法(今日を基準に前1週間・先4週間(35日)を横軸に、
 * 全社員(部署順・表示順)を縦軸に一覧表示)で、各日の作業日報が提出・確認されているかを
 * 確認する画面。資材管理担当者・上長は全員分を、それ以外の一般社員・営業担当は
 * 自分の分のみ閲覧できる。
 */
class DailyReportListController extends Controller
{
    public function index(): View
    {
        /** @var Staff $viewer */
        $viewer = Auth::user();
        $isPrivileged = $viewer->is_procurement_manager || $viewer->is_supervisor;

        $today = Carbon::today();
        $rangeStart = $today->copy()->subDays(7);
        $rangeEnd = $today->copy()->addDays(27);

        $dates = [];
        for ($d = $rangeStart->copy(); $d->lte($rangeEnd); $d->addDay()) {
            $dates[] = $d->format('Y-m-d');
        }

        $holidaysByDate = Holiday::whereDate('date', '>=', $rangeStart->toDateString())
            ->whereDate('date', '<=', $rangeEnd->toDateString())
            ->get()->keyBy(fn (Holiday $h) => $h->date->format('Y-m-d'));

        $staffList = $isPrivileged ? Staff::orderedForRoster()->get() : Staff::where('id', $viewer->id)->get();

        return view('daily-reports.list.index', [
            'dates' => $dates,
            'today' => $today->format('Y-m-d'),
            'holidaysByDate' => $holidaysByDate,
            'staffGroups' => $staffList->groupBy('department'),
            'statusByStaffAndDate' => $this->buildDailyReportStatusByStaffAndDate($rangeStart, $rangeEnd, $staffList),
            'isPrivileged' => $isPrivileged,
        ]);
    }

    /**
     * @return array<int, array<string, string>> staff_id => [work_date => draft|pending_confirmation|rejected|confirmed]
     */
    private function buildDailyReportStatusByStaffAndDate(Carbon $rangeStart, Carbon $rangeEnd, \Illuminate\Support\Collection $staffList): array
    {
        $reports = DailyReport::whereDate('work_date', '>=', $rangeStart->toDateString())
            ->whereDate('work_date', '<=', $rangeEnd->toDateString())
            ->whereIn('staff_id', $staffList->pluck('id'))
            ->get();

        $provisionalReportIds = LaborCost::where('is_provisional', true)
            ->whereNotNull('daily_report_id')
            ->pluck('daily_report_id');

        $result = [];
        foreach ($reports as $report) {
            $status = match (true) {
                $report->isRejected() => 'rejected',
                ! $report->isSubmitted() => 'draft',
                $provisionalReportIds->contains($report->id) => 'pending_confirmation',
                default => 'confirmed',
            };

            $result[$report->staff_id][$report->work_date->format('Y-m-d')] = $status;
        }

        return $result;
    }
}
