<?php

namespace App\Console\Commands;

use App\Models\BusinessOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 仕入の明細行(purchase_details)に相乗りしていた受注情報を、受注ヘッダ(business_orders)へ移す。
 *
 * 移行の取り方は既存の集計と同じにして、移行前後で金額が変わらないようにする:
 *   受注金額・受注日 … MAX(...)  (原価計算 CostAnalysisController / 原価一覧 CostReportController と同じ)
 *   受注先・納入先・件名 … 受注金額を持つ行のうち id が最小のもの
 *                          (原価一覧の salesRow の取り方と同じ)
 *
 * 同一注番で受注金額が食い違う注番は、MAXを採用したうえで一覧に出す(既存の集計も
 * 黙ってMAXを採っているため、移行によって数字は変わらない)。
 */
class MigrateOrderHeaders extends Command
{
    protected $signature = 'app:migrate-order-headers {--dry-run : 実際には保存せず結果だけ表示する}';

    protected $description = '明細に相乗りしている受注情報を受注ヘッダ(business_orders)へ移行する';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // 受注金額または受注日を持つ注番だけがヘッダの対象。
        $headers = DB::table('purchase_details')
            ->select('item_code')
            ->selectRaw('MAX(order_amount) as order_amount')
            ->selectRaw('MAX(order_received_date) as order_received_date')
            ->selectRaw('MAX(sales_date) as sales_date')
            ->selectRaw('COUNT(DISTINCT order_amount) as amount_variants')
            ->where('is_provisional', false)
            ->groupBy('item_code')
            ->havingRaw('MAX(order_amount) > 0 OR MAX(order_received_date) IS NOT NULL')
            ->get();

        if ($headers->isEmpty()) {
            $this->info('移行対象の注番はありません。');

            return self::SUCCESS;
        }

        $salesRows = DB::table('purchase_details')
            ->select('item_code', 'recipient', 'delivery_dest', 'product_name')
            ->where('is_provisional', false)
            ->where('order_amount', '>', 0)
            ->orderBy('id')
            ->get()
            ->keyBy('item_code');

        $existing = BusinessOrder::pluck('id', 'order_no');

        $created = 0;
        $skipped = 0;
        $conflicts = [];

        foreach ($headers as $header) {
            if ($header->amount_variants > 1) {
                $conflicts[] = $header->item_code;
            }

            if ($existing->has($header->item_code)) {
                $skipped++;

                continue;
            }

            $salesRow = $salesRows->get($header->item_code);

            if (! $dryRun) {
                BusinessOrder::create([
                    'order_no' => $header->item_code,
                    'product_name' => $salesRow->product_name ?? null,
                    'recipient' => $salesRow->recipient ?? null,
                    'delivery_dest' => $salesRow->delivery_dest ?? null,
                    'order_received_date' => $header->order_received_date,
                    'order_amount' => $header->order_amount,
                    'sales_date' => $header->sales_date,
                ]);
            }
            $created++;
        }

        $this->info(($dryRun ? '[dry-run] ' : '')."受注ヘッダ 新規{$created}件 / 既存のためスキップ{$skipped}件");

        if ($conflicts !== []) {
            $this->warn('同一注番で受注金額が複数登録されている注番です（最大値を採用しました。既存の集計と同じ扱いです）:');
            foreach ($conflicts as $itemCode) {
                $rows = DB::table('purchase_details')
                    ->where('item_code', $itemCode)
                    ->where('is_provisional', false)
                    ->where('order_amount', '>', 0)
                    ->pluck('order_amount')
                    ->implode(' / ');
                $this->warn("  {$itemCode}: {$rows}");
            }
        }

        return self::SUCCESS;
    }
}
