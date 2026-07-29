<?php

namespace App\Http\Controllers;

use App\Models\LaborCost;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LaborCostController extends Controller
{
    public function index(Request $request): View
    {
        $dateFrom = $request->query('date_from', '');
        $dateTo = $request->query('date_to', '');
        $staffId = $request->query('staff_id', '');
        $orderNo = trim((string) $request->query('order_no', ''));

        $query = LaborCost::query()->with(['staff', 'category'])->where('is_provisional', false);

        if ($dateFrom !== '') {
            $query->where('work_date', '>=', $dateFrom);
        }
        if ($dateTo !== '') {
            $query->where('work_date', '<=', $dateTo);
        }
        if ($staffId !== '') {
            $query->where('staff_id', $staffId);
        }
        if ($orderNo !== '') {
            $query->where('order_no', 'like', "%{$orderNo}%");
        }

        // 集計(合計時間・労務費)は表示件数の上限に関わらず該当する全レコードを対象にする必要があるため、
        // 先に全件取得してから集計し、一覧表示だけを件数上限で絞る(過去にlimit()を集計前にかけてしまい、
        // 1000件を超える注番の合計が黙って過小表示されるバグがあった)。
        $allRows = $query->orderByDesc('work_date')->get();

        $totalMinutes = $allRows->sum(fn (LaborCost $r) => $r->totalMinutes());
        $totalCost = $allRows->sum(fn (LaborCost $r) => $r->estimatedCost());

        $displayLimit = 1000;
        $rows = $allRows->take($displayLimit);

        return view('purchasing.labor.index', [
            'rows' => $rows,
            'matchedCount' => $allRows->count(),
            'displayLimit' => $displayLimit,
            'laborStaff' => Staff::where('is_labor_target', true)->orderBy('name')->get(),
            'filters' => compact('dateFrom', 'dateTo', 'staffId', 'orderNo'),
            'summary' => [
                'total_hours' => intdiv($totalMinutes, 60),
                'total_mins' => $totalMinutes % 60,
                'total_labor' => round($totalMinutes / 480, 2),
                'total_cost' => round($totalCost),
            ],
        ]);
    }
}
