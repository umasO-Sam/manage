<?php

namespace App\Console\Commands;

use App\Models\BusinessPartner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Excelで管理していた「売上取引先一覧」のCSVを取引先マスタへ取り込む。
 *
 * 取引先名(2列目)で突き合わせ、無ければ作成・あれば上書きする。既にこの画面や
 * 物件管理ボードで入力された取引条件(銀行・取引区分・締め日・支払い条件)と
 * 関連注番はCSVに無い項目なので、取り込みでは触らない。
 *
 * CSVの列順(先頭行は見出し):
 *   50音 / 取引先 / 郵便番号 / 住所 / TEL / FAX / 処理方法 / 弥生補助科目 /
 *   集塵機の袋 / 並び順 / 備考 / 下請法サイト60日伺い
 */
class ImportBusinessPartners extends Command
{
    protected $signature = 'app:import-business-partners {path : 取り込むCSVのパス} {--dry-run : 保存せず件数だけ出す}';

    protected $description = '売上取引先一覧のCSVを取引先マスタへ取り込む';

    public function handle(): int
    {
        $path = $this->argument('path');

        if (! is_readable($path)) {
            $this->error("CSVを読めません: {$path}");

            return self::FAILURE;
        }

        $rows = $this->readCsv($path);

        if ($rows === []) {
            $this->error('取り込める行がありませんでした。');

            return self::FAILURE;
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($rows, &$created, &$updated) {
            foreach ($rows as $row) {
                $partner = BusinessPartner::where('name', $row['name'])->first();

                if ($partner === null) {
                    // 既存の取引先なので取引条件調整中にはしない(本登録として入れる)。
                    BusinessPartner::create([...$row, 'is_provisional' => false]);
                    $created++;

                    continue;
                }

                $partner->update($row);
                $updated++;
            }

            if ($this->option('dry-run')) {
                DB::rollBack();
            }
        });

        $this->info(($this->option('dry-run') ? '[dry-run] ' : '')."取り込み完了: 新規 {$created} 件 / 更新 {$updated} 件");

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, string|int|null>>
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $rows = [];
        $isHeader = true;

        while (($columns = fgetcsv($handle)) !== false) {
            if ($isHeader) {
                $isHeader = false;

                continue;
            }

            // 取引先名の無い行(Excelの空行・区切り行)は飛ばす。
            $name = $this->clean($columns[1] ?? '');

            if ($name === null) {
                continue;
            }

            $order = $this->clean($columns[9] ?? '');

            $rows[] = [
                'kana_group' => $this->clean($columns[0] ?? ''),
                'name' => $name,
                'postal_code' => $this->clean($columns[2] ?? ''),
                'address' => $this->clean($columns[3] ?? ''),
                'tel' => $this->clean($columns[4] ?? ''),
                'fax' => $this->clean($columns[5] ?? ''),
                'handling_method' => $this->clean($columns[6] ?? ''),
                'yayoi_sub_account' => $this->clean($columns[7] ?? ''),
                'dust_bag' => $this->clean($columns[8] ?? ''),
                'display_order' => is_numeric($order) ? (int) $order : null,
                'remarks' => $this->clean($columns[10] ?? ''),
                'subcontract_note' => $this->clean($columns[11] ?? ''),
            ];
        }

        fclose($handle);

        return $rows;
    }

    /** 前後の空白とBOMを落とし、空欄はnullにする。 */
    private function clean(string $value): ?string
    {
        $value = trim(str_replace("\u{FEFF}", '', $value));

        return $value === '' ? null : $value;
    }
}
