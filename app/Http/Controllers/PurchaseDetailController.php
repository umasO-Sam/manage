<?php

namespace App\Http\Controllers;

use App\Models\BusinessOrder;
use App\Models\CategoryCode;
use App\Models\PurchaseDetail;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class PurchaseDetailController extends Controller
{
    /**
     * @var array<string, array<int, string>> 検索画面の「直接編集」で1行ずつ更新可能な項目とバリデーションルール。
     *                                        単一レコード編集(update)と異なり、一部項目だけを変更する運用のため「必須」は課さない
     *                                        (項目自体には既存値が入っているため、空欄で送信された場合のみ意図的にクリアされる)。
     *                                        ただしitem_code(注番)はレコードの識別に使われるため必須のままにする。
     */
    private const BULK_EDITABLE_FIELDS = [
        'item_code' => ['required', 'string', 'max:255'],
        'machine_no' => ['nullable', 'string', 'max:255'],
        'product_name' => ['nullable', 'string', 'max:255'],
        'category_id' => ['nullable', 'integer', 'exists:category_codes,id'],
        'manufacturer' => ['nullable', 'string', 'max:255'],
        'item_name' => ['nullable', 'string', 'max:255'],
        'dimensions' => ['nullable', 'string', 'max:255'],
        'remarks' => ['nullable', 'string'],
        'required_qty' => ['nullable', 'numeric'],
        'usage_purpose' => ['nullable', 'string', 'max:255'],
        'order_qty' => ['nullable', 'numeric'],
        'unit' => ['nullable', 'string', 'max:50', 'regex:/^[^\d０-９]+$/u'],
        'unit_price' => ['nullable', 'numeric'],
        'stock_qty' => ['nullable', 'numeric'],
        'supplier_name' => ['nullable', 'string', 'max:255'],
        'order_date' => ['nullable', 'date'],
        'arrival_date' => ['nullable', 'date'],
        'invoice_date' => ['nullable', 'date'],
        'recipient' => ['nullable', 'string', 'max:255'],
        'order_received_date' => ['nullable', 'date'],
        'delivery_dest' => ['nullable', 'string', 'max:255'],
        'order_amount' => ['nullable', 'numeric'],
        'sales_date' => ['nullable', 'date'],
        'supplier_invoice_no' => ['nullable', 'string', 'max:255'],
    ];

    /**
     * @var array<string, string> クエリ文字列のキー => purchase_details のカラム名
     */
    private const SEARCH_FIELDS = [
        'item_code' => 'item_code',
        'machine_no' => 'machine_no',
        'product_name' => 'product_name',
        'dimensions' => 'dimensions',
        'item_name' => 'item_name',
        'manufacturer' => 'manufacturer',
        'supplier_name' => 'supplier_name',
    ];

    /**
     * @var array<string, string> クエリ文字列のキー => purchase_details の日付カラム名
     */
    private const DATE_FIELDS = [
        'order_date' => '注文日',
        'arrival_date' => '受入日',
        'invoice_date' => '納品書日',
        'order_received_date' => '受注日',
        'sales_date' => '売上日',
    ];

    /**
     * 仕入管理データの検索（dbtestシステムからの移植・第一弾）。
     * 資材部門以外の全スタッフが閲覧できる想定のため、認可チェックは行わない。
     */
    public function index(Request $request): View
    {
        $filters = [];
        foreach (self::SEARCH_FIELDS as $key => $column) {
            $filters[$key] = trim((string) $request->query($key, ''));
            $filters["{$key}_match"] = $request->query("{$key}_match") === 'perfect' ? 'perfect' : 'partial';
        }
        $alphas = array_values(array_filter((array) $request->query('alpha', [])));
        $filters['alpha'] = $alphas;
        $filters['category_id'] = array_values(array_filter((array) $request->query('category_id', [])));
        $provisional = $request->query('provisional', '');
        $filters['provisional'] = in_array($provisional, ['1', '0'], true) ? $provisional : '';

        foreach (self::DATE_FIELDS as $key => $label) {
            $mode = $request->query("{$key}_mode", '');
            $filters["{$key}_mode"] = in_array($mode, ['exact', 'before', 'after', 'range'], true) ? $mode : '';
            $filters["{$key}_from"] = trim((string) $request->query("{$key}_from", ''));
            $filters["{$key}_to"] = trim((string) $request->query("{$key}_to", ''));
        }

        $query = PurchaseDetail::query();

        foreach (self::SEARCH_FIELDS as $key => $column) {
            $value = $filters[$key];
            if ($value === '') {
                continue;
            }

            if ($filters["{$key}_match"] === 'perfect') {
                $query->where($column, $value);
            } else {
                $query->where($column, 'like', "%{$value}%");
            }
        }

        if (! empty($filters['category_id'])) {
            $query->whereIn('category_id', $filters['category_id']);
        }

        if ($filters['provisional'] !== '') {
            $query->where('is_provisional', $filters['provisional'] === '1');
        }

        foreach (self::DATE_FIELDS as $key => $label) {
            $mode = $filters["{$key}_mode"];
            $from = $filters["{$key}_from"];
            $to = $filters["{$key}_to"];

            if ($mode === 'exact' && $from !== '') {
                $query->whereDate($key, $from);
            } elseif ($mode === 'before' && $from !== '') {
                $query->whereDate($key, '<=', $from);
            } elseif ($mode === 'after' && $from !== '') {
                $query->whereDate($key, '>=', $from);
            } elseif ($mode === 'range' && ($from !== '' || $to !== '')) {
                if ($from !== '') {
                    $query->whereDate($key, '>=', $from);
                }
                if ($to !== '') {
                    $query->whereDate($key, '<=', $to);
                }
            }
        }

        $this->applyAlphaFilter($query, $alphas);

        $details = $query
            ->with('category')
            ->orderByRaw("(CASE WHEN (recipient IS NOT NULL AND recipient <> '') OR order_received_date IS NOT NULL OR order_amount > 0 THEN 0 ELSE 1 END)")
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $categories = CategoryCode::orderBy('code')->get();

        return view('purchasing.index', [
            'details' => $details,
            'filters' => $filters,
            'categories' => $categories,
            'showProjects' => $this->shouldShowProjects($request, $filters),
            'projectOrders' => $this->shouldShowProjects($request, $filters)
                ? $this->searchProjectOrders($filters)
                : collect(),
        ]);
    }

    /**
     * 受注ヘッダ(物件)を検索結果に並べるかどうか。既定では出さず、「物件表示」ボタンを
     * 押したとき、または受注日・売上日で絞り込んだときに出す
     * (この2つは明細ではなく受注ヘッダが持つ情報を探している操作のため)。
     *
     * @param  array<string, mixed>  $filters
     */
    private function shouldShowProjects(Request $request, array $filters): bool
    {
        if ($request->boolean('show_projects')) {
            return true;
        }

        foreach (['order_received_date', 'sales_date'] as $key) {
            if ($filters["{$key}_mode"] !== '' && ($filters["{$key}_from"] !== '' || $filters["{$key}_to"] !== '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * 明細と同じ絞り込み条件のうち、受注ヘッダが持つ項目(注番・件名・受注先・受注日・売上日)
     * だけを適用して物件を探す。
     *
     * @param  array<string, mixed>  $filters
     * @return \Illuminate\Support\Collection<int, BusinessOrder>
     */
    private function searchProjectOrders(array $filters)
    {
        $query = BusinessOrder::query()->with(['businessPartner', 'staff', 'card' => fn ($q) => $q->withTrashed()]);

        // 注番・製品名は明細側と同じキーで絞り込む(受注ヘッダ側の列名に読み替える)。
        foreach (['item_code' => 'order_no', 'product_name' => 'product_name'] as $filterKey => $column) {
            $value = $filters[$filterKey];
            if ($value === '') {
                continue;
            }

            $filters["{$filterKey}_match"] === 'perfect'
                ? $query->where($column, $value)
                : $query->where($column, 'like', "%{$value}%");
        }

        foreach (['order_received_date', 'sales_date'] as $key) {
            $mode = $filters["{$key}_mode"];
            $from = $filters["{$key}_from"];
            $to = $filters["{$key}_to"];

            if ($mode === 'exact' && $from !== '') {
                $query->whereDate($key, $from);
            } elseif ($mode === 'before' && $from !== '') {
                $query->whereDate($key, '<=', $from);
            } elseif ($mode === 'after' && $from !== '') {
                $query->whereDate($key, '>=', $from);
            } elseif ($mode === 'range' && ($from !== '' || $to !== '')) {
                if ($from !== '') {
                    $query->whereDate($key, '>=', $from);
                }
                if ($to !== '') {
                    $query->whereDate($key, '<=', $to);
                }
            }
        }

        return $query->orderByDesc('order_received_date')->orderByDesc('id')->limit(200)->get();
    }

    /**
     * 仕入管理データの編集(経理資材担当のみ、procurement.managerミドルウェアで制御)。
     * 検索画面の絞り込み条件付きURLから遷移してきた場合、更新後にその条件へ戻れるよう
     * クエリ文字列をそのままフォームに持ち回す。
     */
    public function edit(Request $request, PurchaseDetail $purchaseDetail): View
    {
        return view('purchasing.edit', [
            'detail' => $purchaseDetail,
            'categories' => CategoryCode::orderBy('code')->get(),
            'returnQuery' => (string) $request->query('return_query', ''),
        ]);
    }

    public function destroy(Request $request, PurchaseDetail $purchaseDetail): RedirectResponse
    {
        $purchaseDetail->delete();

        $returnQuery = (string) $request->input('return_query', '');
        $redirectUrl = route('purchasing.index').($returnQuery !== '' ? "?{$returnQuery}" : '');

        return redirect()->to($redirectUrl)->with('status', 'delete-success');
    }

    /**
     * 検索画面の「まとめて削除」から、チェックボックスで選択した複数レコードを削除する。
     * 削除確認は画面側(削除実行ボタン押下時の確認モーダル)で行う前提のため、ここでは実行のみ。
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $ids = array_values(array_filter((array) $request->input('ids', []), fn ($id) => is_numeric($id)));
        $count = PurchaseDetail::whereIn('id', $ids)->count();
        PurchaseDetail::whereIn('id', $ids)->delete();

        $returnQuery = (string) $request->input('return_query', '');
        $redirectUrl = route('purchasing.index').($returnQuery !== '' ? "?{$returnQuery}" : '');

        return redirect()->to($redirectUrl)->with('status', 'bulk-delete-success')->with('bulk_delete_count', $count);
    }

    public function update(Request $request, PurchaseDetail $purchaseDetail): RedirectResponse
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

        // 受注項目(受注先・受注日・納入先・受注金額・売上日・製品名)は受注ヘッダが正。
        // 明細にも従来どおり保存しつつ、変更分をヘッダへ反映する。
        BusinessOrder::syncChangedFromDetail($purchaseDetail, $data);

        $purchaseDetail->update($data);

        $returnQuery = (string) $request->input('return_query', '');
        $redirectUrl = route('purchasing.index').($returnQuery !== '' ? "?{$returnQuery}" : '');

        return redirect()->to($redirectUrl)->with('status', 'update-success');
    }

    /**
     * 検索画面の「直接編集」から、表示中の複数レコードをまとめて更新する。
     * 画面側で変更点の確認を済ませた上で送信される想定のため、ここでは
     * 型のバリデーションのみ行い、1件ずつ更新する。
     */
    /**
     * @var array<string, string> 「直接編集」の保存後にどの画面へ戻るか(return_to)のホワイトリスト。
     *                            任意のURLへリダイレクトできてしまわないよう、ルート名を直接受け取らずキー経由でのみ許可する。
     */
    private const RETURN_ROUTES = [
        'search' => 'purchasing.index',
        'orders' => 'purchasing.orders.index',
        'invoices' => 'purchasing.invoices.index',
    ];

    public function bulkUpdate(Request $request): RedirectResponse
    {
        $updates = (array) $request->input('updates', []);

        DB::transaction(function () use ($updates, $request) {
            foreach ($updates as $id => $fields) {
                $purchaseDetail = PurchaseDetail::find($id);
                if (! $purchaseDetail) {
                    continue;
                }

                $validated = Validator::make((array) $fields, self::BULK_EDITABLE_FIELDS, [
                    'unit.regex' => '単位に数字は使用できません。',
                ])->validate();
                $validated['is_provisional'] = $request->boolean("updates.{$id}.is_provisional");

                BusinessOrder::syncChangedFromDetail($purchaseDetail, $validated);

                $purchaseDetail->update($validated);
            }
        });

        $returnRouteName = self::RETURN_ROUTES[$request->input('return_to')] ?? self::RETURN_ROUTES['search'];
        $returnQuery = (string) $request->input('return_query', '');
        $redirectUrl = route($returnRouteName).($returnQuery !== '' ? "?{$returnQuery}" : '');

        return redirect()->to($redirectUrl)->with('status', 'bulk-update-success');
    }

    /**
     * @param  Builder<PurchaseDetail>  $query
     * @param  array<int, string>  $alphas
     */
    private function applyAlphaFilter(Builder $query, array $alphas): void
    {
        if (empty($alphas) || in_array('ALL', $alphas, true)) {
            return;
        }

        // MySQL(本番)・SQLite(ローカル開発)の両方で動く書き方に限定するため、
        // REGEXP・CHAR_LENGTH等のMySQL専用関数は使わずSUBSTR+BETWEENで代替する。
        $query->where(function (Builder $q) use ($alphas) {
            foreach ($alphas as $alpha) {
                if ($alpha === 'ERR') {
                    // 異常データ: 注番が空、または先頭が半角英字(A-Z/a-z)でないもの
                    $q->orWhereNull('item_code')
                        ->orWhere('item_code', '')
                        ->orWhere(function (Builder $err) {
                            $err->whereRaw("NOT (SUBSTR(item_code, 1, 1) BETWEEN 'A' AND 'Z')")
                                ->whereRaw("NOT (SUBSTR(item_code, 1, 1) BETWEEN 'a' AND 'z')");
                        });
                } elseif (preg_match('/^[A-Z]$/', $alpha)) {
                    $q->orWhere('item_code', 'like', "{$alpha}%");
                }
            }
        });
    }
}
