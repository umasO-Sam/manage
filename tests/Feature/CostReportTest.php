<?php

namespace Tests\Feature;

use App\Models\CategoryCode;
use App\Models\LaborCost;
use App\Models\PurchaseDetail;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CostReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_procurement_manager_and_sales_can_view_the_page(): void
    {
        $this->actingAs(Staff::factory()->procurementManager()->create())->get(route('purchasing.cost-report.index'))->assertOk();
        $this->actingAs(Staff::factory()->sales()->create())->get(route('purchasing.cost-report.index'))->assertOk();
    }

    public function test_general_staff_cannot_view_the_page(): void
    {
        $this->actingAs(Staff::factory()->create())->get(route('purchasing.cost-report.index'))->assertForbidden();
    }

    private function seedCategories(): array
    {
        return [
            'material' => CategoryCode::create(['code' => 11, 'major_category' => '材料', 'sub_category' => '金属']),
            'parts' => CategoryCode::create(['code' => 22, 'major_category' => '部品', 'sub_category' => 'モータ']),
            'switch_sensor' => CategoryCode::create(['code' => 31, 'major_category' => '電機', 'sub_category' => 'スイッチ／センサ']),
            'machine_outsourcing' => CategoryCode::create(['code' => 51, 'major_category' => '外注', 'sub_category' => '機械加工']),
            'electrical_outsourcing' => CategoryCode::create(['code' => 53, 'major_category' => '電機', 'sub_category' => '制御盤配線']),
            'shipping' => CategoryCode::create(['code' => 54, 'major_category' => '運賃', 'sub_category' => '運送']),
            'lease' => CategoryCode::create(['code' => 56, 'major_category' => 'リース', 'sub_category' => 'オフィス']),
            'machine_manufacturing' => CategoryCode::create(['code' => 59, 'major_category' => '社内人工', 'sub_category' => '機械製缶']),
            'machine_design' => CategoryCode::create(['code' => 63, 'major_category' => '社内人工', 'sub_category' => '機械設計']),
            'machine_onsite' => CategoryCode::create(['code' => 64, 'major_category' => '社内人工', 'sub_category' => '現地']),
            'machine_other' => CategoryCode::create(['code' => 61, 'major_category' => '社内人工', 'sub_category' => '旅費']),
            'electrical_labor' => CategoryCode::create(['code' => 65, 'major_category' => '社内人工', 'sub_category' => '電気設計']),
            'misc_labor' => CategoryCode::create(['code' => 70, 'major_category' => '雑人工', 'sub_category' => '管理']),
        ];
    }

    public function test_report_aggregates_a_confirmed_order_within_the_date_range(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $worker = Staff::factory()->create(['is_labor_target' => true, 'position_weight' => 1]);
        $cat = $this->seedCategories();

        PurchaseDetail::create([
            'item_code' => 'RPT001-N01', 'category_id' => null, 'item_name' => '受注行',
            'order_qty' => 0, 'unit_price' => 0, 'delivery_dest' => 'テスト工場', 'product_name' => 'テスト製品',
            'order_received_date' => '2024-06-15', 'order_amount' => 100000, 'is_provisional' => false,
        ]);
        PurchaseDetail::create(['item_code' => 'RPT001-N01', 'category_id' => $cat['material']->id, 'order_qty' => 1, 'unit_price' => 1000, 'is_provisional' => false]);
        PurchaseDetail::create(['item_code' => 'RPT001-N01', 'category_id' => $cat['parts']->id, 'order_qty' => 1, 'unit_price' => 2000, 'is_provisional' => false]);
        PurchaseDetail::create(['item_code' => 'RPT001-N01', 'category_id' => $cat['switch_sensor']->id, 'order_qty' => 1, 'unit_price' => 3000, 'is_provisional' => false]);
        PurchaseDetail::create(['item_code' => 'RPT001-N01', 'category_id' => $cat['machine_outsourcing']->id, 'order_qty' => 1, 'unit_price' => 4000, 'is_provisional' => false]);
        PurchaseDetail::create(['item_code' => 'RPT001-N01', 'category_id' => $cat['electrical_outsourcing']->id, 'order_qty' => 1, 'unit_price' => 5000, 'is_provisional' => false]);
        PurchaseDetail::create(['item_code' => 'RPT001-N01', 'category_id' => $cat['shipping']->id, 'order_qty' => 1, 'unit_price' => 600, 'is_provisional' => false]);
        PurchaseDetail::create(['item_code' => 'RPT001-N01', 'category_id' => $cat['lease']->id, 'order_qty' => 1, 'unit_price' => 700, 'is_provisional' => false]);

        LaborCost::create(['work_date' => '2024-06-10', 'staff_id' => $worker->id, 'order_no' => 'RPT001-N01', 'category_id' => $cat['machine_manufacturing']->id, 'work_hours' => 8, 'work_minutes' => 0, 'is_overtime' => false, 'position_weight_cache' => 1, 'is_provisional' => false]);
        LaborCost::create(['work_date' => '2024-06-10', 'staff_id' => $worker->id, 'order_no' => 'RPT001-N01', 'category_id' => $cat['machine_design']->id, 'work_hours' => 4, 'work_minutes' => 0, 'is_overtime' => false, 'position_weight_cache' => 1, 'is_provisional' => false]);
        LaborCost::create(['work_date' => '2024-06-10', 'staff_id' => $worker->id, 'order_no' => 'RPT001-N01', 'category_id' => $cat['machine_onsite']->id, 'work_hours' => 2, 'work_minutes' => 0, 'is_overtime' => false, 'position_weight_cache' => 1, 'is_provisional' => false]);
        LaborCost::create(['work_date' => '2024-06-10', 'staff_id' => $worker->id, 'order_no' => 'RPT001-N01', 'category_id' => $cat['machine_other']->id, 'work_hours' => 1, 'work_minutes' => 0, 'is_overtime' => false, 'position_weight_cache' => 1, 'is_provisional' => false]);
        LaborCost::create(['work_date' => '2024-06-10', 'staff_id' => $worker->id, 'order_no' => 'RPT001-N01', 'category_id' => $cat['electrical_labor']->id, 'work_hours' => 6, 'work_minutes' => 0, 'is_overtime' => false, 'position_weight_cache' => 1, 'is_provisional' => false]);

        // 部品材料費=1,000+2,000+3,000=6,000 機械等外注費=4,000 電気関係外注費=5,000
        // 機械人工=40,000+20,000+10,000+5,000=75,000 電機人工=30,000
        // その他(運送費600+リース700)=1,300、小計121,700の5%切り捨て=6,000 → その他計=7,300
        // 総原価=121,700+6,000=127,300 損益=100,000-127,300=-27,300 利益率=-27.3%
        $response = $this->actingAs($manager)->get(route('purchasing.cost-report.index', [
            'date_from' => '2024-06-01', 'date_to' => '2024-06-30',
        ]));

        $response->assertOk()
            ->assertSee('RPT001-N01')->assertSee('テスト工場')->assertSee('テスト製品')
            ->assertSee('100,000')->assertSee('127,300')->assertSee('-27,300')->assertSee('-27.3%')
            ->assertSee('6,000')->assertSee('75,000')->assertSee('30,000');
    }

    public function test_order_without_order_received_date_or_amount_is_excluded(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        PurchaseDetail::create([
            'item_code' => 'RPT002-N01', 'item_name' => '未受注行',
            'order_qty' => 1, 'unit_price' => 1000, 'is_provisional' => false,
        ]);

        $response = $this->actingAs($manager)->get(route('purchasing.cost-report.index', [
            'date_from' => '2020-01-01', 'date_to' => '2030-12-31',
        ]));

        $response->assertOk()->assertSee('該当する受注データがありません。');
    }

    public function test_order_outside_the_date_range_is_excluded(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        PurchaseDetail::create([
            'item_code' => 'RPT003-N01', 'item_name' => '対象外',
            'order_qty' => 1, 'unit_price' => 1000,
            'order_received_date' => '2023-01-01', 'order_amount' => 5000, 'is_provisional' => false,
        ]);

        $response = $this->actingAs($manager)->get(route('purchasing.cost-report.index', [
            'date_from' => '2024-01-01', 'date_to' => '2024-12-31',
        ]));

        $response->assertOk()->assertDontSee('RPT003-N01');
    }

    public function test_misc_labor_is_shown_as_a_standalone_row_within_the_period(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $worker = Staff::factory()->create(['is_labor_target' => true, 'position_weight' => 1]);
        $miscCategory = CategoryCode::create(['code' => 70, 'major_category' => '雑人工', 'sub_category' => '管理']);

        LaborCost::create([
            'work_date' => '2024-06-05', 'staff_id' => $worker->id, 'order_no' => 'ZATSU', 'category_id' => $miscCategory->id,
            'work_hours' => 8, 'work_minutes' => 0, 'is_overtime' => false, 'position_weight_cache' => 1, 'is_provisional' => false,
        ]);

        $response = $this->actingAs($manager)->get(route('purchasing.cost-report.index', [
            'date_from' => '2024-06-01', 'date_to' => '2024-06-30',
        ]));

        // 8h(1人工) × 40,000円 = 40,000円
        $response->assertOk()->assertSee('雑人工')->assertSee('期間中の雑人工合計')->assertSee('40,000');
    }

    public function test_csv_export_returns_a_csv_file_with_expected_data(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        PurchaseDetail::create([
            'item_code' => 'RPT004-N01', 'item_name' => '対象',
            'order_qty' => 1, 'unit_price' => 1000,
            'order_received_date' => '2024-06-15', 'order_amount' => 5000, 'is_provisional' => false,
        ]);

        $response = $this->actingAs($manager)->get(route('purchasing.cost-report.export', [
            'date_from' => '2024-06-01', 'date_to' => '2024-06-30',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $content = $response->streamedContent();
        $this->assertStringContainsString('RPT004-N01', $content);
        $this->assertStringContainsString('5000', $content);
    }
}
