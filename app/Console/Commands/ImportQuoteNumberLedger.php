<?php

namespace App\Console\Commands;

use App\Models\CustomerCode;
use App\Models\QuoteNumber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 過去の「注文番号管理台帳」CSV(sample/order)を見積番号台帳へ取り込む。
 *
 * CSVの構造:
 *   見出し行  「客先番号,,会社名」 … 客先番号マスタになる(例「D,,大幸」)
 *   明細行    「,客先番号,装置番号,補足注番,,工事名,納入先,備考,ノートNo,完了日,…」
 *
 * 実行のたびに source='legacy' の行だけを洗い替える(この画面で採番した分は残す)。
 */
class ImportQuoteNumberLedger extends Command
{
    protected $signature = 'app:import-quote-number-ledger {--dir= : CSVのあるディレクトリ(既定 sample/order)} {--dry-run}';

    protected $description = '過去の注文番号管理台帳CSVを見積番号台帳へ取り込む';

    public function handle(): int
    {
        $dir = $this->option('dir') ?: base_path('sample/order');

        if (! is_dir($dir)) {
            $this->error("ディレクトリが見つかりません: {$dir}");

            return self::FAILURE;
        }

        $files = collect(glob($dir.'/*.csv'))->reject(fn ($p) => str_contains(basename($p), '規約'))->values();

        if ($files->isEmpty()) {
            $this->error('取り込む対象のCSVがありません。');

            return self::FAILURE;
        }

        $companies = [];
        $rows = [];
        $unparsed = 0;

        foreach ($files as $path) {
            foreach ($this->readRows($path) as $cols) {
                // 見出し行: 「客先番号,,会社名」
                if (($cols[0] ?? '') !== '' && ($cols[1] ?? '') === '' && ($cols[2] ?? '') !== ''
                    && preg_match('/^[A-Z]{1,3}$/', trim($cols[0]))) {
                    $companies[trim($cols[0])] = trim($cols[2]);

                    continue;
                }

                // 明細行: 先頭が空で、2列目が客先番号、3列目が装置番号(見積単位)
                $code = trim($cols[1] ?? '');
                $unit = trim($cols[2] ?? '');

                if (($cols[0] ?? '') !== '' || ! preg_match('/^[A-Z]{1,3}$/', $code) || $unit === '') {
                    continue;
                }

                $suffix = trim($cols[3] ?? '') ?: null;
                $parsed = QuoteNumber::parseSuffix($suffix);

                if ($suffix !== null && $parsed === null) {
                    $unparsed++;
                }

                $rows[] = [
                    'full_no' => $code.$unit.($suffix !== null ? '-'.$suffix : ''),
                    'customer_code' => $code,
                    'unit_no' => $unit,
                    'suffix' => $suffix,
                    'quote_type' => $parsed['quote_type'] ?? null,
                    'quote_seq' => $parsed['quote_seq'] ?? null,
                    'extra_code' => $parsed['extra_code'] ?? null,
                    'project_name' => trim($cols[5] ?? '') ?: null,
                    'delivery_dest' => trim($cols[6] ?? '') ?: null,
                    'customer_contact' => null,
                    'remarks' => trim($cols[7] ?? '') ?: null,
                    'note_no' => trim($cols[8] ?? '') ?: null,
                    'completed_on' => trim($cols[9] ?? '') ?: null,
                    'staff_id' => null,
                    'source' => 'legacy',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        $this->info(sprintf(
            '%s客先番号 %d件 / 台帳 %d件（うち規約どおりにパースできなかった補足注番 %d件）',
            $this->option('dry-run') ? '[dry-run] ' : '',
            count($companies),
            count($rows),
            $unparsed
        ));

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($companies, $rows) {
            foreach ($companies as $code => $name) {
                CustomerCode::updateOrCreate(['code' => $code], ['company_name' => $name]);
            }

            QuoteNumber::where('source', 'legacy')->delete();

            // SQLiteのバインド変数上限(32,766)を超えないよう、17列 × 1000件で分割する。
            foreach (array_chunk($rows, 1000) as $chunk) {
                DB::table('quote_numbers')->insert($chunk);
            }
        });

        $this->info('取り込みが完了しました。');

        return self::SUCCESS;
    }

    /**
     * 台帳CSVはShift_JISで、行によって列数が異なる。UTF-8へ変換しながら1行ずつ返す。
     *
     * @return \Generator<int, array<int, string>>
     */
    private function readRows(string $path): \Generator
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return;
        }

        try {
            while (($cols = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                yield array_map(
                    fn ($v) => $v === null ? '' : mb_convert_encoding($v, 'UTF-8', 'SJIS-win'),
                    $cols
                );
            }
        } finally {
            fclose($handle);
        }
    }
}
