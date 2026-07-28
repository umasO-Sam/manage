<?php

namespace App\Http\Controllers;

use App\Models\LaborCost;
use App\Models\PurchaseDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CostAnalysisController extends Controller
{
    /**
     * 注番別の原価分析（仕入・外注費 + 人工費 vs 受注金額）。
     * 大量データでもDB側で集計するため、旧dbtestシステムのSQLをそのまま踏襲する。
     */
    public function index(Request $request): View
    {
        $orderNo = trim((string) $request->query('order_no', ''));

        if ($orderNo === '') {
            return view('purchasing.cost.index', ['orderNo' => '', 'result' => null]);
        }

        $parts = DB::table('purchase_details as p')
            ->leftJoin('category_codes as c', 'p.category_id', '=', 'c.id')
            ->where('p.item_code', $orderNo)
            ->where('p.is_provisional', false)
            ->selectRaw('
                MAX(p.order_amount) as total_order_amount,
                SUM(p.order_qty * p.unit_price) as total_material_cost,
                SUM(CASE WHEN c.is_parts = 1 THEN (p.order_qty * p.unit_price) ELSE 0 END) as r_parts,
                SUM(CASE WHEN c.is_outsourcing = 1 AND (p.item_name LIKE ? OR c.major_category LIKE ?) THEN (p.order_qty * p.unit_price) ELSE 0 END) as r_sub_elec_work,
                SUM(CASE WHEN c.is_outsourcing = 1 AND (p.item_name LIKE ? OR c.major_category LIKE ?) THEN (p.order_qty * p.unit_price) ELSE 0 END) as r_sub_elec_ctrl,
                SUM(CASE WHEN c.is_outsourcing = 1 AND p.item_name NOT LIKE ? AND p.item_name NOT LIKE ? AND c.major_category NOT LIKE ? AND c.major_category NOT LIKE ? THEN (p.order_qty * p.unit_price) ELSE 0 END) as r_sub_proc,
                SUM(CASE WHEN c.major_category LIKE ? THEN (p.order_qty * p.unit_price) ELSE 0 END) as r_seikan
            ', [
                '%電気工事%', '%電気工事%',
                '%制御%', '%制御%',
                '%電気工事%', '%制御%', '%電気工事%', '%制御%',
                '%製缶%',
            ])
            ->first();

        $labor = DB::table('labor_costs as l')
            ->leftJoin('category_codes as c', 'l.category_id', '=', 'c.id')
            ->where('l.order_no', $orderNo)
            ->where('l.is_provisional', false)
            ->selectRaw('
                SUM(((l.work_hours * 60 + l.work_minutes) / 480.0) * (CASE WHEN l.is_overtime = 1 THEN 50000 ELSE 40000 END) * (CASE WHEN l.position_weight_cache IS NULL OR l.position_weight_cache = 0 THEN 1.0 ELSE l.position_weight_cache END)) as total_labor_cost,
                SUM(CASE WHEN c.item_name LIKE ? OR c.major_category LIKE ? THEN (((l.work_hours * 60 + l.work_minutes) / 480.0) * (CASE WHEN l.is_overtime = 1 THEN 50000 ELSE 40000 END) * (CASE WHEN l.position_weight_cache IS NULL OR l.position_weight_cache = 0 THEN 1.0 ELSE l.position_weight_cache END)) ELSE 0 END) as r_mech_design,
                SUM(CASE WHEN (c.item_name LIKE ? AND c.item_name LIKE ?) OR (c.major_category LIKE ? AND c.major_category LIKE ?) THEN (((l.work_hours * 60 + l.work_minutes) / 480.0) * (CASE WHEN l.is_overtime = 1 THEN 50000 ELSE 40000 END) * (CASE WHEN l.position_weight_cache IS NULL OR l.position_weight_cache = 0 THEN 1.0 ELSE l.position_weight_cache END)) ELSE 0 END) as r_elec_design,
                SUM(CASE WHEN c.item_name LIKE ? OR c.major_category LIKE ? THEN (((l.work_hours * 60 + l.work_minutes) / 480.0) * (CASE WHEN l.is_overtime = 1 THEN 50000 ELSE 40000 END) * (CASE WHEN l.position_weight_cache IS NULL OR l.position_weight_cache = 0 THEN 1.0 ELSE l.position_weight_cache END)) ELSE 0 END) as r_assembly,
                SUM(CASE WHEN c.item_name LIKE ? OR c.item_name LIKE ? OR c.major_category LIKE ? OR c.major_category LIKE ? THEN (((l.work_hours * 60 + l.work_minutes) / 480.0) * (CASE WHEN l.is_overtime = 1 THEN 50000 ELSE 40000 END) * (CASE WHEN l.position_weight_cache IS NULL OR l.position_weight_cache = 0 THEN 1.0 ELSE l.position_weight_cache END)) ELSE 0 END) as r_adjustment
            ', [
                '%機械設計%', '%機械設計%',
                '%電気%', '%設計%', '%電気%', '%設計%',
                '%組付%', '%組付%',
                '%試運転%', '%調整%', '%試運転%', '%調整%',
            ])
            ->first();

        $topParts = PurchaseDetail::query()
            ->where('item_code', $orderNo)
            ->where('is_provisional', false)
            ->get()
            ->sortByDesc(fn (PurchaseDetail $d) => $d->lineTotal())
            ->take(5)
            ->map(fn (PurchaseDetail $d) => [
                'supplier_name' => $d->supplier_name,
                'item_name' => $d->item_name,
                'line_total' => $d->lineTotal(),
            ])
            ->values();

        $laborTasks = LaborCost::query()
            ->with('category')
            ->where('order_no', $orderNo)
            ->where('is_provisional', false)
            ->get()
            ->groupBy(fn (LaborCost $l) => $l->category?->item_name ?? '未分類')
            ->map(function ($rows, $name) {
                $mins = $rows->sum(fn (LaborCost $l) => $l->totalMinutes());

                return ['name' => $name, 'hours' => intdiv($mins, 60), 'mins' => $mins % 60, 'raw_mins' => $mins];
            })
            ->sortByDesc('raw_mins')
            ->values();

        $orderAmount = (float) ($parts?->total_order_amount ?? 0);
        $materialCost = (float) ($parts?->total_material_cost ?? 0);
        $laborCost = (float) ($labor?->total_labor_cost ?? 0);
        $totalCost = $materialCost + $laborCost;
        $profit = $orderAmount - $totalCost;

        $result = [
            'summary' => [
                'order_amount' => (int) $orderAmount,
                'total_cost' => (int) $totalCost,
                'gross_profit' => (int) $profit,
                'profit_margin' => $orderAmount > 0 ? round(($profit / $orderAmount) * 100, 2) : 0,
            ],
            'ratios' => [
                'mech_design' => (float) ($labor?->r_mech_design ?? 0),
                'elec_design' => (float) ($labor?->r_elec_design ?? 0),
                'parts' => (float) ($parts?->r_parts ?? 0),
                'seikan' => (float) ($parts?->r_seikan ?? 0),
                'assembly' => (float) ($labor?->r_assembly ?? 0),
                'adjustment' => (float) ($labor?->r_adjustment ?? 0),
                'sub_elec_work' => (float) ($parts?->r_sub_elec_work ?? 0),
                'sub_elec_ctrl' => (float) ($parts?->r_sub_elec_ctrl ?? 0),
                'sub_proc' => (float) ($parts?->r_sub_proc ?? 0),
            ],
            'top_parts' => $topParts,
            'labor_tasks' => $laborTasks,
        ];

        return view('purchasing.cost.index', compact('orderNo', 'result'));
    }
}
