<?php

namespace App\Http\Controllers;

use App\Models\PurchaseDetail;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): View
    {
        $supplier = trim((string) $request->query('supplier_name', ''));
        $itemCode = trim((string) $request->query('item_code', ''));
        $dateFrom = $request->query('date_from', '');
        $dateTo = $request->query('date_to', '');
        $includeProvisional = $request->boolean('include_provisional');

        $query = PurchaseDetail::query();
        if (! $includeProvisional) {
            $query->where('is_provisional', false);
        }

        if ($supplier !== '') {
            $query->where('supplier_name', 'like', "%{$supplier}%");
        }
        if ($itemCode !== '') {
            $query->where('item_code', 'like', "%{$itemCode}%");
        }
        if ($dateFrom !== '') {
            $query->whereDate('order_date', '>=', $dateFrom);
        }
        if ($dateTo !== '') {
            $query->whereDate('order_date', '<=', $dateTo);
        }

        $details = ($supplier !== '' || $itemCode !== '' || $dateFrom !== '' || $dateTo !== '')
            ? $query->orderBy('supplier_name')->orderByDesc('order_date')->limit(500)->get()
            : collect();

        return view('purchasing.orders.index', [
            'details' => $details,
            'filters' => compact('supplier', 'itemCode', 'dateFrom', 'dateTo', 'includeProvisional'),
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

        if ($details->pluck('is_provisional')->unique()->count() > 1) {
            throw ValidationException::withMessages([
                'target_ids' => '仮登録と確定済みのレコードは同じ注文書にまとめて印刷できません。',
            ]);
        }

        $isProvisional = (bool) $details->first()?->is_provisional;
        $total = $isProvisional ? null : $details->sum(fn (PurchaseDetail $d) => $d->lineTotal());

        return view('purchasing.orders.print', [
            'details' => $details,
            'total' => $total,
            'isProvisional' => $isProvisional,
            'staffName' => $data['staff_name'],
            'staffPhone' => $data['staff_phone'] ?? '',
            'remarks' => $data['remarks'] ?? '',
        ]);
    }
}
