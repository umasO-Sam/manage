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

    public function test_procurement_manager_can_view_the_selection_and_results_pages(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        $this->actingAs($manager)->get(route('purchasing.cost-report.index'))->assertOk();
        $this->actingAs($manager)->get(route('purchasing.cost-report.results'))->assertOk();
    }

    public function test_sales_and_general_staff_cannot_view_the_pages(): void
    {
        foreach ([Staff::factory()->sales()->create(), Staff::factory()->create()] as $staff) {
            $this->actingAs($staff)->get(route('purchasing.cost-report.index'))->assertForbidden();
            $this->actingAs($staff)->get(route('purchasing.cost-report.results'))->assertForbidden();
        }
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

    public function test_selection_screen_shows_candidates_within_two_years_before_the_end_date(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        PurchaseDetail::create([
            'item_code' => 'SEL001-N01', 'order_qty' => 1, 'unit_price' => 1000,
            'order_received_date' => '2024-06-15', 'order_amount' => 5000, 'is_provisional' => false,
        ]);
        // 終了日(2024-06-30)から2年より前 → 候補から除外される
        PurchaseDetail::create([
            'item_code' => 'SEL002-N01', 'order_qty' => 1, 'unit_price' => 1000,
            'order_received_date' => '2022-01-01', 'order_amount' => 5000, 'is_provisional' => false,
        ]);
        // 受注金額が0 → 候補から除外される
        PurchaseDetail::create([
            'item_code' => 'SEL003-N01', 'order_qty' => 1, 'unit_price' => 1000,
            'order_received_date' => '2024-06-15', 'order_amount' => 0, 'is_provisional' => false,
        ]);

        $response = $this->actingAs($manager)->get(route('purchasing.cost-report.index', ['date_to' => '2024-06-30']));

        $response->assertOk()->assertSee('候補（1件）')->assertSee('SEL001-N01')->assertDontSee('SEL002-N01')->assertDontSee('SEL003-N01');
    }

    public function test_results_aggregates_only_the_selected_item_codes(): void
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

        // 除外用: 選択しない別注番
        PurchaseDetail::create([
            'item_code' => 'RPT099-N01', 'item_name' => '選択しない注番', 'order_qty' => 1, 'unit_price' => 1000,
            'order_received_date' => '2024-06-15', 'order_amount' => 9999, 'is_provisional' => false,
        ]);

        // 部品材料費=1,000+2,000+3,000=6,000 機械等外注費=4,000 電気関係外注費=5,000
        // 機械人工=40,000+20,000+10,000+5,000=75,000 電機人工=30,000
        // その他(運送費600+リース700)=1,300、小計121,700の5%切り捨て=6,000 → その他計=7,300
        // 総原価=121,700+6,000=127,300 損益=100,000-127,300=-27,300 利益率=-27.3%
        $response = $this->actingAs($manager)->get(route('purchasing.cost-report.results', [
            'item_codes' => ['RPT001-N01'],
        ]));

        $response->assertOk()
            ->assertSee('RPT001-N01')->assertSee('テスト工場')->assertSee('テスト製品')
            ->assertSee('100,000')->assertSee('127,300')->assertSee('-27,300')->assertSee('-27.3%')
            ->assertSee('75,000')->assertSee('30,000')
            ->assertDontSee('選択しない注番');
    }

    public function test_misc_labor_is_shown_as_a_standalone_row_scoped_by_the_date_range(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $worker = Staff::factory()->create(['is_labor_target' => true, 'position_weight' => 1]);
        $miscCategory = CategoryCode::create(['code' => 70, 'major_category' => '雑人工', 'sub_category' => '管理']);

        LaborCost::create([
            'work_date' => '2024-06-05', 'staff_id' => $worker->id, 'order_no' => 'ZATSU', 'category_id' => $miscCategory->id,
            'work_hours' => 8, 'work_minutes' => 0, 'is_overtime' => false, 'position_weight_cache' => 1, 'is_provisional' => false,
        ]);

        $response = $this->actingAs($manager)->get(route('purchasing.cost-report.results', [
            'date_from' => '2024-06-01', 'date_to' => '2024-06-30',
        ]));

        // 8h(1人工) × 40,000円 = 40,000円
        $response->assertOk()->assertSee('雑人工')->assertSee('期間中の雑人工合計')->assertSee('40,000');
    }

    public function test_manually_entered_item_code_outside_the_candidate_window_is_included(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        PurchaseDetail::create([
            'item_code' => 'OLD001-N01', 'product_name' => '古い注番', 'order_qty' => 1, 'unit_price' => 1000,
            'order_received_date' => '2018-01-01', 'order_amount' => 3000, 'is_provisional' => false,
        ]);

        $response = $this->actingAs($manager)->get(route('purchasing.cost-report.results', [
            'item_codes' => ['OLD001-N01'],
        ]));

        $response->assertOk()->assertSee('OLD001-N01')->assertSee('古い注番');
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
            'item_codes' => ['RPT004-N01'],
        ]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $content = $response->streamedContent();
        $this->assertStringContainsString('RPT004-N01', $content);
        $this->assertStringContainsString('5000', $content);
    }
}
