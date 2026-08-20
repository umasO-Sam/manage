<?php

namespace App\Http\Controllers;

use App\Models\BusinessOrderLog;
use App\Models\BusinessPartner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * 取引先一覧。銀行・取引区分・締め日・支払条件を扱うため資金管理者(とadministrator)限定。
 * 物件管理ボードのカード作成で仮登録された取引先がここに並び、
 * 4項目を入力して「取引条件調整完了」を押すと本登録になる。
 *
 * 受注先プルダウンの選択肢としては経理資材担当も読むが、この管理画面には入れない。
 *
 * Excelの「売上取引先一覧」で持っていた住所・TEL・処理方法などもこの画面で管理する。
 * 社数が多く1件ずつのフォームでは追えないため、表形式の直接編集と
 * タブ区切りの貼り付け一括登録を用意している(仕入管理のデータ入力と同じ操作感)。
 */
class BusinessPartnerController extends Controller
{
    /** 貼り付けで一度に登録できる行数。仕入管理のエクセル一括登録に合わせる。 */
    private const BULK_PASTE_MAX_ROWS = 200;

    /**
     * 貼り付け欄の列順。Excelの「売上取引先一覧」をそのままコピーできるよう
     * CSVと同じ並びにして、関連注番だけ末尾に足している。
     *
     * @var array<int, array{0: string, 1: string}> [列見出し, カラム名]
     */
    private const PASTE_COLUMNS = [
        ['50音', 'kana_group'],
        ['取引先', 'name'],
        ['郵便番号', 'postal_code'],
        ['住所', 'address'],
        ['TEL', 'tel'],
        ['FAX', 'fax'],
        ['処理方法', 'handling_method'],
        ['弥生補助科目', 'yayoi_sub_account'],
        ['集塵機の袋', 'dust_bag'],
        ['並び順', 'display_order'],
        ['備考', 'remarks'],
        ['下請法メモ', 'subcontract_note'],
        ['関連注番', 'related_order_nos'],
    ];

    public function index(): View
    {
        return view('business-partners.index', [
            'partners' => BusinessPartner::withCount('businessOrders')
                ->orderBy('is_provisional', 'desc')
                ->orderByRaw('display_order is null, display_order')
                ->orderBy('name')
                ->get(),
            'pasteColumns' => array_column(self::PASTE_COLUMNS, 0),
            'bulkPasteMaxRows' => self::BULK_PASTE_MAX_ROWS,
        ]);
    }

    public function update(Request $request, BusinessPartner $businessPartner): RedirectResponse
    {
        $data = $request->validate($this->fieldRules($businessPartner->id));

        $businessPartner->update($this->normalize($data));
        // 受注ヘッダ側の受注先名(表示用の文字列)も追随させる。
        $businessPartner->businessOrders()->update(['recipient' => $businessPartner->name]);

        return back()->with('status', 'partner-updated');
    }

    /**
     * 表形式の「直接編集」。1件ずつ保存すると社数ぶん往復するため、変更した行を
     * まとめて1回で保存する(担当者管理の直接編集と同じ作り)。
     * 1行でも問題があれば何も保存しない。
     */
    public function bulkUpdate(Request $request): RedirectResponse
    {
        /** @var array<int|string, array<string, mixed>> $updates */
        $updates = $request->input('updates', []);
        $errors = [];
        $saved = 0;

        DB::transaction(function () use ($updates, &$errors, &$saved) {
            foreach ($updates as $id => $fields) {
                $partner = BusinessPartner::find($id);

                if ($partner === null) {
                    continue;
                }

                $validator = Validator::make((array) $fields, $this->fieldRules($partner->id));

                if ($validator->fails()) {
                    foreach ($validator->errors()->all() as $message) {
                        $errors[] = "{$partner->name}: {$message}";
                    }

                    continue;
                }

                $partner->update($this->normalize($validator->validated()));
                $partner->businessOrders()->update(['recipient' => $partner->name]);
                $saved++;
            }

            if ($errors !== []) {
                DB::rollBack();
            }
        });

        if ($errors !== []) {
            return back()->withErrors($errors);
        }

        return back()->with('status', 'partners-bulk-updated')->with('partners_saved', $saved);
    }

