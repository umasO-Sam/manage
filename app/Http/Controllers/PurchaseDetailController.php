<?php

namespace App\Http\Controllers;

use App\Models\CategoryCode;
use App\Models\PurchaseDetail;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseDetailController extends Controller
{
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
        $filters['category_id'] = trim((string) $request->query('category_id', ''));

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

        if ($filters['category_id'] !== '') {
            $query->where('category_id', $filters['category_id']);
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
        ]);
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
