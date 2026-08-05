<?php

namespace Tests\Feature;

use App\Models\CategoryCode;
use App\Models\DailyReport;
use App\Models\LaborCost;
use App\Models\PurchaseDetail;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * app:import-legacy-purchasing-data の洗い替え範囲。
 *
 * 以前は purchase_details / labor_costs を無条件で全削除していたため、Accessを再取り込みすると
 * このアプリで登録した仕入レコードや、作業日報から生成された人工レコードまで消えていた。
 * source='legacy' の行だけが洗い替え対象になることを保証する。
 */
class LegacyImportSourceTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = storage_path('app/legacy-import');
        File::ensureDirectoryExists($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (['category_codes', 'staff', 'purchase_details', 'paste_error', 'labor_costs'] as $name) {
            File::delete("{$this->dir}/{$name}.csv");
        }

        parent::tearDown();
    }

    public function test_rows_created_in_this_app_survive_a_reimport(): void
    {
        $staff = Staff::factory()->create();
        $category = CategoryCode::create(['code' => 59, 'major_category' => '社内人工', 'item_name' => '機械製造']);

        // このアプリで登録した仕入レコード(データ入力画面相当)
        $managedDetail = PurchaseDetail::create([
            'item_code' => 'MG001-N01', 'item_name' => 'アプリで登録した部品', 'order_qty' => 1, 'unit_price' => 1000,
        ]);

        // 作業日報から生成された人工レコード
        $report = DailyReport::create(['staff_id' => $staff->id, 'work_date' => '2026-08-03', 'submitted_at' => now()]);
        $managedLabor = LaborCost::create([
            'work_date' => '2026-08-03', 'staff_id' => $staff->id, 'daily_report_id' => $report->id,
            'work_hours' => 8, 'work_minutes' => 0, 'is_overtime' => false, 'is_provisional' => false,
        ]);

        // 取り込み前に一度だけ「Access由来」の行を作っておき、洗い替えられることも確認する。
        $legacyDetail = PurchaseDetail::create(['item_code' => 'LG001-N01', 'item_name' => '旧データ']);
        $legacyDetail->forceFill(['source' => 'legacy'])->save();

        $this->writeCsvFixtures($category);

        $this->artisan('app:import-legacy-purchasing-data')->assertSuccessful();

        // このアプリで登録した分は残る
        $this->assertDatabaseHas('purchase_details', ['id' => $managedDetail->id, 'source' => 'manage']);
        $this->assertDatabaseHas('labor_costs', ['id' => $managedLabor->id, 'source' => 'manage']);

        // Access由来の古い行は消え、CSVの内容で入れ替わる
        $this->assertDatabaseMissing('purchase_details', ['id' => $legacyDetail->id]);
        $this->assertDatabaseHas('purchase_details', ['item_code' => 'IMP001-N01', 'source' => 'legacy']);
    }

    public function test_newly_created_rows_default_to_the_manage_source(): void
    {
        $detail = PurchaseDetail::create(['item_code' => 'MG002-N01']);

        $this->assertSame('manage', $detail->fresh()->source);
    }

    private function writeCsvFixtures(CategoryCode $category): void
    {
        File::put("{$this->dir}/category_codes.csv", implode("\n", [
            'ID,分類コード,大分類,細分,品目,部品工具,社内人工,外注',
            "1,{$category->code},社内人工,,機械製造,0,1,0",
        ]));

        File::put("{$this->dir}/staff.csv", "ID,氏名,役職,人工対象,役職重さ\n");

        File::put("{$this->dir}/purchase_details.csv", implode("\n", [
            '納品書日付,コード,機械装置No,製品名,分類,メーカー,品名,形式／寸法,備考,必要数量,使用用途,注文数量,単位,単価,在庫,商社名,注文日付,受入日付,受注先,受注日,納入先,受注金額,商社納品書番号',
            ',IMP001-N01,,製品,,,取り込んだ部品,,,,,1,個,500,,商社A,,,受注先A,2026-07-01,納入先A,100000,',
        ]));

        File::put("{$this->dir}/paste_error.csv", '納品書日付,コード,機械装置No,製品名,分類,メーカー,品名,形式／寸法,備考,必要数量,使用用途,注文数量,単位,単価,在庫,商社名,注文日付,受入日付,受注先,受注日,納入先,受注金額,商社納品書番号');

        File::put("{$this->dir}/labor_costs.csv", "年月日,ＳＩＤ,注番,機械装置Ｎｏ,分類コード,時間,分,時間外,役職荷重,補足\n");
    }
}
