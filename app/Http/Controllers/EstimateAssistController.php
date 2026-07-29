<?php

namespace App\Http\Controllers;

use App\Models\CategoryCode;
use App\Models\LaborCost;
use App\Models\PurchaseDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * 見積補助。仕入検索・人工計算・原価計算の各画面に分かれていた情報を
 * 「これから見積を作る」観点で1画面に集約する。
 * - 注番で仕入・人工を横断集計し、対象注番を個別に除外できる
 * - メーカー/品名/形式・寸法から過去の類似取引を参考価格として検索できる
 */
class EstimateAssistController extends Controller
{
    private const REFERENCE_CANDIDATE_LIMIT = 500;

    private const REFERENCE_DISPLAY_LIMIT = 100;

    public function index(Request $request): View
    {
        $agg = $this->aggregateByOrderNo($request);
        $purchaseRows = $agg['purchaseRows'];
        $laborRows = $agg['laborRows'];

        $priceTotal = $purchaseRows->sum(fn (PurchaseDetail $d) => $d->requiredAmount());
        $orderPriceTotal = $purchaseRows->sum(fn (PurchaseDetail $d) => $d->orderRequiredAmount());
        $salesAmountTotal = $purchaseRows->sum(fn (PurchaseDetail $d) => (float) $d->order_amount);

        $totalMinutes = $laborRows->sum(fn (LaborCost $r) => $r->totalMinutes());
        $laborCostTotal = $laborRows->sum(fn (LaborCost $r) => $r->estimatedCost());

        [$referenceFilters, $referenceResults, $referenceTotalCount] = $this->searchReferencePrices($request);

        return view('purchasing.estimate.index', [
            'orderNo' => $agg['orderNo'],
            'orderNoMatch' => $agg['orderNoMatch'],
            'matchedOrderNos' => $agg['matchedOrderNos'],
            'excludedOrderNos' => $agg['excludedOrderNos'],
            'includedOrderNos' => $agg['includedOrderNos'],
            'detailFilters' => $agg['detailFilters'],
            'categories' => CategoryCode::orderBy('code')->get(),
            'purchaseRows' => $purchaseRows,
            'laborRows' => $laborRows,
            'totals' => [
                'price' => (int) round($priceTotal),
                'order_price' => (int) round($orderPriceTotal),
                'sales_amount' => (int) round($salesAmountTotal),
                'total_labor' => round($totalMinutes / 480, 2),
                'labor_cost' => (int) round($laborCostTotal),
            ],
            'referenceFilters' => $referenceFilters,
            'referenceResults' => $referenceResults,
            'referenceTotalCount' => $referenceTotalCount,
            'referenceCandidateLimit' => self::REFERENCE_CANDIDATE_LIMIT,
            'referenceDisplayLimit' => self::REFERENCE_DISPLAY_LIMIT,
        ]);
    }

