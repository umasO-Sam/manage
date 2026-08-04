<?php

namespace App\Http\Controllers;

use App\Models\CategoryCode;
use App\Models\DailyReport;
use App\Models\LaborCost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * 資材管理担当者向けの作業日報確認画面。日付単位で表示し、提出済みの作業日報のうち
 * 生成されたLaborCostがまだ仮登録(is_provisional=true)のままのもの(差し戻し中を除く)を
 * 一覧して、内容を確認したうえで確定または差し戻せる。個々のLaborCost行の編集は行わない
 * (内容を直したい場合は本人に作業日報を再提出してもらう運用、
 * LaborCostController::bulkConfirm()と同じ方針)。
 */
class DailyReportReviewController extends Controller
{
    public function index(Request $request): View
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
        ]);
    }

    public function decide(Request $request, DailyReport $dailyReport): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:confirm,reject'],
            'rejection_reason' => ['nullable', 'string', 'max:2000', 'required_if:action,reject'],
        ]);

        if ($data['action'] === 'confirm') {
            LaborCost::where('daily_report_id', $dailyReport->id)
                ->where('is_provisional', true)
                ->update(['is_provisional' => false]);

            return back()->with('status', 'daily-report-confirmed');
        }

        $dailyReport->update([
            'rejected_at' => now(),
            'rejection_reason' => $data['rejection_reason'],
        ]);

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
