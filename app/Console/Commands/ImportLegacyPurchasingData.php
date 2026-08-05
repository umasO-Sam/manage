<?php

namespace App\Console\Commands;

use App\Models\CategoryCode;
use App\Models\Staff;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * dbtestシステムの元データ（Access「仕入管理ＤＢ.accdb」からエクスポートしたCSV）を
 * このLaravelアプリのDBへ一括インポートする。CSVはstorage/app/legacy-import/export.ps1で
 * 生成する（PHPにODBC拡張が無いため、エクスポートはPowerShell側で行う）。
 *
 * 実行のたびにcategory_codesは全件、purchase_details / labor_costs は
 * **source='legacy'(＝Access由来)の行だけ**を洗い替える。
 * 以前は無条件の全削除だったため、データ入力画面で登録した仕入レコードや、
 * 作業日報から生成された人工レコードまで巻き添えで消える状態だった。
 * staffは既存レコード（name一致）を壊さないよう、無ければ作成・あれば人工関連の列だけ更新する。
 */
class ImportLegacyPurchasingData extends Command
{
    /** Access由来のレコードに付ける出所。この値の行だけが取り込みで洗い替えられる。 */
    private const SOURCE_LEGACY = 'legacy';

    protected $signature = 'app:import-legacy-purchasing-data';

    protected $description = 'Access「仕入管理DB」からエクスポートしたCSVを取り込み、仕入管理データ・人工データ・分類コードを最新化する';

    /** @var array<int, int> Access分類コードID => category_codes.id */
    private array $categoryIdMap = [];

    /** @var array<int, int> Access分類コード(code)値 => category_codes.id（重複コードは最初に見つかったものを採用） */
    private array $categoryCodeMap = [];

    /** @var array<int, int> AccessのSID(社員ID) => staff.id */
    private array $staffIdMap = [];

    public function handle(): int
    {
        $dir = storage_path('app/legacy-import');

        foreach (['category_codes', 'staff', 'purchase_details', 'paste_error', 'labor_costs'] as $name) {
            if (! file_exists("{$dir}/{$name}.csv")) {
                $this->error("{$name}.csv が見つかりません。先に export.ps1 を実行してください。");

                return self::FAILURE;
            }
        }

        DB::transaction(function () use ($dir) {
            $this->importCategoryCodes("{$dir}/category_codes.csv");
            $this->importStaff("{$dir}/staff.csv");
            $this->importPurchaseDetails("{$dir}/purchase_details.csv", "{$dir}/paste_error.csv");
            $this->importLaborCosts("{$dir}/labor_costs.csv");
        });

        $this->info('インポートが完了しました。');

        return self::SUCCESS;
    }

