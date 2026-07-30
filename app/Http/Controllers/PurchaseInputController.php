<?php

namespace App\Http\Controllers;

use App\Models\CategoryCode;
use App\Models\LaborCost;
use App\Models\PurchaseDetail;
use App\Models\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PurchaseInputController extends Controller
{
    /**
     * @var int コピー&ペースト一括登録で一度に受け付ける最大行数。
     */
    private const BULK_PASTE_MAX_ROWS = 200;

    public function create(): View
    {
        return view('purchasing.input', [
            'categories' => CategoryCode::orderBy('code')->get(),
            'laborStaff' => Staff::whereNotNull('sid')->orderBy('sid')->get(),
            'provisionalCount' => PurchaseDetail::where('is_provisional', true)->count(),
            'bulkPasteMaxRows' => self::BULK_PASTE_MAX_ROWS,
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
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'item_name' => [$isProvisional ? 'nullable' : 'required', 'string', 'max:255'],
            'dimensions' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'required_qty' => ['nullable', 'numeric'],
            'usage_purpose' => ['nullable', 'string', 'max:255'],
            'order_qty' => [$isProvisional ? 'nullable' : 'required', 'numeric'],
            'unit' => ['nullable', 'string', 'max:50', 'regex:/^[^\d０-９]+$/u'],
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
        ], [
            'unit.regex' => '単位に数字は使用できません。',
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
            'labor_machine_no' => ['nullable', 'string', 'max:255'],
            'labor_category_id' => [$isProvisional ? 'nullable' : 'required', 'integer', 'exists:category_codes,id'],
            'work_hours' => ['nullable', 'integer', 'min:0'],
            'work_minutes' => ['nullable', 'integer', 'min:0', 'max:59'],
            'is_overtime' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $data['machine_no'] = $data['labor_machine_no'] ?? null;
        $data['category_id'] = $data['labor_category_id'] ?? null;
        unset($data['labor_machine_no'], $data['labor_category_id']);
        $data['is_overtime'] = $request->boolean('is_overtime');
        $data['is_provisional'] = $isProvisional;
        $data['position_weight_cache'] = $data['staff_id'] ?? null
            ? Staff::find($data['staff_id'])?->position_weight
            : null;

        LaborCost::create($data);

        return redirect()->route('purchasing.input')->with('status', $isProvisional ? 'input-provisional' : 'input-created');
    }

    /**
     * エクセル等の表(タブ区切り)をコピー&ペーストして仕入明細を一括登録する。
     * 列順は固定: 品名, 機械装置No, 分類(コード), 型式, 数量, 単価, 商社名, メーカー。
     * 注番・注文日付は全行共通としてフォーム上部で1回だけ入力する。
     *
     * confirmedが未送信の場合は登録を実行せず、内容確認画面を表示する。
     * confirmed=1で再送信された時のみ実際に登録する(この時点で貼り付け内容は
     * 確認画面から変更されない前提のため、パース処理をもう一度そのまま通す)。
     */
    public function storeBulkPaste(Request $request): RedirectResponse|View
    {
        $request->validate([
            'item_code' => ['required', 'string', 'max:255'],
            'order_date' => ['nullable', 'date'],
            'paste_data' => ['required', 'string'],
        ]);

        $lines = preg_split('/\r\n|\r|\n/', trim($request->string('paste_data')->value()));
        $lines = array_values(array_filter($lines, fn ($line) => trim($line) !== ''));

        // Excelから見出し行ごと選択・コピーされるケースを考慮し、1列目が「品名」の行は見出しとして読み飛ばす。
        if (isset($lines[0]) && trim(explode("\t", $lines[0])[0]) === '品名') {
            array_shift($lines);
        }

        if (count($lines) === 0) {
            return back()->withInput()->withErrors(['paste_data' => '貼り付けるデータがありません。']);
        }

        if (count($lines) > self::BULK_PASTE_MAX_ROWS) {
            return back()->withInput()->withErrors([
                'paste_data' => '一度に貼り付けできるのは'.self::BULK_PASTE_MAX_ROWS.'行までです。('.count($lines).'行貼り付けられています)',
            ]);
        }

        [$categoryIdsByCode, $categoryLabelsById] = $this->categoryLookupMaps();
        $itemCode = $request->string('item_code')->value();
        $orderDate = $request->input('order_date');

        $rows = [];
        $errors = [];

        foreach ($lines as $index => $line) {
            $rowNumber = $index + 1;
            $columns = array_pad(explode("\t", $line), 8, '');
            [$itemName, $machineNo, $categoryCode, $dimensions, $orderQty, $unitPrice, $supplierName, $manufacturer]
                = array_map(fn ($value) => trim($value), array_slice($columns, 0, 8));

            $normalizedCategoryCode = $this->normalizeNumber($categoryCode);
            // 分類欄が「1」の行は「分類未定」の目印として扱い、分類は空欄・仮登録扱いで保存する。
            $isUnclassified = $normalizedCategoryCode === '1';
            $categoryId = $isUnclassified ? null : $categoryIdsByCode->get($normalizedCategoryCode);

            if ($itemName === '') {
                $errors[] = "{$rowNumber}行目: 品名を入力してください。";
            }
            if (! $isUnclassified && ($categoryCode === '' || $categoryId === null)) {
                $errors[] = "{$rowNumber}行目: 分類コード「{$categoryCode}」が見つかりません。";
            }
            if ($orderQty === '' || ! is_numeric($this->normalizeNumber($orderQty))) {
                $errors[] = "{$rowNumber}行目: 数量を数値で入力してください。";
            }
            if ($unitPrice === '' || ! is_numeric($this->normalizeNumber($unitPrice))) {
                $errors[] = "{$rowNumber}行目: 単価を数値で入力してください。";
            }
            if ($supplierName === '') {
                $errors[] = "{$rowNumber}行目: 商社名を入力してください。";
            }

            $rows[] = [
                'item_code' => $itemCode,
                'machine_no' => $machineNo !== '' ? $machineNo : null,
                'category_id' => $categoryId,
                'category_display' => $isUnclassified ? '(分類未定・仮登録)' : ($categoryLabelsById[$categoryId] ?? ''),
                'manufacturer' => $manufacturer !== '' ? $manufacturer : null,
                'item_name' => $itemName,
                'dimensions' => $dimensions !== '' ? $dimensions : null,
                'order_qty' => is_numeric($this->normalizeNumber($orderQty)) ? $this->normalizeNumber($orderQty) : null,
                'unit_price' => is_numeric($this->normalizeNumber($unitPrice)) ? $this->normalizeNumber($unitPrice) : null,
                'supplier_name' => $supplierName !== '' ? $supplierName : null,
                'order_date' => $orderDate,
                'is_provisional' => $isUnclassified,
            ];
        }

        if (count($errors) > 0) {
            return back()->withInput()->withErrors(['paste_data' => $errors]);
        }

        if (! $request->boolean('confirmed')) {
            return view('purchasing.bulk-paste-confirm', [
                'rows' => $rows,
                'itemCode' => $itemCode,
                'orderDateRaw' => (string) $request->input('order_date'),
                'pasteData' => $request->input('paste_data'),
            ]);
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                PurchaseDetail::create($row);
            }
        });

        return redirect()->route('purchasing.input')
            ->with('status', 'bulk-paste-created')
            ->with('bulk_paste_count', count($rows))
            ->with('bulk_paste_item_code', $itemCode);
    }

    /**
     * エクセルの日報(タブ区切り)をコピー&ペーストして社内人工データを一括登録する。
     * 列順は固定: 年月日, SID, 注番, 機械装置No, 分類コード, 時間, 分, 補足, 時間外。
     * 年月日は「2026/7/30」のように年を含む形式で入力する。SIDは担当者管理で割り当てた番号
     * (staff.sid)で担当者を特定する。
     *
     * confirmedが未送信の場合は登録を実行せず、内容確認画面を表示する(storeBulkPasteと同様)。
     */
    public function storeLaborBulkPaste(Request $request): RedirectResponse|View
    {
        $request->validate([
            'labor_paste_data' => ['required', 'string'],
        ]);

        $lines = preg_split('/\r\n|\r|\n/', trim($request->string('labor_paste_data')->value()));
        $lines = array_values(array_filter($lines, fn ($line) => trim($line) !== ''));

        // エクセルから見出し行ごと選択・コピーされるケースを考慮し、1列目が「年月日」の行は見出しとして読み飛ばす。
        if (isset($lines[0]) && trim(explode("\t", $lines[0])[0]) === '年月日') {
            array_shift($lines);
        }

        if (count($lines) === 0) {
            return back()->withInput()->withErrors(['labor_paste_data' => '貼り付けるデータがありません。']);
        }

        if (count($lines) > self::BULK_PASTE_MAX_ROWS) {
            return back()->withInput()->withErrors([
                'labor_paste_data' => '一度に貼り付けできるのは'.self::BULK_PASTE_MAX_ROWS.'行までです。('.count($lines).'行貼り付けられています)',
            ]);
        }

        [$categoryIdsByCode, $categoryLabelsById] = $this->categoryLookupMaps();
        $staffBySid = Staff::whereNotNull('sid')->get()->keyBy('sid');

        $rows = [];
        $errors = [];

        foreach ($lines as $index => $line) {
            $rowNumber = $index + 1;
            $columns = array_pad(explode("\t", $line), 9, '');
            [$dateText, $sidText, $orderNo, $machineNo, $categoryCodeText, $hoursText, $minutesText, $note, $overtimeText]
                = array_map(fn ($value) => trim($value), array_slice($columns, 0, 9));

            $workDate = $dateText !== '' ? $this->parseLaborDate($dateText) : null;
            if ($dateText === '') {
                $errors[] = "{$rowNumber}行目: 年月日を入力してください。";
            } elseif ($workDate === null) {
                $errors[] = "{$rowNumber}行目: 年月日を解釈できません(「{$dateText}」)。";
            }

            $normalizedSid = $this->normalizeNumber($sidText);
            $staff = ($normalizedSid !== '' && is_numeric($normalizedSid)) ? $staffBySid->get((int) $normalizedSid) : null;
            if ($sidText === '') {
                $errors[] = "{$rowNumber}行目: SIDを入力してください。";
            } elseif ($staff === null) {
                $errors[] = "{$rowNumber}行目: SID「{$sidText}」に該当する担当者が見つかりません。";
            }

            $normalizedCategoryCode = $this->normalizeNumber($categoryCodeText);
            $categoryId = $categoryIdsByCode->get($normalizedCategoryCode);
            if ($categoryCodeText === '' || $categoryId === null) {
                $errors[] = "{$rowNumber}行目: 分類コード「{$categoryCodeText}」が見つかりません。";
            }

            $normalizedHours = $this->normalizeNumber($hoursText);
            if ($normalizedHours === '') {
                $errors[] = "{$rowNumber}行目: 時間を入力してください。";
            } elseif (! is_numeric($normalizedHours)) {
                $errors[] = "{$rowNumber}行目: 時間を数値で入力してください。";
            }
            $normalizedMinutes = $this->normalizeNumber($minutesText);
            if ($normalizedMinutes === '') {
                $errors[] = "{$rowNumber}行目: 分を入力してください。";
            } elseif (! is_numeric($normalizedMinutes)) {
                $errors[] = "{$rowNumber}行目: 分を数値で入力してください。";
            }

            $rows[] = [
                'work_date' => $workDate,
                'staff_id' => $staff?->id,
                'staff_name' => $staff?->name,
                'sid_display' => $sidText,
                'order_no' => $orderNo !== '' ? $orderNo : null,
                'machine_no' => $machineNo !== '' ? $machineNo : null,
                'category_id' => $categoryId,
                'category_display' => $categoryLabelsById[$categoryId] ?? '',
                'work_hours' => is_numeric($normalizedHours) ? (int) $normalizedHours : 0,
                'work_minutes' => is_numeric($normalizedMinutes) ? (int) $normalizedMinutes : 0,
                'is_overtime' => in_array(strtoupper($overtimeText), ['TRUE', '1'], true),
                'position_weight_cache' => $staff?->position_weight,
                'note' => $note !== '' ? $note : null,
                'is_provisional' => false,
            ];
        }

        if (count($errors) > 0) {
            return back()->withInput()->withErrors(['labor_paste_data' => $errors]);
        }

        if (! $request->boolean('confirmed')) {
            return view('purchasing.labor-bulk-paste-confirm', [
                'rows' => $rows,
                'pasteData' => $request->input('labor_paste_data'),
            ]);
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                LaborCost::create($row);
            }
        });

        return redirect()->route('purchasing.input')
            ->with('status', 'labor-bulk-paste-created')
            ->with('bulk_paste_count', count($rows));
    }

    /**
     * @return array{0: Collection<int|string, int>, 1: Collection<int, string>}
     */
    private function categoryLookupMaps(): array
    {
        $categories = CategoryCode::all();
        $categoryIdsByCode = $categories->pluck('id', 'code');
        $categoryLabelsById = $categories->mapWithKeys(
            fn ($c) => [$c->id => $c->code.':'.$c->major_category.($c->sub_category ? '／'.$c->sub_category : '')]
        );

        return [$categoryIdsByCode, $categoryLabelsById];
    }

    /**
     * 「2026/7/30」のような年を含む日付表示をYYYY-MM-DDへ変換する。解釈できなければnullを返す。
     */
    private function parseLaborDate(string $text): ?string
    {
        $normalized = str_replace('／', '/', $this->normalizeNumber($text));
        $timestamp = strtotime($normalized);

        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }

    /**
     * エクセルからの貼り付けで混ざりがちな全角数字・カンマ・円記号を除去し、半角数値の文字列に揃える。
     */
    private function normalizeNumber(string $value): string
    {
        $value = trim($value);
        $value = str_replace(['０', '１', '２', '３', '４', '５', '６', '７', '８', '９'], ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'], $value);

        return preg_replace('/[¥,\s]/u', '', $value);
    }
}
