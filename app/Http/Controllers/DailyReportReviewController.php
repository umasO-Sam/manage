<?php

namespace App\Http\Controllers;

use App\Models\CategoryCode;
use App\Models\DailyReport;
use App\Models\LaborCost;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * 資材管理担当者向けの作業日報確認画面。提出済みの作業日報のうち、生成されたLaborCostが
 * まだ仮登録(is_provisional=true)のままのものを一覧し、内容を確認したうえで確定できる。
 * 個々のLaborCost行の編集は行わない(内容を直したい場合は本人に作業日報を再提出してもらう
 * 運用、LaborCostController::bulkConfirm()と同じ方針)。
 */
class DailyReportReviewController extends Controller
{
    public function index(): View
    {
        $reportIds = LaborCost::where('is_provisional', true)
            ->whereNotNull('daily_report_id')
            ->distinct()
            ->pluck('daily_report_id');

        $reports = DailyReport::with([
            'staff',
            'entries' => fn ($q) => $q->orderBy('start_minute'),
            'entries.category',
        ])
            ->whereIn('id', $reportIds)
            ->orderBy('work_date')
            ->get();

        // 作業日報の「なぞって選択」グリッドと同じ分類ボタン(社内人工・雑人工、コード59〜71)を
        // 参照して色分けする。DailyReportController::show()と同じ絞り込み条件。
        $categories = CategoryCode::whereIn('major_category', ['社内人工', '雑人工'])
            ->whereBetween('code', [59, 71])->orderBy('code')->get()
            ->map(fn (CategoryCode $c) => ['id' => $c->id, 'label' => $c->code.':'.($c->sub_category ?: $c->major_category)])
            ->values();

        return view('daily-reports.review.index', ['reports' => $reports, 'categories' => $categories]);
    }

    public function confirm(DailyReport $dailyReport): RedirectResponse
    {
        LaborCost::where('daily_report_id', $dailyReport->id)
            ->where('is_provisional', true)
            ->update(['is_provisional' => false]);

        return back()->with('status', 'daily-report-confirmed');
    }
}
