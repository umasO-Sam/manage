<?php

namespace App\Http\Controllers;

use App\Models\BusinessOrder;
use App\Models\LaborCost;
use App\Models\PurchaseDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 受注日・受注金額が登録された注番(=確定した受注)を一覧集計する原価レポート。
 * 単一注番の詳細を見るCostAnalysisControllerに対し、こちらは複数の受注を
 * 横断して一括確認・CSV出力するためのもの。
 *
 * 「売上日」項目を追加したばかりでまだ入力が揃っていないため、いきなり
 * 期間集計するのではなく、まず受注日を手がかりにした候補一覧から対象を
 * 選ばせる(select)→選んだ注番で実際に集計する(results)の2段階にしている。
 */
class CostReportController extends Controller
{
    /** 候補選定: 終了日からさかのぼって候補として表示する期間(年)。 */
    private const CANDIDATE_WINDOW_YEARS = 2;

    /** 部品材料費の内訳: 材料費計(major_category='材料')・部品費計(major_category='部品')・スイッチセンサ計(コード31,32)。 */
    private const SWITCH_SENSOR_CODES = [31, 32];

    /** 機械等外注費(major_category='外注': 機械加工51・表面処理52)。電気関係外注費はコード53(電機／制御盤配線)。 */
    private const ELECTRICAL_OUTSOURCING_CODES = [53];

    /** 機械人工の内訳。 */
    private const MACHINE_MANUFACTURING_CODES = [59, 60]; // 機械製缶・機械組付/人工
    private const MACHINE_DESIGN_CODES = [63]; // 機械設計
    private const MACHINE_ONSITE_CODES = [64]; // 現地工事人工
    private const MACHINE_OTHER_CODES = [61, 62, 69]; // 旅費・打合見積・研修など(社内費その他計)

    /** 電機人工(電気設計65・電気製造67・ソフト対応68)。 */
    private const ELECTRICAL_LABOR_CODES = [65, 67, 68];

    /** その他の内訳: 運送費(54)・レンタルリース費(56)。比率雑費計は5%計算値を別途加える。 */
    private const SHIPPING_CODES = [54];
    private const LEASE_CODES = [56];

