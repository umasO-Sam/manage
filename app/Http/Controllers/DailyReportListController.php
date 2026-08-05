<?php

namespace App\Http\Controllers;

use App\Models\DailyReport;
use App\Models\Holiday;
use App\Models\LaborCost;
use App\Models\Staff;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * 作業日報一覧。勤務状況一覧と同じ表示方法(今日を基準に前1週間・先4週間(35日)を横軸に、
 * 全社員(部署順・表示順)を縦軸に一覧表示)で、各日の作業日報が提出・確認されているかを
 * 確認する画面。ルート側のsupervisor.or.managerミドルウェアにより
 * 資材管理担当者・上長のみがアクセスでき、常に全社員分を表示する。
 */
class DailyReportListController extends Controller
{
    public function index(): View
    {
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

        $staffList = Staff::orderedForRoster()->get();

        return view('daily-reports.list.index', [
            'dates' => $dates,
            'today' => $today->format('Y-m-d'),
            'holidaysByDate' => $holidaysByDate,
            'staffGroups' => $staffList->groupBy('department'),
            'statusByStaffAndDate' => $this->buildDailyReportStatusByStaffAndDate($rangeStart, $rangeEnd, $staffList),
            'purchaseInputByStaffAndDate' => $this->buildPurchaseInputByStaffAndDate($rangeStart, $rangeEnd, $staffList),
        ]);
    }

    /**
     * 仕入管理のデータ入力で登録された人工レコード(作業日報を経由していない=daily_report_idがnull)が
     * ある担当者・日付。作業日報が未提出でも人工データは既に入力済みであることを一覧上で示すために使う。
     *
     * @return array<int, array<string, bool>> staff_id => [work_date => true]
     */
    private function buildPurchaseInputByStaffAndDate(Carbon $rangeStart, Carbon $rangeEnd, \Illuminate\Support\Collection $staffList): array
    {
        $rows = LaborCost::whereNull('daily_report_id')
            ->where('is_provisional', false)
            ->whereDate('work_date', '>=', $rangeStart->toDateString())
            ->whereDate('work_date', '<=', $rangeEnd->toDateString())
            ->whereIn('staff_id', $staffList->pluck('id'))
            ->get(['staff_id', 'work_date']);

        $result = [];
        foreach ($rows as $row) {
            $result[$row->staff_id][$row->work_date->format('Y-m-d')] = true;
        }

        return $result;
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
