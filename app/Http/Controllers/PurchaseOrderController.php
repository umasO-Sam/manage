<?php

namespace App\Http\Controllers;

use App\Models\PurchaseDetail;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): View
    {
        $supplier = trim((string) $request->query('supplier_name', ''));
        $dateFrom = $request->query('date_from', '');
        $dateTo = $request->query('date_to', '');

        $query = PurchaseDetail::query()->where('is_provisional', false);

        if ($supplier !== '') {
            $query->where('supplier_name', 'like', "%{$supplier}%");
        }
        if ($dateFrom !== '') {
            $query->where('order_date', '>=', $dateFrom);
        }
        if ($dateTo !== '') {
            $query->where('order_date', '<=', $dateTo);
        }

        $details = ($supplier !== '' || $dateFrom !== '' || $dateTo !== '')
            ? $query->orderBy('supplier_name')->orderByDesc('order_date')->limit(500)->get()
            : collect();

        return view('purchasing.orders.index', [
            'details' => $details,
            'filters' => compact('supplier', 'dateFrom', 'dateTo'),
        ]);
    }

    public function print(Request $request): View
    {
        $data = $request->validate([
            'target_ids' => ['required', 'array', 'min:1'],
            'target_ids.*' => ['integer', 'exists:purchase_details,id'],
            'staff_name' => ['required', 'string', 'max:255'],
            'staff_phone' => ['nullable', 'string', 'max:50'],
            'remarks' => ['nullable', 'string'],
        ]);

        $details = PurchaseDetail::whereIn('id', $data['target_ids'])->get();
        $total = $details->sum(fn (PurchaseDetail $d) => $d->lineTotal());

        return view('purchasing.orders.print', [
            'details' => $details,
            'total' => $total,
            'staffName' => $data['staff_name'],
            'staffPhone' => $data['staff_phone'] ?? '',
            'remarks' => $data['remarks'] ?? '',
        ]);
    }
}