    /**
     * 対象選択画面。終了日からさかのぼった候補(受注日・受注金額が登録済みの注番)を
     * チェックボックスで、さらに候補期間より前の注番は手入力欄で選ばせる。
     */
    public function index(Request $request): View
    {
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));

        $candidates = $dateTo !== '' ? $this->findCandidates($dateTo) : collect();

        return view('purchasing.cost-report.index', [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'candidates' => $candidates,
            'windowYears' => self::CANDIDATE_WINDOW_YEARS,
        ]);
    }

    /**
     * 対象選択画面で選んだ注番の集計結果一覧。
     */
    public function results(Request $request): View
    {
        [$dateFrom, $dateTo, $itemCodes] = $this->parseSelection($request);

        $rows = $itemCodes->isNotEmpty() ? $this->buildReportRows($itemCodes) : collect();
        $miscLaborRow = ($dateFrom !== '' || $dateTo !== '') ? $this->buildMiscLaborRow($dateFrom, $dateTo) : null;

        return view('purchasing.cost-report.results', [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'itemCodes' => $itemCodes,
            'rows' => $rows,
            'miscLaborRow' => $miscLaborRow,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$dateFrom, $dateTo, $itemCodes] = $this->parseSelection($request);

        $rows = $itemCodes->isNotEmpty() ? $this->buildReportRows($itemCodes) : collect();
        $miscLaborRow = ($dateFrom !== '' || $dateTo !== '') ? $this->buildMiscLaborRow($dateFrom, $dateTo) : null;

        $headers = [
            '注番', '受注先', '納入先', '製品名', '受注額', '原価', '損益', '利益率(%)',
            '部品材料費', '材料費計', '部品費計', 'スイッチセンサ計',
            '機械等外注費', '電気関係外注費',
            '機械人工', '機械製造人工', '機械設計人工', '現地工事人工', '社内費その他計',
            '電機人工',
            'その他', '運送費', 'レンタルリース費', '比率雑費計',
        ];

        $csvRows = $rows->concat($miscLaborRow ? [$miscLaborRow] : [])->map(fn (array $r) => [
            $r['item_code'], $r['recipient'], $r['delivery_dest'], $r['product_name'], $r['order_amount'], $r['total_cost'], $r['profit'], $r['profit_margin'],
            $r['parts_material_total'], $r['material_cost'], $r['parts_cost'], $r['switch_sensor_cost'],
            $r['machine_outsourcing_cost'], $r['electrical_outsourcing_cost'],
            $r['machine_labor_total'], $r['machine_manufacturing_labor'], $r['machine_design_labor'], $r['machine_onsite_labor'], $r['machine_other_labor'],
            $r['electrical_labor_cost'],
            $r['other_total'], $r['shipping_cost'], $r['lease_cost'], $r['misc_ratio_cost'],
        ]);

        $fileName = 'cost_report_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($headers, $csvRows) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF"); // Excelでの文字化け防止のUTF-8 BOM
            fputcsv($stream, $headers);
            foreach ($csvRows as $row) {
                fputcsv($stream, $row);
            }
            fclose($stream);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array{0: string, 1: string, 2: Collection<int, string>}
     */
    private function parseSelection(Request $request): array
    {
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));
        $itemCodes = collect((array) $request->query('item_codes', []))
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '')
            ->unique()
            ->values();

        return [$dateFrom, $dateTo, $itemCodes];
    }

    /**
     * 終了日から過去2年以内で、受注日・受注金額(>0)が登録済みの注番を候補として返す。
     *
     * @return Collection<int, object>
     */
    private function findCandidates(string $dateTo): Collection
    {
        $windowStart = Carbon::parse($dateTo)->subYears(self::CANDIDATE_WINDOW_YEARS)->toDateString();

        // 受注日・受注金額は受注ヘッダ(business_orders)が持つ。以前は明細のMAX(...)を
        // 注番ごとに集約して同じことをしていた。
        return BusinessOrder::query()
            ->select('order_no as item_code', 'order_received_date', 'order_amount')
            ->whereNotNull('order_received_date')
            ->where('order_amount', '>', 0)
            ->whereDate('order_received_date', '<=', $dateTo)
            ->whereDate('order_received_date', '>=', $windowStart)
            ->orderByDesc('order_received_date')
            ->get();
    }

    /**
     * 選択された注番ごとに、仕入・人工を横断集計したレポート行を返す。
     *
     * @param  Collection<int, string>  $itemCodes
     * @return Collection<int, array<string, mixed>>
     */
    private function buildReportRows(Collection $itemCodes): Collection
    {
        // 受注金額・受注先・納入先・件名は受注ヘッダから引く。
        $headers = BusinessOrder::whereIn('order_no', $itemCodes)->get()->keyBy('order_no');
        $orderAmounts = $headers->map(fn (BusinessOrder $o) => (float) $o->order_amount);

        $purchaseRows = DB::table('purchase_details as p')
            ->leftJoin('category_codes as c', 'p.category_id', '=', 'c.id')
            ->whereIn('p.item_code', $itemCodes)
            ->where('p.is_provisional', false)
            ->selectRaw('p.item_code, c.code as category_code, c.major_category, (p.order_qty * p.unit_price) as amount')
            ->get()
            ->groupBy('item_code');

        $laborRows = DB::table('labor_costs as l')
            ->leftJoin('category_codes as c', 'l.category_id', '=', 'c.id')
            ->whereIn('l.order_no', $itemCodes)
            ->where('l.is_provisional', false)
            ->get()
            ->map(function ($l) {
                $totalMinutes = ((int) $l->work_hours * 60) + (int) $l->work_minutes;
                $hourlyRate = $l->is_overtime ? 50000 : 40000;
                $weight = (float) $l->position_weight_cache;
                $multiplier = $weight > 0 ? $weight : 1.0;

                return (object) [
                    'item_code' => $l->order_no,
                    'category_code' => $l->code,
                    'major_category' => $l->major_category,
                    'amount' => round(($totalMinutes / 480) * $hourlyRate * $multiplier),
                ];
            })
            ->groupBy('item_code');

        return $itemCodes->map(function (string $itemCode) use ($headers, $orderAmounts, $purchaseRows, $laborRows) {
            $rows = collect($purchaseRows->get($itemCode, collect()))->concat($laborRows->get($itemCode, collect()));
            $header = $headers->get($itemCode);

            return $this->buildRow(
                itemCode: $itemCode,
                recipient: $header?->recipient ?? '',
                deliveryDest: $header?->delivery_dest ?? '',
                productName: $header?->product_name ?? '',
                orderAmount: (float) ($orderAmounts[$itemCode] ?? 0),
                rows: $rows,
            );
        })->values();
    }

    /**
     * 期間中(work_date基準)の雑人工(major_category='雑人工')を、注番に紐づかない
     * 単独レコードとして原価に直接集計する。
     */
    private function buildMiscLaborRow(string $dateFrom, string $dateTo): ?array
    {
        $query = DB::table('labor_costs as l')
            ->leftJoin('category_codes as c', 'l.category_id', '=', 'c.id')
            ->where('l.is_provisional', false)
            ->where('c.major_category', '雑人工');

        if ($dateFrom !== '') {
            $query->where('l.work_date', '>=', $dateFrom);
        }
        if ($dateTo !== '') {
            $query->where('l.work_date', '<=', $dateTo);
        }

        $laborRows = $query->get()->map(function ($l) {
            $totalMinutes = ((int) $l->work_hours * 60) + (int) $l->work_minutes;
            $hourlyRate = $l->is_overtime ? 50000 : 40000;
            $weight = (float) $l->position_weight_cache;
            $multiplier = $weight > 0 ? $weight : 1.0;

            return (object) [
                'category_code' => $l->code,
                'major_category' => $l->major_category,
                'amount' => round(($totalMinutes / 480) * $hourlyRate * $multiplier),
            ];
        });

        if ($laborRows->isEmpty()) {
            return null;
        }

        $total = (int) round($laborRows->sum('amount'));

        return [
            'item_code' => '雑人工', 'recipient' => '', 'delivery_dest' => '', 'product_name' => '期間中の雑人工合計',
            'order_amount' => 0, 'total_cost' => $total, 'profit' => -$total, 'profit_margin' => null,
            'parts_material_total' => 0, 'material_cost' => 0, 'parts_cost' => 0, 'switch_sensor_cost' => 0,
            'machine_outsourcing_cost' => 0, 'electrical_outsourcing_cost' => 0,
            'machine_labor_total' => 0, 'machine_manufacturing_labor' => 0, 'machine_design_labor' => 0,
            'machine_onsite_labor' => 0, 'machine_other_labor' => 0,
            'electrical_labor_cost' => 0,
            'other_total' => 0, 'shipping_cost' => 0, 'lease_cost' => 0, 'misc_ratio_cost' => 0,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @return array<string, mixed>
     */
    private function buildRow(string $itemCode, string $recipient, string $deliveryDest, string $productName, float $orderAmount, Collection $rows): array
    {
        $sumMajor = fn (string $major) => (float) $rows->filter(fn ($r) => $r->major_category === $major)->sum('amount');
        $sumCodes = fn (array $codes) => (float) $rows->filter(fn ($r) => in_array((int) $r->category_code, $codes, true))->sum('amount');

        $materialCost = $sumMajor('材料');
        $partsCost = $sumMajor('部品');
        $switchSensorCost = $sumCodes(self::SWITCH_SENSOR_CODES);
        $partsMaterialTotal = $materialCost + $partsCost + $switchSensorCost;

        $machineOutsourcingCost = $sumMajor('外注');
        $electricalOutsourcingCost = $sumCodes(self::ELECTRICAL_OUTSOURCING_CODES);

        $machineManufacturingLabor = $sumCodes(self::MACHINE_MANUFACTURING_CODES);
        $machineDesignLabor = $sumCodes(self::MACHINE_DESIGN_CODES);
        $machineOnsiteLabor = $sumCodes(self::MACHINE_ONSITE_CODES);
        $machineOtherLabor = $sumCodes(self::MACHINE_OTHER_CODES);
        $machineLaborTotal = $machineManufacturingLabor + $machineDesignLabor + $machineOnsiteLabor + $machineOtherLabor;

        $electricalLaborCost = $sumCodes(self::ELECTRICAL_LABOR_CODES);

        $shippingCost = $sumCodes(self::SHIPPING_CODES);
        $leaseCost = $sumCodes(self::LEASE_CODES);

        // 比率雑費計は、それ以外の全費目の小計の5%(100円未満切り捨て)。従来の原価計算と同じ計算式。
        $coreSubtotal = $partsMaterialTotal + $machineOutsourcingCost + $electricalOutsourcingCost
            + $machineLaborTotal + $electricalLaborCost + $shippingCost + $leaseCost;
        $miscRatioCost = (int) floor(($coreSubtotal * 0.05) / 100) * 100;

        $otherTotal = $shippingCost + $leaseCost + $miscRatioCost;
        $totalCost = $coreSubtotal + $miscRatioCost;
        $profit = $orderAmount - $totalCost;

        return [
            'item_code' => $itemCode,
            'recipient' => $recipient,
            'delivery_dest' => $deliveryDest,
            'product_name' => $productName,
            'order_amount' => (int) $orderAmount,
            'total_cost' => (int) round($totalCost),
            'profit' => (int) round($profit),
            'profit_margin' => $orderAmount > 0 ? round(($profit / $orderAmount) * 100, 1) : null,
            'parts_material_total' => (int) round($partsMaterialTotal),
            'material_cost' => (int) round($materialCost),
            'parts_cost' => (int) round($partsCost),
            'switch_sensor_cost' => (int) round($switchSensorCost),
            'machine_outsourcing_cost' => (int) round($machineOutsourcingCost),
            'electrical_outsourcing_cost' => (int) round($electricalOutsourcingCost),
            'machine_labor_total' => (int) round($machineLaborTotal),
            'machine_manufacturing_labor' => (int) round($machineManufacturingLabor),
            'machine_design_labor' => (int) round($machineDesignLabor),
            'machine_onsite_labor' => (int) round($machineOnsiteLabor),
            'machine_other_labor' => (int) round($machineOtherLabor),
            'electrical_labor_cost' => (int) round($electricalLaborCost),
            'other_total' => (int) round($otherTotal),
            'shipping_cost' => (int) round($shippingCost),
            'lease_cost' => (int) round($leaseCost),
            'misc_ratio_cost' => (int) round($miscRatioCost),
        ];
    }
}
