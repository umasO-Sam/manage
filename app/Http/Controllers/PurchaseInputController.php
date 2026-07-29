<?php

namespace App\Http\Controllers;

use App\Models\CategoryCode;
use App\Models\LaborCost;
use App\Models\PurchaseDetail;
use App\Models\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseInputController extends Controller
{
    public function create(): View
    {
        return view('purchasing.input', [
            'categories' => CategoryCode::orderBy('code')->get(),
            'laborStaff' => Staff::where('is_labor_target', true)->orderBy('name')->get(),
            'provisionalCount' => PurchaseDetail::where('is_provisional', true)->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->string('form_type')->value() === 'labor') {
            return $this->storeLabor($request);
        }

        return $this->storePurchaseDetail($request);
    }

    private function storePurchaseDetail(Request $request): RedirectResponse
    {
        $isProvisional = $request->boolean('is_provisional');

        $data = $request->validate([
            'item_code' => ['required', 'string', 'max:255'],
            'machine_no' => ['nullable', 'string', 'max:255'],
            'product_name' => ['nullable', 'string', 'max:255'],
            'category_id' => [$isProvisional ? 'nullable' : 'required', 'integer', 'exists:category_codes,id'],
            'manufacturer' => [$isProvisional ? 'nullable' : 'required', 'string', 'max:255'],
            'item_name' => [$isProvisional ? 'nullable' : 'required', 'string', 'max:255'],
            'dimensions' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'required_qty' => ['nullable', 'numeric'],
            'usage_purpose' => ['nullable', 'string', 'max:255'],
            'order_qty' => [$isProvisional ? 'nullable' : 'required', 'numeric'],
            'unit' => ['nullable', 'string', 'max:50'],
            'unit_price' => [$isProvisional ? 'nullable' : 'required', 'numeric'],
            'stock_qty' => ['nullable', 'numeric'],
            'supplier_name' => [$isProvisional ? 'nullable' : 'required', 'string', 'max:255'],
            'order_date' => ['nullable', 'date'],
            'arrival_date' => ['nullable', 'date'],
            'invoice_date' => ['nullable', 'date'],
            'recipient' => ['nullable', 'string', 'max:255'],
            'order_received_date' => ['nullable', 'date'],
            'delivery_dest' => ['nullable', 'string', 'max:255'],
            'order_amount' => ['nullable', 'numeric'],
            'sales_date' => ['nullable', 'date'],
            'supplier_invoice_no' => ['nullable', 'string', 'max:255'],
        ]);
        $data['is_provisional'] = $isProvisional;

        PurchaseDetail::create($data);

        return redirect()->route('purchasing.input')->with('status', $isProvisional ? 'input-provisional' : 'input-created');
    }

    private function storeLabor(Request $request): RedirectResponse
    {
        $isProvisional = $request->boolean('is_provisional');

        $data = $request->validate([
            'work_date' => [$isProvisional ? 'nullable' : 'required', 'date'],
            'staff_id' => [$isProvisional ? 'nullable' : 'required', 'integer', 'exists:staff,id'],
            'order_no' => ['nullable', 'string', 'max:255'],
            'machine_no' => ['nullable', 'string', 'max:255'],
            'category_id' => [$isProvisional ? 'nullable' : 'required', 'integer', 'exists:category_codes,id'],
            'work_hours' => ['nullable', 'integer', 'min:0'],
            'work_minutes' => ['nullable', 'integer', 'min:0', 'max:59'],
            'is_overtime' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $data['is_overtime'] = $request->boolean('is_overtime');
        $data['is_provisional'] = $isProvisional;
        $data['position_weight_cache'] = $data['staff_id'] ?? null
            ? Staff::find($data['staff_id'])?->position_weight
            : null;

        LaborCost::create($data);

        return redirect()->route('purchasing.input')->with('status', $isProvisional ? 'input-provisional' : 'input-created');
    }
}
