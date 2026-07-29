<?php

namespace App\Http\Controllers;

use App\Models\LaborCost;
use App\Models\PurchaseDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CostAnalysisController extends Controller
{
    /**
     * 大分類ごとに直接集計する費目。キー => [表示名, 大分類名の配列]。
     * 社内人工/雑人工のみコード62(打合せ見積)を除外する特別ルールがある(costItemAmount参照)。
     *
     * @var array<string, array{label: string, majors: array<int, string>}>
     */
    /** 「人工等」小計に含める社内人工コード(機械製缶・人工・機械設計・現地・電気設計・電気製造・ソフト対応)。 */
    private const LABOR_CODES = [59, 60, 63, 64, 65, 67, 68];

    private const COST_ITEMS = [
        'material' => ['label' => '材料費', 'majors' => ['材料']],
        'outsourcing' => ['label' => '外注費', 'majors' => ['外注']],
        'parts' => ['label' => '部品費', 'majors' => ['部品']],
        'electrical' => ['label' => '電装費', 'majors' => ['電機']],
        'shipping' => ['label' => '運送費', 'majors' => ['運賃']],
        'lease' => ['label' => 'リース費', 'majors' => ['リース']],
        'internal' => ['label' => '社内費', 'majors' => ['社内人工', '雑人工']],
    ];

    /**
     * 注番別の原価分析。ユーザーが実運用で使っている「仕入リスト - 原価計算表」Excelの
     * 集計ロジック(大分類ごとの直接集計 + 5%比率雑費 + 簡易収支)をそのまま踏襲する。
     */
    public function index(Request $request): View
    {
        $orderNo = trim((string) $request->query('order_no', ''));
        // 従来は完全一致のみだったため、後方互換のためデフォルトは完全一致のままとする。
        $orderNoMatch = $request->query('order_no_match') === 'partial' ? 'partial' : 'perfect';
        $excludedOrderNos = array_values(array_filter((array) $request->query('excluded_order_nos', [])));

        $emptyState = [
            'orderNo' => $orderNo,
            'orderNoMatch' => $orderNoMatch,
            'matchedOrderNos' => collect(),
            'excludedOrderNos' => $excludedOrderNos,
            'includedOrderNos' => collect(),
            'result' => null,
        ];

        if ($orderNo === '') {
            return view('purchasing.cost.index', $emptyState);
        }

        $applyOrderNoFilter = function ($query, string $column) use ($orderNo, $orderNoMatch) {
            return $orderNoMatch === 'perfect'
                ? $query->where($column, $orderNo)
                : $query->where($column, 'like', "%{$orderNo}%");
        };

        $purchaseOrderNos = $applyOrderNoFilter(PurchaseDetail::query(), 'item_code')
            ->where('is_provisional', false)
            ->whereNotNull('item_code')
            ->distinct()
            ->pluck('item_code');

        $laborOrderNos = $applyOrderNoFilter(LaborCost::query(), 'order_no')
            ->where('is_provisional', false)
            ->whereNotNull('order_no')
            ->distinct()
            ->pluck('order_no');

        $matchedOrderNos = $purchaseOrderNos->merge($laborOrderNos)->unique()->sort()->values();
        $includedOrderNos = $matchedOrderNos->diff($excludedOrderNos)->values();

        if ($includedOrderNos->isEmpty()) {
            return view('purchasing.cost.index', [
                ...$emptyState,
                'matchedOrderNos' => $matchedOrderNos,
                'includedOrderNos' => $includedOrderNos,
            ]);
        }

        $purchaseRows = DB::table('purchase_details as p')
            ->leftJoin('category_codes as c', 'p.category_id', '=', 'c.id')
            ->whereIn('p.item_code', $includedOrderNos)
            ->where('p.is_provisional', false)
            ->selectRaw('c.code as category_code, c.major_category, c.sub_category, (p.order_qty * p.unit_price) as amount')
            ->get();

        // 人工計算画面(labor_costs)で記録される時間ベースの労務費も、旧仕入リストに直接手入力されていた
        // 「人工」明細行と同じ分類ルールで原価計算に合算する(そうしないと新規注番で人工が一切集計されない)。
        $laborRows = DB::table('labor_costs as l')
            ->leftJoin('category_codes as c', 'l.category_id', '=', 'c.id')
            ->whereIn('l.order_no', $includedOrderNos)
            ->where('l.is_provisional', false)
            ->get()
            ->map(function ($l) {
                $totalMinutes = ((int) $l->work_hours * 60) + (int) $l->work_minutes;
                $hourlyRate = $l->is_overtime ? 50000 : 40000;
                $weight = (float) $l->position_weight_cache;
                $multiplier = $weight > 0 ? $weight : 1.0;
                $amount = round(($totalMinutes / 480) * $hourlyRate * $multiplier);

                return (object) [
                    'category_code' => $l->code,
                    'major_category' => $l->major_category,
                    'sub_category' => $l->sub_category,
                    'amount' => $amount,
                ];
            });

        $rows = $purchaseRows->concat($laborRows);

        // 対象が複数の注番にまたがる場合、受注金額は各注番ごとの最大値(同一注番内の重複記載を除くため)
        // を合算する(単一注番のみの場合はmax()と同じ結果になり、従来通りの挙動を維持する)。
        $orderAmount = (float) DB::table('purchase_details')
            ->select('item_code', DB::raw('MAX(order_amount) as max_amount'))
            ->whereIn('item_code', $includedOrderNos)
            ->where('is_provisional', false)
            ->groupBy('item_code')
            ->get()
            ->sum('max_amount');

        $items = [];
        $subtotal = 0.0;
        foreach (self::COST_ITEMS as $key => $def) {
            $amount = $this->costItemAmount($rows, $def['majors']);
            $items[$key] = ['label' => $def['label'], 'amount' => $amount];
            $subtotal += $amount;
        }

        $travelCost = $rows->filter(fn ($r) => (int) $r->category_code === 61)->sum('amount');
        $laborCost = $rows->filter(fn ($r) => in_array((int) $r->category_code, self::LABOR_CODES, true))->sum('amount');
        $laborBreakdown = $this->laborBreakdown($rows);

        $miscRatio = (int) floor(($subtotal * 0.05) / 100) * 100;
        $totalCost = $subtotal + $miscRatio;
        $profit = $orderAmount - $totalCost;

        // 「他/他」バケット(コード44,55,66,77,88,99): 注番に紐づけない雑多な費目として原価から除外し、警告表示する。
        $miscCategoryAmount = $rows->filter(fn ($r) => $r->major_category === '他')->sum('amount');
        // 分類コード自体が付いていない仕入行: 集計から漏れているため警告表示する。
        $unclassifiedAmount = $rows->filter(fn ($r) => $r->category_code === null)->sum('amount');

        $topParts = PurchaseDetail::query()
            ->whereIn('item_code', $includedOrderNos)
            ->where('is_provisional', false)
            ->get()
            ->sortByDesc(fn (PurchaseDetail $d) => $d->lineTotal())
            ->take(5)
            ->map(fn (PurchaseDetail $d) => [
                'supplier_name' => $d->supplier_name,
                'item_name' => $d->item_name,
                'line_total' => $d->lineTotal(),
            ])
            ->values();

        $result = [
            'summary' => [
                'order_amount' => (int) $orderAmount,
                'total_cost' => (int) $totalCost,
                'gross_profit' => (int) $profit,
                'profit_margin' => $orderAmount > 0 ? round(($profit / $orderAmount) * 100, 1) : null,
            ],
            'items' => $items,
            'labor_cost' => (int) $laborCost,
            'labor_breakdown' => $laborBreakdown,
            'travel_cost' => (int) $travelCost,
            'misc_ratio' => $miscRatio,
            'subtotal' => (int) $subtotal,
            'misc_category_amount' => (int) $miscCategoryAmount,
            'unclassified_amount' => (int) $unclassifiedAmount,
            'top_parts' => $topParts,
        ];

        return view('purchasing.cost.index', [
            'orderNo' => $orderNo,
            'orderNoMatch' => $orderNoMatch,
            'matchedOrderNos' => $matchedOrderNos,
            'excludedOrderNos' => $excludedOrderNos,
            'includedOrderNos' => $includedOrderNos,
            'result' => $result,
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @param  array<int, string>  $majors
     */
    private function costItemAmount($rows, array $majors): float
    {
        return (float) $rows
            ->filter(function ($r) use ($majors) {
                if (! in_array($r->major_category, $majors, true)) {
                    return false;
                }

                // 社内人工のコード62(打合せ見積)は原価計算表Excelの集計ルールに合わせて除外する。
                if (in_array($r->major_category, ['社内人工', '雑人工'], true) && (int) $r->category_code === 62) {
                    return false;
                }

                return true;
            })
            ->sum('amount');
    }

    /**
     * 「人工等」小計(LABOR_CODES)を、分類コードの細分(sub_category)ごとに分けて集計する。
     * コード60は「人工」「機械組付」の2つの細分が同じコード値を共有しているため、
     * コードだけでなくsub_category名も合わせてグルーピングキーにする。
     *
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @return \Illuminate\Support\Collection<int, array{code: int, label: string, amount: float}>
     */
    private function laborBreakdown($rows)
    {
        return $rows
            ->filter(fn ($r) => in_array((int) $r->category_code, self::LABOR_CODES, true))
            ->groupBy(fn ($r) => $r->category_code.'|'.($r->sub_category ?? '未分類'))
            ->map(fn ($group) => [
                'code' => (int) $group->first()->category_code,
                'label' => $group->first()->sub_category ?? '未分類',
                'amount' => $group->sum('amount'),
            ])
            ->sortBy(['code', 'label'])
            ->values();
    }
}
