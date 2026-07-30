<?php

namespace App\Http\Controllers;

use App\Models\LaborCost;
use App\Models\PurchaseDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        return view('purchasing.cost.index', $this->analyze($request));
    }

    /**
     * CSV出力(原価計算結果・集計対象の仕入レコード・人工データを1ファイルにまとめる)。
     * 画面表示と同じクエリパラメータ(注番・完全/部分一致・除外注番・締め月)をそのまま使うことで、
     * 画面に表示されている集計内容と出力内容を一致させる。
     */
    public function export(Request $request): StreamedResponse
    {
        $analysis = $this->analyze($request);
        $fileName = 'cost_analysis_'.($analysis['orderNo'] !== '' ? $analysis['orderNo'].'_' : '').now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($analysis) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF"); // Excelでの文字化け防止のUTF-8 BOM

            $this->writeAnalysisSection($stream, $analysis);
            $this->writePurchaseSection($stream, $analysis);
            $this->writeLaborSection($stream, $analysis);

            fclose($stream);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array{orderNo: string, orderNoMatch: string, matchedOrderNos: Collection<int, string>, excludedOrderNos: array<int, string>, includedOrderNos: Collection<int, string>, cutoffMonth: string, cutoffDate: ?string, result: ?array<string, mixed>}
     */
    private function analyze(Request $request): array
    {
        $orderNo = trim((string) $request->query('order_no', ''));
        // 従来は完全一致のみだったため、後方互換のためデフォルトは完全一致のままとする。
        $orderNoMatch = $request->query('order_no_match') === 'partial' ? 'partial' : 'perfect';
        $excludedOrderNos = array_values(array_filter((array) $request->query('excluded_order_nos', [])));

        // 「M月末まで」の仕掛計上締め。<input type="month">からの"YYYY-MM"を月末日に変換する。
        // 仕入・外注等は受入日、社内人工は作業日を基準に、この日付以前の分だけを集計する
        // (未受入・未作業のためまだコストが発生していない行は自動的に除外される)。
        $cutoffMonth = trim((string) $request->query('cutoff_month', ''));
        $cutoffDate = null;
        $cutoffDateEnd = null;
        if ($cutoffMonth !== '') {
            try {
                $cutoffDate = Carbon::createFromFormat('Y-m', $cutoffMonth)->endOfMonth()->toDateString();
                // arrival_date/work_dateはEloquentのdateキャスト経由だと"YYYY-MM-DD 00:00:00"形式で
                // 保存されるため、純粋な日付文字列との<=比較だと締め日当日ちょうどの行が文字列比較上
                // 弾かれてしまう。時刻部分まで含めた上限にして両方の保存形式を正しく含める。
                $cutoffDateEnd = $cutoffDate.' 23:59:59';
            } catch (\Exception) {
                $cutoffMonth = '';
            }
        }

        $emptyState = [
            'orderNo' => $orderNo,
            'orderNoMatch' => $orderNoMatch,
            'matchedOrderNos' => collect(),
            'excludedOrderNos' => $excludedOrderNos,
            'includedOrderNos' => collect(),
            'cutoffMonth' => $cutoffMonth,
            'cutoffDate' => $cutoffDate,
            'result' => null,
        ];

        if ($orderNo === '') {
            return $emptyState;
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
            return [
                ...$emptyState,
                'matchedOrderNos' => $matchedOrderNos,
                'includedOrderNos' => $includedOrderNos,
            ];
        }

        $purchaseRows = DB::table('purchase_details as p')
            ->leftJoin('category_codes as c', 'p.category_id', '=', 'c.id')
            ->whereIn('p.item_code', $includedOrderNos)
            ->where('p.is_provisional', false)
            ->when($cutoffDate !== null, fn ($q) => $q->whereNotNull('p.arrival_date')->where('p.arrival_date', '<=', $cutoffDateEnd))
            ->selectRaw('c.code as category_code, c.major_category, c.sub_category, (p.order_qty * p.unit_price) as amount')
            ->get();

        // 人工計算画面(labor_costs)で記録される時間ベースの労務費も、旧仕入リストに直接手入力されていた
        // 「人工」明細行と同じ分類ルールで原価計算に合算する(そうしないと新規注番で人工が一切集計されない)。
        $laborRows = DB::table('labor_costs as l')
            ->leftJoin('category_codes as c', 'l.category_id', '=', 'c.id')
            ->whereIn('l.order_no', $includedOrderNos)
            ->where('l.is_provisional', false)
            ->when($cutoffDate !== null, fn ($q) => $q->whereNotNull('l.work_date')->where('l.work_date', '<=', $cutoffDateEnd))
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
            ->when($cutoffDate !== null, fn ($q) => $q->whereNotNull('arrival_date')->where('arrival_date', '<=', $cutoffDateEnd))
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

        return [
            'orderNo' => $orderNo,
            'orderNoMatch' => $orderNoMatch,
            'matchedOrderNos' => $matchedOrderNos,
            'excludedOrderNos' => $excludedOrderNos,
            'includedOrderNos' => $includedOrderNos,
            'cutoffMonth' => $cutoffMonth,
            'cutoffDate' => $cutoffDate,
            'result' => $result,
        ];
    }

    /**
     * @param  Collection<int, object>  $rows
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
     * @param  Collection<int, object>  $rows
     * @return Collection<int, array{code: int, label: string, amount: float}>
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

    /**
     * @param  resource  $stream
     * @param  array<string, mixed>  $analysis
     */
    private function writeAnalysisSection($stream, array $analysis): void
    {
        fputcsv($stream, ['■原価計算結果']);
        fputcsv($stream, ['注番', $analysis['orderNo']]);
        fputcsv($stream, ['対象注番数', $analysis['includedOrderNos']->count()]);
        fputcsv($stream, ['対象注番一覧', $analysis['includedOrderNos']->implode('、')]);
        fputcsv($stream, [
            '計上締め',
            $analysis['cutoffDate'] !== null ? Carbon::parse($analysis['cutoffDate'])->isoFormat('YYYY年M月末まで') : '指定なし(全期間)',
        ]);

        $result = $analysis['result'];
        if ($result === null) {
            fputcsv($stream, ['該当データなし']);
            fputcsv($stream, []);

            return;
        }

        fputcsv($stream, ['受注金額', $result['summary']['order_amount']]);
        fputcsv($stream, ['総原価(比率雑費込み)', $result['summary']['total_cost']]);
        fputcsv($stream, ['簡易収支', $result['summary']['gross_profit']]);
        fputcsv($stream, ['収支率(%)', $result['summary']['profit_margin']]);
        foreach ($result['items'] as $item) {
            fputcsv($stream, [$item['label'], $item['amount']]);
        }
        fputcsv($stream, ['内訳:人工等', $result['labor_cost']]);
        foreach ($result['labor_breakdown'] as $laborItem) {
            fputcsv($stream, ['　└'.$laborItem['label'], $laborItem['amount']]);
        }
        fputcsv($stream, ['内訳:旅費等', $result['travel_cost']]);
        fputcsv($stream, ['小計', $result['subtotal']]);
        fputcsv($stream, ['比率雑費(小計の5%・100円未満切り捨て)', $result['misc_ratio']]);
        if ($result['misc_category_amount'] > 0) {
            fputcsv($stream, ['「他/他」未分類バケット(集計除外)', $result['misc_category_amount']]);
        }
        if ($result['unclassified_amount'] > 0) {
            fputcsv($stream, ['分類コード未設定(集計除外)', $result['unclassified_amount']]);
        }
        fputcsv($stream, []);
    }

    /**
     * @param  resource  $stream
     * @param  array<string, mixed>  $analysis
     */
    private function writePurchaseSection($stream, array $analysis): void
    {
        fputcsv($stream, ['■仕入レコード']);
        fputcsv($stream, [
            '注番', '機械装置No', '製品名', '分類', 'メーカー', '品名', '形式/寸法', '数量', '単位', '単価', '金額',
            '商社名', '注文日', '受入日', '納品書日', '受注先', '受注日', '納入先', '受注金額', '売上日', '商社納品書No', '備考',
        ]);

        if ($analysis['includedOrderNos']->isEmpty()) {
            fputcsv($stream, []);

            return;
        }

        $cutoffDateEnd = $analysis['cutoffDate'] !== null ? $analysis['cutoffDate'].' 23:59:59' : null;

        PurchaseDetail::query()
            ->with('category')
            ->whereIn('item_code', $analysis['includedOrderNos'])
            ->where('is_provisional', false)
            ->when($cutoffDateEnd !== null, fn ($q) => $q->whereNotNull('arrival_date')->where('arrival_date', '<=', $cutoffDateEnd))
            ->orderBy('item_code')
            ->orderBy('id')
            ->get()
            ->each(function (PurchaseDetail $d) use ($stream) {
                fputcsv($stream, [
                    $d->item_code, $d->machine_no, $d->product_name,
                    $d->category ? $d->category->code.':'.$d->category->major_category.($d->category->sub_category ? '／'.$d->category->sub_category : '') : '',
                    $d->manufacturer, $d->item_name, $d->dimensions, $d->order_qty, $d->unit, $d->unit_price, $d->lineTotal(),
                    $d->supplier_name,
                    $d->order_date?->format('Y-m-d'), $d->arrival_date?->format('Y-m-d'), $d->invoice_date?->format('Y-m-d'),
                    $d->recipient, $d->order_received_date?->format('Y-m-d'), $d->delivery_dest, $d->order_amount, $d->sales_date?->format('Y-m-d'),
                    $d->supplier_invoice_no, $d->remarks,
                ]);
            });

        fputcsv($stream, []);
    }

    /**
     * @param  resource  $stream
     * @param  array<string, mixed>  $analysis
     */
    private function writeLaborSection($stream, array $analysis): void
    {
        fputcsv($stream, ['■人工データ']);
        fputcsv($stream, ['作業日', '担当者(SID)', '注番', '機械装置No', '分類', '時間', '分', '人工', '概算額', '時間外', '補足']);

        if ($analysis['includedOrderNos']->isEmpty()) {
            return;
        }

        $cutoffDateEnd = $analysis['cutoffDate'] !== null ? $analysis['cutoffDate'].' 23:59:59' : null;

        LaborCost::query()
            ->with(['staff', 'category'])
            ->whereIn('order_no', $analysis['includedOrderNos'])
            ->where('is_provisional', false)
            ->when($cutoffDateEnd !== null, fn ($q) => $q->whereNotNull('work_date')->where('work_date', '<=', $cutoffDateEnd))
            ->orderBy('work_date')
            ->orderBy('id')
            ->get()
            ->each(function (LaborCost $l) use ($stream) {
                fputcsv($stream, [
                    $l->work_date?->format('Y-m-d'),
                    $l->staff ? ($l->staff->sid !== null ? $l->staff->sid.':'.$l->staff->name : $l->staff->name) : '',
                    $l->order_no, $l->machine_no,
                    $l->category ? $l->category->code.':'.$l->category->major_category.($l->category->sub_category ? '／'.$l->category->sub_category : '') : '',
                    $l->work_hours, $l->work_minutes, round($l->totalMinutes() / 480, 3), $l->estimatedCost(),
                    $l->is_overtime ? '1' : '', $l->note,
                ]);
            });
    }
}
