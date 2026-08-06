<?php

namespace App\Http\Controllers;

use App\Models\CategoryCode;
use App\Models\DailyReport;
use App\Models\LaborCost;
use App\Models\OperationLog;
use App\Services\TimecardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * 経理資材担当向けの作業日報確認画面。日付単位で表示し、提出済みの作業日報のうち
 * 生成されたLaborCostがまだ仮登録(is_provisional=true)のままのもの(差し戻し中を除く)を
 * 一覧して、内容を確認したうえで確定または差し戻せる。個々のLaborCost行の編集は行わない
 * (内容を直したい場合は本人に作業日報を再提出してもらう運用、
 * LaborCostController::bulkConfirm()と同じ方針)。
 */
class DailyReportReviewController extends Controller
{
    public function index(Request $request, TimecardService $timecard): View
    {
        $date = $this->resolveDate($request->query('date'));

        $reports = $this->pendingReportsQuery()
            ->whereDate('work_date', $date)
            ->with([
                'staff',
                'entries' => fn ($q) => $q->orderBy('start_minute'),
                'entries.category',
            ])
            ->get()
            ->sortBy(fn (DailyReport $r) => $r->staff->name)
            ->values();

        // 作業日報の「なぞって選択」グリッドと同じ分類ボタン(社内人工・雑人工、コード59〜71)を
        // 参照して色分けする。DailyReportController::show()と同じ絞り込み条件。
        $categories = CategoryCode::whereIn('major_category', ['社内人工', '雑人工'])
            ->whereBetween('code', [59, 71])->orderBy('code')->get()
            ->map(fn (CategoryCode $c) => ['id' => $c->id, 'label' => $c->code.':'.($c->sub_category ?: $c->major_category)])
            ->values();

        return view('daily-reports.review.index', [
            'reports' => $reports,
            'categories' => $categories,
            'date' => $date,
            'prevDate' => Carbon::parse($date)->subDay()->format('Y-m-d'),
            'nextDate' => Carbon::parse($date)->addDay()->format('Y-m-d'),
            ...$this->timecardContext($reports, $date, $timecard),
        ]);
    }

    /**
     * タイムカードの打刻と日報の入力内容を突き合わせた結果。翌日以降にまとめて確認する
     * 運用のため、確認画面で「打刻とこれだけずれている」と気づける形で並べる。
     * 連携が無効・未紐づけの場合は空になり、画面側では何も表示しない。
     *
     * @param  \Illuminate\Support\Collection<int, DailyReport>  $reports
     * @return array{punchesByStaff: array<int, array{come: int|null, bye: int|null}>, timecardWarnings: array<int, string|null>, timecardService: TimecardService}
     */
    private function timecardContext($reports, string $date, TimecardService $timecard): array
    {
        $day = Carbon::parse($date);
        $staffList = $reports->pluck('staff')->filter()->unique('id')->values();

        $punches = $timecard->punchesFor($staffList, $day, $day);

        $punchesByStaff = [];
        $warnings = [];

        foreach ($reports as $report) {
            $punch = $punches[$report->staff_id][$date] ?? null;
            $punchesByStaff[$report->staff_id] = $punch;

            // 休暇のエントリは勤務時間ではないため、突き合わせの対象から外す。
            $worked = $report->entries->where('is_leave', false);

            $warnings[$report->id] = $timecard->divergenceWarning(
                $punch,
                $worked->min('start_minute'),
                $worked->max('end_minute')
            );
        }

        return [
            'punchesByStaff' => $punchesByStaff,
            'timecardWarnings' => $warnings,
            'timecardService' => $timecard,
        ];
    }

    public function decide(Request $request, DailyReport $dailyReport): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:confirm,reject'],
            'rejection_reason' => ['nullable', 'string', 'max:2000', 'required_if:action,reject'],
        ]);

        // 状態更新と操作ログは必ず対で残す(片方だけ成立して履歴が欠けるのを防ぐ)。
        if ($data['action'] === 'confirm') {
            DB::transaction(function () use ($dailyReport) {
                LaborCost::where('daily_report_id', $dailyReport->id)
                    ->where('is_provisional', true)
                    ->update(['is_provisional' => false]);

                OperationLog::record(OperationLog::ACTION_DAILY_REPORT_CONFIRM, $dailyReport, $dailyReport->staff_id);
            });

            return back()->with('status', 'daily-report-confirmed');
        }

        DB::transaction(function () use ($dailyReport, $data) {
            $dailyReport->update([
                'rejected_at' => now(),
                'rejection_reason' => $data['rejection_reason'],
            ]);

            OperationLog::record(
                OperationLog::ACTION_DAILY_REPORT_REJECT,
                $dailyReport,
                $dailyReport->staff_id,
                $data['rejection_reason']
            );
        });

        return back()->with('status', 'daily-report-rejected');
    }

    /**
     * 提出済みでLaborCostが仮登録のまま、かつ差し戻し中でない作業日報。
     *
     * @return \Illuminate\Database\Eloquent\Builder<DailyReport>
     */
    private function pendingReportsQuery()
    {
        $reportIds = LaborCost::where('is_provisional', true)
            ->whereNotNull('daily_report_id')
            ->distinct()
            ->pluck('daily_report_id');

        return DailyReport::whereIn('id', $reportIds)->whereNull('rejected_at');
    }

    /**
     * ?date= が無い・不正な場合は、確認待ちの中で一番古い日付をデフォルトにする
     * (無ければ今日)。管理者が古い滞留分から順に処理しやすいようにするため。
     */
    private function resolveDate(?string $date): string
    {
        if ($date) {
            try {
                return Carbon::createFromFormat('Y-m-d', $date)->format('Y-m-d');
            } catch (\Exception) {
                // fall through to default below
            }
        }

        $earliest = $this->pendingReportsQuery()->orderBy('work_date')->value('work_date');

        return $earliest ? Carbon::parse($earliest)->format('Y-m-d') : now()->format('Y-m-d');
    }
}
