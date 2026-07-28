<?php

namespace App\Http\Controllers;

use App\Models\PurchaseDetail;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseInvoiceController extends Controller
{
    private const ALLOWED_DATE_TYPES = ['order_date', 'invoice_date'];

    public function index(Request $request): View
    {
        $dateFrom = $request->query('date_from', '');
        $dateTo = $request->query('date_to', '');
        $dateType = in_array($request->query('date_type'), self::ALLOWED_DATE_TYPES, true)
            ? $request->query('date_type')
            : 'invoice_date';
        $supplier = trim((string) $request->query('supplier_name', ''));

        $suppliers = collect();
        $details = collect();
        $summary = null;

        if ($dateFrom !== '' && $dateTo !== '') {
            $suppliers = PurchaseDetail::query()
                ->whereNotNull('supplier_name')
                ->where('supplier_name', '<>', '')
                ->whereBetween($dateType, [$dateFrom, $dateTo])
                ->distinct()
                ->orderBy('supplier_name')
                ->pluck('supplier_name');

            if ($supplier !== '') {
                $details = PurchaseDetail::query()
                    ->where('supplier_name', $supplier)
                    ->whereBetween($dateType, [$dateFrom, $dateTo])
                    ->orderBy($dateType)
                    ->get();

                $subtotal = $details->sum(fn (PurchaseDetail $d) => $d->lineTotal());
                $tax = floor($subtotal * 0.10);
                $summary = [
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'total' => $subtotal + $tax,
                ];
            }
        }

        return view('purchasing.invoices.index', [
            'suppliers' => $suppliers,
            'details' => $details,
            'summary' => $summary,
            'filters' => compact('dateFrom', 'dateTo', 'dateType', 'supplier'),
        ]);
    }

    public function print(Request $request): View
    {
        $data = $request->validate([
            'supplier_name' => ['required', 'string', 'max:255'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date'],
            'date_type' => ['required', 'in:order_date,invoice_date'],
        ]);

        $details = PurchaseDetail::query()
            ->where('supplier_name', $data['supplier_name'])
            ->whereBetween($data['date_type'], [$data['date_from'], $data['date_to']])
            ->orderBy($data['date_type'])
            ->get();

        $subtotal = $details->sum(fn (PurchaseDetail $d) => $d->lineTotal());
        $tax = floor($subtotal * 0.10);

        return view('purchasing.invoices.print', [
            'details' => $details,
            'supplierName' => $data['supplier_name'],
            'dateFrom' => $data['date_from'],
            'dateTo' => $data['date_to'],
            'dateType' => $data['date_type'],
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $subtotal + $tax,
        ]);
    }
}
