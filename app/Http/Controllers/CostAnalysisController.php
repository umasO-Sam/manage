<?php

namespace App\Http\Controllers;

use App\Models\PurchaseDetail;
use Illuminate\Http\Request;
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

        if ($orderNo === '') {
            return view('purchasing.cost.index', ['orderNo' => '', 'result' => null]);
        }

        $rows = DB::table('purchase_details as p')
            ->leftJoin('category_codes as c', 'p.category_id', '=', 'c.id')
            ->where('p.item_code', $orderNo)
            ->where('p.is_provisional', false)
            ->selectRaw('c.code as category_code, c.major_category, c.sub_category, (p.order_qty * p.unit_price) as amount')
            ->get();

        $orderAmount = (float) (DB::table('purchase_details')
            ->where('item_code', $orderNo)
            ->where('is_provisional', false)
            ->max('order_amount') ?? 0);

        $items = [];
        $subtotal = 0.0;
        foreach (self::COST_ITEMS as $key => $def) {
            $amount = $this->costItemAmount($rows, $def['majors']);
            $items[$key] = ['label' => $def['label'], 'amount' => $amount];
            $subtotal += $amount;
        }

        // 旅費(コード61)は社内費の内訳として別枠表示するため、人工等は差分で求める(Excel側の内訳リストは
        // コード69・70が漏れているため、合計と内訳が一致するよう差分計算にしている)。
        $travelCost = $rows->filter(fn ($r) => (int) $r->category_code === 61)->sum('amount');
        $laborCost = $items['internal']['amount'] - $travelCost;

        $miscRatio = (int) floor(($subtotal * 0.05) / 100) * 100;
        $totalCost = $subtotal + $miscRatio;
        $profit = $orderAmount - $totalCost;

        // 「他/他」バケット(コード44,55,66,77,88,99): 注番に紐づけない雑多な費目として原価から除外し、警告表示する。
        $miscCategoryAmount = $rows->filter(fn ($r) => $r->major_category === '他')->sum('amount');
        // 分類コード自体が付いていない仕入行: 集計から漏れているため警告表示する。
        $unclassifiedAmount = $rows->filter(fn ($r) => $r->category_code === null)->sum('amount');

        $topParts = PurchaseDetail::query()
            ->where('item_code', $orderNo)
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
            'travel_cost' => (int) $travelCost,
            'misc_ratio' => $miscRatio,
            'subtotal' => (int) $subtotal,
            'misc_category_amount' => (int) $miscCategoryAmount,
            'unclassified_amount' => (int) $unclassifiedAmount,
            'top_parts' => $topParts,
        ];

        return view('purchasing.cost.index', compact('orderNo', 'result'));
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
}