    /**
     * タブ区切りテキストの貼り付けによる一括登録。confirmedが未送信のうちは内容確認画面を出し、
     * 「登録する」を押したときだけ保存する(仕入の一括登録と同じ)。
     * すでにある取引先は直接編集で直す前提で、ここでは新規行だけを受け付ける。
     */
    public function storeBulkPaste(Request $request): RedirectResponse|View
    {
        $request->validate(['paste_data' => ['required', 'string']]);

        $lines = preg_split('/\r\n|\r|\n/', trim($request->string('paste_data')->value()));
        $lines = array_values(array_filter($lines, fn ($line) => trim($line) !== ''));

        // Excelから見出し行ごとコピーされることがあるため、1列目が「50音」の行は読み飛ばす。
        if (isset($lines[0]) && trim(explode("\t", $lines[0])[0]) === '50音') {
            array_shift($lines);
        }

        if ($lines === []) {
            return back()->withInput()->withErrors(['paste_data' => '貼り付けるデータがありません。']);
        }

        if (count($lines) > self::BULK_PASTE_MAX_ROWS) {
            return back()->withInput()->withErrors([
                'paste_data' => '一度に貼り付けできるのは'.self::BULK_PASTE_MAX_ROWS.'行までです。('.count($lines).'行貼り付けられています)',
            ]);
        }

        $rows = [];
        $errors = [];
        $names = [];

        foreach ($lines as $index => $line) {
            $rowNumber = $index + 1;
            $columns = array_pad(explode("\t", $line), count(self::PASTE_COLUMNS), '');

            $row = [];
            foreach (self::PASTE_COLUMNS as $position => [$label, $field]) {
                $value = trim((string) $columns[$position]);
                $row[$field] = $value === '' ? null : $value;
            }

            if ($row['name'] === null) {
                $errors[] = "{$rowNumber}行目: 取引先を入力してください。";

                continue;
            }

            if ($row['display_order'] !== null && ! ctype_digit($row['display_order'])) {
                $errors[] = "{$rowNumber}行目: 並び順は数字で入力してください。";
            }

            if (in_array($row['name'], $names, true)) {
                $errors[] = "{$rowNumber}行目: 「{$row['name']}」が貼り付け内容の中で重複しています。";
            }

            if (BusinessPartner::where('name', $row['name'])->exists()) {
                $errors[] = "{$rowNumber}行目: 「{$row['name']}」はすでに登録されています。直接編集で修正してください。";
            }

            $names[] = $row['name'];
            $rows[] = $row;
        }

        if ($errors !== []) {
            return back()->withInput()->withErrors(['paste_data' => $errors]);
        }

        if (! $request->boolean('confirmed')) {
            return view('business-partners.bulk-paste-confirm', [
                'rows' => $rows,
                'columns' => self::PASTE_COLUMNS,
                'pasteData' => $request->input('paste_data'),
            ]);
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                // 一覧に足す取引先は取引実績のある既存先なので、取引条件調整中にはしない。
                BusinessPartner::create([...$row, 'is_provisional' => false]);
            }
        });

        return redirect()->route('business-partners.index')
            ->with('status', 'partners-bulk-created')
            ->with('partners_saved', count($rows));
    }

    /**
     * 取引条件調整完了。4項目がすべて埋まっているときだけ確定でき、確定すると
     * この取引先のカードから「取引条件調整中」バッジが一斉に消えて請求済へ進めるようになる。
     */
    public function confirm(BusinessPartner $businessPartner): RedirectResponse
    {
        if (! $businessPartner->hasAllTerms()) {
            return back()->withErrors(['confirm' => '銀行・取引区分・締め日・支払い条件をすべて入力してください。']);
        }

        DB::transaction(function () use ($businessPartner) {
            $businessPartner->update([
                'is_provisional' => false,
                'confirmed_at' => now(),
                'confirmed_by' => Auth::id(),
            ]);

            foreach ($businessPartner->businessOrders as $order) {
                BusinessOrderLog::record(
                    $order,
                    BusinessOrderLog::ACTION_TRADE_TERMS_CONFIRMED,
                    "{$businessPartner->name} の取引条件を確定"
                );
            }
        });

        return back()->with('status', 'partner-confirmed');
    }

    /**
     * 空欄で送られてきた項目はnullに寄せる(空文字のまま保存すると、
     * 「未入力」と「空文字」が混ざって取引条件の判定がぶれるため)。
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value) && trim($value) === '') {
                $data[$key] = null;
            }
        }

        return $data;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function fieldRules(int $ignoreId): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('business_partners', 'name')->ignore($ignoreId)],
            'kana_group' => ['nullable', 'string', 'max:10'],
            'bank' => ['nullable', 'string', 'max:255'],
            'transaction_type' => ['nullable', 'string', 'max:255'],
            'closing_day' => ['nullable', 'string', 'max:255'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:1000'],
            'address' => ['nullable', 'string', 'max:2000'],
            'tel' => ['nullable', 'string', 'max:1000'],
            'fax' => ['nullable', 'string', 'max:1000'],
            'handling_method' => ['nullable', 'string', 'max:5000'],
            'yayoi_sub_account' => ['nullable', 'string', 'max:255'],
            'dust_bag' => ['nullable', 'string', 'max:255'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'subcontract_note' => ['nullable', 'string', 'max:5000'],
            'related_order_nos' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