    /**
     * @return array{orderNo: string, orderNoMatch: string, matchedOrderNos: Collection<int, string>, excludedOrderNos: array<int, string>, includedOrderNos: Collection<int, string>, detailFilters: array{category_id: array<int, string>, manufacturer: string, item_name: string, dimensions: string, supplier_name: string}, purchaseRows: Collection<int, PurchaseDetail>, laborRows: Collection<int, LaborCost>}
     */
    private function aggregateByOrderNo(Request $request): array
    {
        $orderNo = trim((string) $request->query('order_no', ''));
        $orderNoMatch = $request->query('order_no_match') === 'perfect' ? 'perfect' : 'partial';
        $excludedOrderNos = array_values(array_filter((array) $request->query('excluded_order_nos', [])));

        $detailFilters = [
            'category_id' => array_values(array_filter((array) $request->query('category_id', []))),
            'manufacturer' => trim((string) $request->query('manufacturer', '')),
            'item_name' => trim((string) $request->query('item_name', '')),
            'dimensions' => trim((string) $request->query('dimensions', '')),
            'supplier_name' => trim((string) $request->query('supplier_name', '')),
        ];

        $matchedOrderNos = collect();
        $includedOrderNos = collect();
        $purchaseRows = collect();
        $laborRows = collect();

        if ($orderNo === '') {
            return compact('orderNo', 'orderNoMatch', 'matchedOrderNos', 'excludedOrderNos', 'includedOrderNos', 'detailFilters', 'purchaseRows', 'laborRows');
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

        if ($includedOrderNos->isNotEmpty()) {
            $purchaseQuery = PurchaseDetail::query()
                ->whereIn('item_code', $includedOrderNos)
                ->where('is_provisional', false);

            if (! empty($detailFilters['category_id'])) {
                $purchaseQuery->whereIn('category_id', $detailFilters['category_id']);
            }
            foreach (['manufacturer', 'item_name', 'dimensions', 'supplier_name'] as $column) {
                if ($detailFilters[$column] === '') {
                    continue;
                }
                $purchaseQuery->where(function ($q) use ($column, $detailFilters) {
                    foreach ($this->katakanaWidthVariants($detailFilters[$column]) as $variant) {
                        $q->orWhere($column, 'like', "%{$variant}%");
                    }
                });
            }

            $purchaseRows = $purchaseQuery->with('category')->orderByDesc('id')->get();

            $laborQuery = LaborCost::query()
                ->whereIn('order_no', $includedOrderNos)
                ->where('is_provisional', false);

            if (! empty($detailFilters['category_id'])) {
                $laborQuery->whereIn('category_id', $detailFilters['category_id']);
            }

            $laborRows = $laborQuery->with(['staff', 'category'])->orderByDesc('work_date')->get();
        }

        return compact('orderNo', 'orderNoMatch', 'matchedOrderNos', 'excludedOrderNos', 'includedOrderNos', 'detailFilters', 'purchaseRows', 'laborRows');
    }

    /**
     * @return array{0: array<string, array{value: string, match: string}>, 1: Collection<int, PurchaseDetail>, 2: int}
     */
    private function searchReferencePrices(Request $request): array
    {
        $fields = [];
        foreach (['manufacturer' => 'ref_manufacturer', 'item_name' => 'ref_item_name', 'dimensions' => 'ref_dimensions'] as $column => $param) {
            $value = trim((string) $request->query($param, ''));
            $match = $request->query("{$param}_match") === 'perfect' ? 'perfect' : 'partial';
            $fields[$column] = ['value' => $value, 'match' => $match];
        }
        $sort = $request->query('ref_sort') === 'newest' ? 'newest' : 'relevance';
        $fields['sort'] = $sort;

        $activeFields = array_filter($fields, fn ($f, $key) => $key !== 'sort' && $f['value'] !== '', ARRAY_FILTER_USE_BOTH);

        if (empty($activeFields)) {
            return [$fields, collect(), 0];
        }

        $query = PurchaseDetail::query()->where('is_provisional', false);
        $query->where(function ($q) use ($activeFields) {
            foreach ($activeFields as $column => $field) {
                foreach ($this->katakanaWidthVariants($field['value']) as $variant) {
                    if ($field['match'] === 'perfect') {
                        $q->orWhere($column, $variant);
                    } else {
                        $q->orWhere($column, 'like', "%{$variant}%");
                    }
                }
            }
        });

        $totalCount = (clone $query)->count();

        // 全件をスコアリングすると重いため、直近の注文日順に候補を絞ってから
        // 一致度を計算する(参考価格の性質上、古すぎる取引は優先度が低いため許容する)。
        $candidates = $query->with('category')
            ->orderByDesc('order_date')
            ->limit(self::REFERENCE_CANDIDATE_LIMIT)
            ->get();

        $candidates->each(function (PurchaseDetail $detail) use ($activeFields) {
            $score = 0;
            foreach ($activeFields as $column => $field) {
                $fieldValue = (string) $detail->{$column};
                $matched = false;
                foreach ($this->katakanaWidthVariants($field['value']) as $variant) {
                    $matched = $field['match'] === 'perfect'
                        ? $fieldValue === $variant
                        : mb_stripos($fieldValue, $variant) !== false;
                    if ($matched) {
                        break;
                    }
                }
                if ($matched) {
                    $score++;
                }
            }
            $detail->matchScore = $score;
        });

        // PHP 8のソートは安定ソートであるため、先に注文日順(タイブレーク)で
        // 並べてから一致度順(relevance時のみ)で並べ直せば「一致度優先・同点は
        // 注文日が新しい順」になる。
        $results = $candidates->sortByDesc(fn (PurchaseDetail $d) => $d->order_date?->timestamp ?? 0);
        if ($sort === 'relevance') {
            $results = $results->sortByDesc(fn (PurchaseDetail $d) => $d->matchScore);
        }

        return [$fields, $results->values()->take(self::REFERENCE_DISPLAY_LIMIT), $totalCount];
    }

    /**
     * 旧Accessデータのメーカー名等は半角カタカナで登録されていることが多く、
     * 全角カタカナで入力した検索語がヒットしないため、半角⇔全角カタカナの
     * 双方の表記で照合できるよう入力値のバリエーションを返す。
     *
     * @return array<int, string>
     */
    private function katakanaWidthVariants(string $value): array
    {
        return array_values(array_unique([
            $value,
            mb_convert_kana($value, 'kV'),
            mb_convert_kana($value, 'KV'),
        ]));
    }
}
