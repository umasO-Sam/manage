<?php

namespace App\Http\Controllers;

use App\Models\CategoryCode;
use App\Models\LaborCost;
use App\Models\PurchaseDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 受注日・受注金額が登録された注番(=確定した受注)を、売上日(受注日)の範囲指定で
 * 一覧集計する原価レポート。単一注番の詳細を見るCostAnalysisControllerに対し、
 * こちらは期間内の受注を横断して一括確認・CSV出力するためのもの。
 */
class CostReportController extends Controller
{
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

    public function index(Request $request): View
    {
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));

        $rows = collect();
        $miscLaborRow = null;

        if ($dateFrom !== '' || $dateTo !== '') {
            $rows = $this->buildReportRows($dateFrom, $dateTo);
            $miscLaborRow = $this->buildMiscLaborRow($dateFrom, $dateTo);
        }

        return view('purchasing.cost-report.index', [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'rows' => $rows,
            'miscLaborRow' => $miscLaborRow,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));

        $rows = ($dateFrom !== '' || $dateTo !== '') ? $this->buildReportRows($dateFrom, $dateTo) : collect();
        $miscLaborRow = ($dateFrom !== '' || $dateTo !== '') ? $this->buildMiscLaborRow($dateFrom, $dateTo) : null;

        $headers = [
            '注番', '納入先', '製品名', '受注額', '原価', '損益', '利益率(%)',
            '部品材料費', '材料費計', '部品費計', 'スイッチセンサ計',
            '機械等外注費', '電気関係外注費',
            '機械人工', '機械製造人工', '機械設計人工', '現地工事人工', '社内費その他計',
            '電機人工',
            'その他', '運送費', 'レンタルリース費', '比率雑費計',
        ];

        $csvRows = $rows->concat($miscLaborRow ? [$miscLaborRow] : [])->map(fn (array $r) => [
            $r['item_code'], $r['delivery_dest'], $r['product_name'], $r['order_amount'], $r['total_cost'], $r['profit'], $r['profit_margin'],
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
     * 期間内に受注日・受注金額(>0)が登録された注番ごとに、仕入・人工を横断集計した
     * レポート行を返す。
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function buildReportRows(string $dateFrom, string $dateTo): Collection
    {
        $summaryQuery = PurchaseDetail::query()
            ->where('is_provisional', false)
            ->select('item_code')
            ->selectRaw('MAX(order_received_date) as order_received_date')
            ->selectRaw('MAX(order_amount) as order_amount')
            ->groupBy('item_code')
            ->havingNotNull('order_received_date')
            ->having('order_amount', '>', 0);

        if ($dateFrom !== '') {
            $summaryQuery->having('order_received_date', '>=', $dateFrom);
        }
        if ($dateTo !== '') {
            $summaryQuery->having('order_received_date', '<=', $dateTo);
        }

        $summaries = $summaryQuery->get()->keyBy('item_code');

        if ($summaries->isEmpty()) {
            return collect();
        }

        $itemCodes = $summaries->keys();

        $salesRowsByItemCode = PurchaseDetail::query()
            ->whereIn('item_code', $itemCodes)
            ->where('is_provisional', false)
            ->where('order_amount', '>', 0)
            ->orderBy('id')
            ->get()
            ->groupBy('item_code');

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

        return $itemCodes->map(function (string $itemCode) use ($summaries, $salesRowsByItemCode, $purchaseRows, $laborRows) {
            $rows = collect($purchaseRows->get($itemCode, collect()))->concat($laborRows->get($itemCode, collect()));
            $salesRow = $salesRowsByItemCode->get($itemCode, collect())->first();

            return $this->buildRow(
                itemCode: $itemCode,
                deliveryDest: $salesRow?->delivery_dest ?? '',
                productName: $salesRow?->product_name ?? '',
                orderAmount: (float) $summaries[$itemCode]->order_amount,
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
            'item_code' => '雑人工', 'delivery_dest' => '', 'product_name' => '期間中の雑人工合計',
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
    private function buildRow(string $itemCode, string $deliveryDest, string $productName, float $orderAmount, Collection $rows): array
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
