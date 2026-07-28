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

        $query = LaborCost::query()->with(['staff', 'category']);

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

        $rows = $query->orderByDesc('work_date')->limit(1000)->get();

        $totalMinutes = $rows->sum(fn (LaborCost $r) => $r->totalMinutes());
        $totalCost = $rows->sum(fn (LaborCost $r) => $r->estimatedCost());

        return view('purchasing.labor.index', [
            'rows' => $rows,
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
