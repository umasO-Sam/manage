<?php

namespace Tests\Feature;

use App\Models\CategoryCode;
use App\Models\LaborCost;
use App\Models\PurchaseDetail;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasingModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_staff_cannot_view_input_orders_invoices_labor_or_cost_pages(): void
    {
        $staff = Staff::factory()->create();

        $this->actingAs($staff)->get(route('purchasing.input'))->assertForbidden();
        $this->actingAs($staff)->get(route('purchasing.orders.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('purchasing.invoices.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('purchasing.labor.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('purchasing.cost.index'))->assertForbidden();
    }

    public function test_procurement_manager_can_view_all_purchasing_pages(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        $this->actingAs($manager)->get(route('purchasing.input'))->assertOk();
        $this->actingAs($manager)->get(route('purchasing.orders.index'))->assertOk();
        $this->actingAs($manager)->get(route('purchasing.invoices.index'))->assertOk();
        $this->actingAs($manager)->get(route('purchasing.labor.index'))->assertOk();
        $this->actingAs($manager)->get(route('purchasing.cost.index'))->assertOk();
    }

    public function test_procurement_manager_can_register_a_confirmed_purchase_detail(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $category = CategoryCode::create(['code' => 1, 'major_category' => '部品', 'is_parts' => true]);

        $response = $this->actingAs($manager)->post(route('purchasing.input.store'), [
            'form_type' => 'purchase',
            'is_provisional' => '0',
            'item_code' => 'AB123-C45',
            'category_id' => $category->id,
            'manufacturer' => 'オムロン',
            'item_name' => '近接センサ',
            'order_qty' => 5,
            'unit' => '個',
            'unit_price' => 1000,
            'supplier_name' => '大津屋',
        ]);

        $response->assertRedirect(route('purchasing.input'));
        $this->assertDatabaseHas('purchase_details', [
            'item_code' => 'AB123-C45',
            'is_provisional' => false,
        ]);
    }

    public function test_provisional_purchase_detail_skips_required_fields(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        $response = $this->actingAs($manager)->post(route('purchasing.input.store'), [
            'form_type' => 'purchase',
            'is_provisional' => '1',
            'item_code' => 'TEMP-001',
        ]);

        $response->assertRedirect(route('purchasing.input'));
        $this->assertDatabaseHas('purchase_details', [
            'item_code' => 'TEMP-001',
            'is_provisional' => true,
        ]);
    }

    public function test_procurement_manager_can_register_labor(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $worker = Staff::factory()->create(['is_labor_target' => true, 'position_weight' => 1]);
        $category = CategoryCode::create(['code' => 2, 'major_category' => '機械設計']);

        $response = $this->actingAs($manager)->post(route('purchasing.input.store'), [
            'form_type' => 'labor',
            'is_provisional' => '0',
            'work_date' => now()->toDateString(),
            'staff_id' => $worker->id,
            'order_no' => 'AB123-C45',
            'category_id' => $category->id,
            'work_hours' => 4,
            'work_minutes' => 30,
        ]);

        $response->assertRedirect(route('purchasing.input'));
        $this->assertDatabaseHas('labor_costs', [
            'staff_id' => $worker->id,
            'work_hours' => 4,
            'work_minutes' => 30,
        ]);
    }

    public function test_order_search_excludes_provisional_rows(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        PurchaseDetail::create([
            'item_code' => 'A1', 'supplier_name' => '大津屋', 'order_date' => now(),
            'item_name' => '確定品', 'order_qty' => 1, 'unit_price' => 100, 'is_provisional' => false,
        ]);
        PurchaseDetail::create([
            'item_code' => 'A2', 'supplier_name' => '大津屋', 'order_date' => now(),
            'item_name' => '仮登録品', 'order_qty' => 1, 'unit_price' => 100, 'is_provisional' => true,
        ]);

        $response = $this->actingAs($manager)->get(route('purchasing.orders.index', ['supplier_name' => '大津屋']));

        $response->assertSee('確定品')->assertDontSee('仮登録品');
    }

    public function test_order_print_renders_selected_items_with_total(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $detail = PurchaseDetail::create([
            'item_code' => 'A1', 'supplier_name' => '大津屋', 'item_name' => 'テスト部品',
            'order_qty' => 3, 'unit_price' => 1000,
        ]);

        $response = $this->actingAs($manager)->post(route('purchasing.orders.print'), [
            'target_ids' => [$detail->id],
            'staff_name' => '瀧上',
        ]);

        $response->assertOk()->assertSee('テスト部品')->assertSee('3,000');
    }

    public function test_invoice_search_lists_suppliers_within_date_range(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        PurchaseDetail::create([
            'item_code' => 'A1', 'supplier_name' => '大津屋', 'invoice_date' => '2024-03-15',
            'item_name' => 'テスト部品', 'order_qty' => 1, 'unit_price' => 100,
        ]);

        $response = $this->actingAs($manager)->get(route('purchasing.invoices.index', [
            'date_from' => '2024-03-01',
            'date_to' => '2024-03-31',
        ]));

        $response->assertSee('大津屋');
    }

    public function test_invoice_print_calculates_tax_and_total(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        PurchaseDetail::create([
            'item_code' => 'A1', 'supplier_name' => '大津屋', 'invoice_date' => '2024-03-15',
            'item_name' => 'テスト部品', 'order_qty' => 1, 'unit_price' => 1000,
        ]);

        $response = $this->actingAs($manager)->post(route('purchasing.invoices.print'), [
            'supplier_name' => '大津屋',
            'date_from' => '2024-03-01',
            'date_to' => '2024-03-31',
            'date_type' => 'invoice_date',
        ]);

        $response->assertOk()->assertSee('1,000')->assertSee('100')->assertSee('1,100');
    }

    public function test_labor_summary_aggregates_hours_and_cost(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $worker = Staff::factory()->create(['is_labor_target' => true, 'position_weight' => 1]);
        LaborCost::create([
            'work_date' => now(), 'staff_id' => $worker->id, 'order_no' => 'A1',
            'work_hours' => 8, 'work_minutes' => 0, 'is_overtime' => false, 'position_weight_cache' => 1,
        ]);

        $response = $this->actingAs($manager)->get(route('purchasing.labor.index', [
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addDay()->toDateString(),
        ]));

        $response->assertSee($worker->name)->assertSee('40,000');
    }

    public function test_labor_summary_totals_include_records_beyond_display_limit(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $worker = Staff::factory()->create(['is_labor_target' => true, 'position_weight' => 1]);

        // 表示上限(1000件)を超える該当件数でも、集計値(合計時間・労務費)には
        // 全件が反映される必要がある(以前は集計前にlimit(1000)がかかり、超過分が黙って集計から漏れていた)。
        LaborCost::factory()->count(1005)->create([
            'staff_id' => $worker->id, 'order_no' => 'ZATSU',
            'work_hours' => 8, 'work_minutes' => 0, 'is_overtime' => false, 'position_weight_cache' => 1,
            'is_provisional' => false,
        ]);

        $response = $this->actingAs($manager)->get(route('purchasing.labor.index', ['order_no' => 'ZATSU']));

        // 1005件 × 8時間 × 40,000円/時間外なし × 人工480分換算 = 1005 * 40,000 = 40,200,000円
        $response->assertOk()
            ->assertSee('40,200,000')
            ->assertSee('該当1,005件中')
            ->assertSee('最新1,000件');
    }

    public function test_cost_analysis_computes_profit_and_margin(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $category = CategoryCode::create(['code' => 3, 'major_category' => '部品', 'is_parts' => true]);
        PurchaseDetail::create([
            'item_code' => 'A1', 'category_id' => $category->id, 'item_name' => '部品X',
            'supplier_name' => '大津屋', 'order_qty' => 2, 'unit_price' => 1000, 'order_amount' => 10000,
            'is_provisional' => false,
        ]);

        $response = $this->actingAs($manager)->get(route('purchasing.cost.index', ['order_no' => 'A1']));

        // 受注金額10,000、部品費2,000 + 比率雑費100(小計の5%を100円未満切り捨て) = 総原価2,100
        // 簡易収支 = 10,000 - 2,100 = 7,900、収支率79%
        $response->assertSee('10,000')->assertSee('2,100')->assertSee('7,900')->assertSee('79');
    }
}