    private function readCsv(string $path): \Generator
    {
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($header)) {
                continue;
            }
            yield array_combine($header, $row);
        }
        fclose($handle);
    }

    private function toBool(?string $value): bool
    {
        return trim((string) $value) === 'True';
    }

    private function toNullableInt(?string $value): ?int
    {
        $value = trim((string) $value);

        return $value === '' ? null : (int) round((float) $value);
    }

    private function toNullableDecimal(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : (string) (float) $value;
    }

    /**
     * Accessのdatetime文字列("2025/06/20 0:00:00")をYYYY-MM-DDへ。空・不正値はnull。
     */
    private function toDateString(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || str_starts_with($value, '0000')) {
            return null;
        }
        $ts = strtotime($value);

        return $ts === false ? null : date('Y-m-d', $ts);
    }

    private function importCategoryCodes(string $path): void
    {
        DB::table('category_codes')->delete();
        $this->categoryIdMap = [];
        $this->categoryCodeMap = [];

        $rows = [];
        foreach ($this->readCsv($path) as $row) {
            $code = (int) $row['分類コード'];
            $rows[] = [
                '_access_id' => (int) $row['ID'],
                'code' => $code,
                'major_category' => $row['大分類'] ?: null,
                'sub_category' => $row['細分'] ?: null,
                'item_name' => $row['品目'] ?: null,
                'is_parts' => $this->toBool($row['部品工具']),
                'is_internal' => $this->toBool($row['社内人工']),
                'is_outsourcing' => $this->toBool($row['外注']),
            ];
        }

        foreach ($rows as $row) {
            $accessId = $row['_access_id'];
            unset($row['_access_id']);
            $row['created_at'] = now();
            $row['updated_at'] = now();
            $id = DB::table('category_codes')->insertGetId($row);

            $this->categoryIdMap[$accessId] = $id;
            // 分類コードが重複するケースは最初に登録された方を代表として使う
            if (! isset($this->categoryCodeMap[$row['code']])) {
                $this->categoryCodeMap[$row['code']] = $id;
            }
        }

        $this->info('category_codes: '.count($rows).'件');
    }

    /**
     * Access側の氏名には姓名の間に全角スペースが入っている(例:「鵜飼　克彦」)ことがあり、
     * 既存スタッフの氏名(スペースなし「鵜飼克彦」)と完全一致しないため、スペースを除去して比較する。
     */
    private function normalizeStaffName(string $name): string
    {
        return str_replace(['　', ' '], '', $name);
    }

    private function importStaff(string $path): void
    {
        $this->staffIdMap = [];
        $created = 0;
        $updated = 0;

        $existingByNormalizedName = Staff::all()->keyBy(fn (Staff $s) => $this->normalizeStaffName($s->name));

        foreach ($this->readCsv($path) as $row) {
            $accessId = (int) $row['ID'];
            $name = trim($row['氏名']);
            if ($name === '') {
                continue;
            }

            $isLaborTarget = $this->toBool($row['人工対象']);
            $positionWeight = $this->toNullableInt($row['役職重さ']);

            $staff = $existingByNormalizedName->get($this->normalizeStaffName($name));

            if ($staff) {
                $staff->update([
                    'is_labor_target' => $isLaborTarget,
                    'position_weight' => $positionWeight,
                ]);
                $updated++;
            } else {
                $staff = Staff::create([
                    'name' => $name,
                    'department' => $row['役職'] ?: '未設定',
                    'login_id' => "legacy-{$accessId}",
                    'email' => "legacy{$accessId}@placeholder.invalid",
                    'role' => Staff::ROLE_GENERAL,
                    'is_labor_target' => $isLaborTarget,
                    'position_weight' => $positionWeight,
                    'password' => Hash::make(Str::random(40)),
                ]);
                $created++;
                $existingByNormalizedName->put($this->normalizeStaffName($name), $staff);
            }

            $this->staffIdMap[$accessId] = $staff->id;
        }

        $this->info("staff: 新規{$created}件 / 更新{$updated}件（未ログイン・人工集計専用のダミーアカウントとして作成）");
    }

    private function importPurchaseDetails(string $mainPath, string $pasteErrorPath): void
    {
        // このアプリのデータ入力画面で登録した分は残す(source='manage')。
        DB::table('purchase_details')->where('source', self::SOURCE_LEGACY)->delete();

        $count = 0;
        $batch = [];
        $flush = function () use (&$batch) {
            if (! empty($batch)) {
                DB::table('purchase_details')->insert($batch);
                $batch = [];
            }
        };

        foreach ($this->readCsv($mainPath) as $row) {
            $batch[] = $this->mapPurchaseDetailRow([
                'invoice_date' => $row['納品書日付'],
                'item_code' => $row['コード'],
                'machine_no' => $row['機械装置No'],
                'product_name' => $row['製品名'],
                'category' => $row['分類'],
                'manufacturer' => $row['メーカー'],
                'item_name' => $row['品名'],
                'dimensions' => $row['形式／寸法'],
                'remarks' => $row['備考'],
                'required_qty' => $row['必要数量'],
                'usage_purpose' => $row['使用用途'],
                'order_qty' => $row['注文数量'],
                'unit' => $row['単位'],
                'unit_price' => $row['単価'],
                'stock_qty' => $row['在庫'],
                'supplier_name' => $row['商社名'],
                'order_date' => $row['注文日付'],
                'arrival_date' => $row['受入日付'],
                'recipient' => $row['受注先'],
                'order_received_date' => $row['受注日'],
                'delivery_dest' => $row['納入先'],
                'order_amount' => $row['受注金額'],
                'supplier_invoice_no' => $row['商社納品書番号'],
            ]);
            $count++;

            // SQLiteのバインド変数上限(SQLITE_MAX_VARIABLE_NUMBER=32,766)を超えないよう、
            // 27列 × 1000件 = 27,000変数に抑える。
            if (count($batch) >= 1000) {
                $flush();
                $this->output->write('.');
            }
        }

        foreach ($this->readCsv($pasteErrorPath) as $row) {
            $batch[] = $this->mapPurchaseDetailRow([
                'invoice_date' => $row['納品書日付'] ?? null,
                'item_code' => $row['コード'],
                'machine_no' => $row['機械装置No'] ?? null,
                'product_name' => $row['製品名'] ?? null,
                'category' => $row['分類'] ?? null,
                'manufacturer' => $row['メーカー'] ?? null,
                'item_name' => $row['品名'] ?? null,
                'dimensions' => $row['形式／寸法'] ?? null,
                'remarks' => $row['備考'] ?? null,
                'required_qty' => $row['必要数量'] ?? null,
                'usage_purpose' => $row['使用用途'] ?? null,
                'order_qty' => $row['注文数量'] ?? null,
                'unit' => $row['単位'] ?? null,
                'unit_price' => $row['単価'] ?? null,
                'stock_qty' => $row['在庫'] ?? null,
                'supplier_name' => $row['商社名'] ?? null,
                'order_date' => $row['注文日付'] ?? null,
                'arrival_date' => $row['受入日付'] ?? null,
                'recipient' => $row['受注先'] ?? null,
                'order_received_date' => $row['受注日'] ?? null,
                'delivery_dest' => $row['納入先'] ?? null,
                'order_amount' => $row['受注金額'] ?? null,
                'supplier_invoice_no' => $row['商社納品書番号'] ?? null,
            ]);
            $count++;
        }

        $flush();
        $this->newLine();
        $this->info("purchase_details: {$count}件（「貼り付けエラー」の1件を含む）");
    }

    /**
     * @param  array<string, mixed>  $r
     * @return array<string, mixed>
     */
    private function mapPurchaseDetailRow(array $r): array
    {
        $categoryCode = $this->toNullableInt($r['category']);

        return [
            'item_code' => trim((string) $r['item_code']) ?: '(不明)',
            'machine_no' => $r['machine_no'] ?: null,
            'product_name' => $r['product_name'] ?: null,
            'category_id' => ($categoryCode && isset($this->categoryCodeMap[$categoryCode])) ? $this->categoryCodeMap[$categoryCode] : null,
            'manufacturer' => $r['manufacturer'] ?: null,
            'item_name' => $r['item_name'] ?: null,
            'dimensions' => $r['dimensions'] ?: null,
            'remarks' => $r['remarks'] ?: null,
            'required_qty' => $this->toNullableDecimal($r['required_qty']),
            'usage_purpose' => $r['usage_purpose'] ?: null,
            'order_qty' => $this->toNullableDecimal($r['order_qty']),
            'unit' => $r['unit'] ?: null,
            'unit_price' => $this->toNullableDecimal($r['unit_price']),
            'stock_qty' => $this->toNullableDecimal($r['stock_qty']),
            'supplier_name' => $r['supplier_name'] ?: null,
            'order_date' => $this->toDateString($r['order_date']),
            'arrival_date' => $this->toDateString($r['arrival_date']),
            'invoice_date' => $this->toDateString($r['invoice_date']),
            'recipient' => $r['recipient'] ?: null,
            'order_received_date' => $this->toDateString($r['order_received_date']),
            'delivery_dest' => $r['delivery_dest'] ?: null,
            'order_amount' => $this->toNullableDecimal($r['order_amount']),
            'supplier_invoice_no' => $r['supplier_invoice_no'] ?: null,
            'is_provisional' => false,
            'source' => self::SOURCE_LEGACY,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function importLaborCosts(string $path): void
    {
        // このアプリで登録した分(作業日報から生成された人工・データ入力での登録)は残す。
        DB::table('labor_costs')->where('source', self::SOURCE_LEGACY)->delete();

        $count = 0;
        $skippedNoStaff = 0;
        $batch = [];
        $flush = function () use (&$batch) {
            if (! empty($batch)) {
                DB::table('labor_costs')->insert($batch);
                $batch = [];
            }
        };

        foreach ($this->readCsv($path) as $row) {
            $sid = $this->toNullableInt($row['ＳＩＤ']);
            $staffId = $sid !== null ? ($this->staffIdMap[$sid] ?? null) : null;
            if ($sid !== null && $staffId === null) {
                $skippedNoStaff++;
            }

            $categoryCode = $this->toNullableInt($row['分類コード']);

            $batch[] = [
                'work_date' => $this->toDateString($row['年月日']),
                'staff_id' => $staffId,
                'order_no' => $row['注番'] ?: null,
                'machine_no' => $row['機械装置Ｎｏ'] ?: null,
                'category_id' => ($categoryCode && isset($this->categoryCodeMap[$categoryCode])) ? $this->categoryCodeMap[$categoryCode] : null,
                'work_hours' => $this->toNullableInt($row['時間']) ?? 0,
                'work_minutes' => $this->toNullableInt($row['分']) ?? 0,
                'is_overtime' => $this->toBool($row['時間外']),
                'position_weight_cache' => $this->toNullableDecimal($row['役職荷重']),
                'note' => $row['補足'] ?: null,
                'is_provisional' => false,
                'source' => self::SOURCE_LEGACY,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $count++;

            if (count($batch) >= 2000) {
                $flush();
                $this->output->write('.');
            }
        }

        $flush();
        $this->newLine();
        $this->info("labor_costs: {$count}件（担当者未特定のためstaff_idなしで取り込んだ件数: {$skippedNoStaff}）");
    }
}
