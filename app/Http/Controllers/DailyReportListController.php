<?php

namespace App\Http\Controllers;

use App\Models\DailyReport;
use App\Models\Holiday;
use App\Models\LaborCost;
use App\Models\Staff;
use App\Services\WorkTimeComplianceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * 作業日報一覧。勤務状況一覧と同じ表示方法(全社員を部署順・表示順に縦軸、日付を横軸)で、
 * 基準日(既定は今日)までの3週間(21日分)の各日の作業日報が提出・確認されているかを確認する画面。
 * 基準日は2週間(14日)単位で前後に動かせるほか、日付を直接指定して飛べる。
 * 右側には人別に、特別条項付き36協定の絶対上限に抵触しそうな兆候(当月の時間外労働・
 * うち休日労働・年度内の月45時間超の回数・複数月平均・取得済み有給)を並べる。
 * ルート側のsupervisor.or.managerミドルウェアにより経理資材担当・上長のみがアクセスできる。
 */
class DailyReportListController extends Controller
{
    /** 表示する日数(3週間)。 */
    private const RANGE_DAYS = 21;

    /** 前後に動かす単位(2週間)。 */
    private const SHIFT_DAYS = 14;

    public function index(Request $request, WorkTimeComplianceService $compliance): View
    {
        $today = Carbon::today();
        $anchor = $this->parseDate($request->query('date')) ?? $today->copy();

        $rangeStart = $anchor->copy()->subDays(self::RANGE_DAYS - 1);
        $rangeEnd = $anchor->copy();

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
            'anchor' => $anchor->format('Y-m-d'),
            'prevAnchor' => $anchor->copy()->subDays(self::SHIFT_DAYS)->format('Y-m-d'),
            'nextAnchor' => $anchor->copy()->addDays(self::SHIFT_DAYS)->format('Y-m-d'),
            'rangeLabel' => $rangeStart->format('Y/m/d').'〜'.$rangeEnd->format('Y/m/d'),
            'holidaysByDate' => $holidaysByDate,
            'staffGroups' => $staffList->groupBy('department'),
            'statusByStaffAndDate' => $this->buildDailyReportStatusByStaffAndDate($rangeStart, $rangeEnd, $staffList),
            'purchaseInputByStaffAndDate' => $this->buildPurchaseInputByStaffAndDate($rangeStart, $rangeEnd, $staffList),
            // 36協定の集計は「基準日を含む月」で行う(表示範囲を過去にずらすと当時の状況が見られる)。
            'complianceByStaff' => $this->buildComplianceByStaff($staffList, $anchor, $compliance),
            'monthLabel' => $compliance->monthPeriod($anchor)[0]->format('m/d').'〜'.$compliance->monthPeriod($anchor)[1]->format('m/d'),
            'paidLeaveYearLabel' => Staff::paidLeaveYearPeriod($anchor)[0]->format('Y/m/d').'〜'.Staff::paidLeaveYearPeriod($anchor)[1]->format('Y/m/d'),
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
     * 36協定の兆候表示に使う人別サマリ。全社員分をまとめて計算する
     * (1人ずつ計算すると社員数×十数本のクエリになるため)。
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildComplianceByStaff(Collection $staffList, Carbon $today, WorkTimeComplianceService $compliance): array
    {
        $summaries = $compliance->specialClauseSummariesForStaff($staffList->pluck('id')->all(), $today);
        $balances = Staff::paidLeaveBalancesFor($staffList);

        $result = [];

        foreach ($staffList as $staff) {
            $summary = $summaries[$staff->id] ?? null;
            if ($summary === null) {
                continue;
            }

            $balance = $balances[$staff->id] ?? null;

            // 危険: 単月100時間到達・複数月平均80時間超・特別条項6か月使い切り
            // 注意: 単月80時間超(100時間まで残り20時間を切った)・特別条項の残り1か月
            $isDanger = $summary['hardCapExceeded']
                || $summary['worstAverage'] !== null
                || $summary['specialClauseLimitReached'];
            $isWarning = ! $isDanger && (
                $summary['monthOvertimeMinutes'] > WorkTimeComplianceService::MULTI_MONTH_AVERAGE_CAP_MINUTES
                || $summary['specialClauseMonthsRemaining'] <= 1
            );

            $result[$staff->id] = [
                ...$summary,
                'level' => $isDanger ? 'danger' : ($isWarning ? 'warning' : 'ok'),
                'paidLeaveConsumed' => $balance['consumed'] ?? 0.0,
                'paidLeavePending' => $balance['pending'] ?? 0.0,
                'paidLeaveRemaining' => $balance['remainingTotal'] ?? 0.0,
            ];
        }

        return $result;
    }

    /**
     * 仕入管理のデータ入力で登録された人工レコード(作業日報を経由していない=daily_report_idがnull)が
     * ある担当者・日付。作業日報が未提出でも人工データは既に入力済みであることを一覧上で示すために使う。
     *
     * @return array<int, array<string, bool>> staff_id => [work_date => true]
     */
    private function buildPurchaseInputByStaffAndDate(Carbon $rangeStart, Carbon $rangeEnd, Collection $staffList): array
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
    private function buildDailyReportStatusByStaffAndDate(Carbon $rangeStart, Carbon $rangeEnd, Collection $staffList): array
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
