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

    public function test_purchase_detail_unit_cannot_contain_digits(): void
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
            'unit' => '個5',
            'unit_price' => 1000,
            'supplier_name' => '大津屋',
        ]);

        $response->assertSessionHasErrors('unit');
        $errors = session('errors')->getBag('default');
        $this->assertStringContainsString('単位に数字は使用できません。', $errors->first('unit'));
    }

    public function test_purchase_detail_can_be_registered_without_manufacturer(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $category = CategoryCode::create(['code' => 1, 'major_category' => '部品', 'is_parts' => true]);

        $response = $this->actingAs($manager)->post(route('purchasing.input.store'), [
            'form_type' => 'purchase',
            'is_provisional' => '0',
            'item_code' => 'AB123-C45',
            'category_id' => $category->id,
            'item_name' => '近接センサ',
            'order_qty' => 5,
            'unit' => '個',
            'unit_price' => 1000,
            'supplier_name' => '大津屋',
        ]);

        $response->assertSessionDoesntHaveErrors('manufacturer');
        $response->assertRedirect(route('purchasing.input'));
        $this->assertDatabaseHas('purchase_details', [
            'item_code' => 'AB123-C45',
            'manufacturer' => null,
        ]);
    }

    public function test_procurement_manager_can_register_a_sales_date(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $category = CategoryCode::create(['code' => 1, 'major_category' => '部品', 'is_parts' => true]);

        $this->actingAs($manager)->post(route('purchasing.input.store'), [
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
            'order_amount' => 6000,
            'sales_date' => '2026-07-29',
        ]);

        $this->assertSame('2026-07-29', PurchaseDetail::where('item_code', 'AB123-C45')->first()->sales_date->format('Y-m-d'));
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

    public function test_purchase_detail_validation_error_shows_the_japanese_field_label(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        $response = $this->actingAs($manager)->post(route('purchasing.input.store'), [
            'form_type' => 'purchase',
            'is_provisional' => '0',
            'item_code' => 'AB123-C45',
        ]);

        $response->assertSessionHasErrors(['category_id', 'item_name', 'order_qty', 'unit_price', 'supplier_name']);
        $response->assertSessionDoesntHaveErrors('manufacturer');
        $errors = session('errors')->getBag('default');
        $this->assertStringContainsString('分類を入力してください。', $errors->first('category_id'));
        $this->assertStringContainsString('数量を入力してください。', $errors->first('order_qty'));
        $this->assertStringContainsString('単価を入力してください。', $errors->first('unit_price'));
        $this->assertStringContainsString('商社名を入力してください。', $errors->first('supplier_name'));
    }

    public function test_missing_required_category_select_is_highlighted_with_a_light_red_background(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        $this->actingAs($manager)->post(route('purchasing.input.store'), [
            'form_type' => 'purchase',
            'is_provisional' => '0',
            'item_code' => 'AB123-C45',
        ]);

        $response = $this->actingAs($manager)->get(route('purchasing.input'));

        $response->assertSee('id="category_id" name="category_id" class="mt-1 block w-full text-sm rounded-lg shadow-sm bg-red-50', false);
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
            'labor_category_id' => $category->id,
            'work_hours' => 4,
            'work_minutes' => 30,
        ]);

        $response->assertRedirect(route('purchasing.input'));
        $this->assertDatabaseHas('labor_costs', [
            'staff_id' => $worker->id,
            'category_id' => $category->id,
            'work_hours' => 4,
            'work_minutes' => 30,
        ]);
    }

    public function test_purchase_detail_category_is_saved_correctly(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $category = CategoryCode::create(['code' => 31, 'major_category' => '電機', 'sub_category' => 'スイッチ／センサ']);

        $response = $this->actingAs($manager)->post(route('purchasing.input.store'), [
            'form_type' => 'purchase',
            'is_provisional' => '0',
            'item_code' => 'AB123-C45',
            'category_id' => $category->id,
            'manufacturer' => 'テストメーカー',
            'item_name' => 'テスト部品',
            'order_qty' => 1,
            'unit_price' => 100,
            'supplier_name' => 'テスト商社',
        ]);

        $response->assertRedirect(route('purchasing.input'));
        $this->assertDatabaseHas('purchase_details', [
            'item_code' => 'AB123-C45',
            'category_id' => $category->id,
        ]);
    }

    public function test_purchase_detail_accepts_slash_formatted_dates(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $category = CategoryCode::create(['code' => 1, 'major_category' => '材料']);

        $response = $this->actingAs($manager)->post(route('purchasing.input.store'), [
            'form_type' => 'purchase',
            'is_provisional' => '0',
            'item_code' => 'AB123-C45',
            'category_id' => $category->id,
            'manufacturer' => 'テストメーカー',
            'item_name' => 'テスト部品',
            'order_qty' => 1,
            'unit_price' => 100,
            'supplier_name' => 'テスト商社',
            'order_date' => '2027/11/04',
            'sales_date' => '2027/12/01',
        ]);

        $response->assertRedirect(route('purchasing.input'));
        $detail = PurchaseDetail::where('item_code', 'AB123-C45')->firstOrFail();
        $this->assertSame('2027-11-04', $detail->order_date->toDateString());
        $this->assertSame('2027-12-01', $detail->sales_date->toDateString());
    }

    public function test_purchase_detail_rejects_invalid_date_text_with_japanese_message(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $category = CategoryCode::create(['code' => 1, 'major_category' => '材料']);

        $response = $this->actingAs($manager)->post(route('purchasing.input.store'), [
            'form_type' => 'purchase',
            'is_provisional' => '0',
            'item_code' => 'AB123-C45',
            'category_id' => $category->id,
            'manufacturer' => 'テストメーカー',
            'item_name' => 'テスト部品',
            'order_qty' => 1,
            'unit_price' => 100,
            'supplier_name' => 'テスト商社',
            'order_date' => 'よろしくない値',
        ]);

        $response->assertSessionHasErrors(['order_date']);
        $errors = session('errors')->getBag('default');
        $this->assertStringContainsString('注文日には有効な日付を指定してください。', $errors->first('order_date'));
    }

    public function test_bulk_paste_shows_a_confirmation_screen_without_saving_when_not_yet_confirmed(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        CategoryCode::create(['code' => 3, 'major_category' => '機械', 'sub_category' => 'バルブ']);

        $pasteData = "バタフライ弁（キッツ）\t1\t3\tG-10BJUE-50A\t1\t1500\t㈱モノタロウ\tキッツ";

        $response = $this->actingAs($manager)->post(route('purchasing.input.bulk-paste'), [
            'item_code' => 'AB123-C45',
            'order_date' => '2026/07/30',
            'paste_data' => $pasteData,
        ]);

        $response->assertOk();
        $response->assertSee('一括登録の確認');
        $response->assertSee('バタフライ弁（キッツ）');
        $response->assertSee('登録する');
        $this->assertSame(0, PurchaseDetail::count());
    }

    public function test_bulk_paste_saves_only_after_confirmation(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        CategoryCode::create(['code' => 3, 'major_category' => '機械', 'sub_category' => 'バルブ']);

        $pasteData = "バタフライ弁（キッツ）\t1\t3\tG-10BJUE-50A\t1\t1500\t㈱モノタロウ\tキッツ";

        $response = $this->actingAs($manager)->post(route('purchasing.input.bulk-paste'), [
            'item_code' => 'AB123-C45',
            'order_date' => '2026/07/30',
            'paste_data' => $pasteData,
            'confirmed' => '1',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect(route('purchasing.input'));
        $this->assertSame(1, PurchaseDetail::where('item_code', 'AB123-C45')->count());
    }

    public function test_bulk_paste_registers_multiple_rows_with_shared_item_code_and_date(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $category = CategoryCode::create(['code' => 3, 'major_category' => '機械', 'sub_category' => 'バルブ']);

        $pasteData = "バタフライ弁（キッツ）\t1\t3\tG-10BJUE-50A\t1\t1500\t㈱モノタロウ\tキッツ\n"
            ."安全弁\t2\t3\tSV-20\t3\t2000\t大津屋\t";

        $response = $this->actingAs($manager)->post(route('purchasing.input.bulk-paste'), [
            'item_code' => 'AB123-C45',
            'order_date' => '2026/07/30',
            'paste_data' => $pasteData,
            'confirmed' => '1',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect(route('purchasing.input'));
        $this->assertSame(2, PurchaseDetail::where('item_code', 'AB123-C45')->count());

        $first = PurchaseDetail::where('item_name', 'バタフライ弁（キッツ）')->firstOrFail();
        $this->assertSame('1', $first->machine_no);
        $this->assertSame($category->id, $first->category_id);
        $this->assertSame('G-10BJUE-50A', $first->dimensions);
        $this->assertSame('1.00', $first->order_qty);
        $this->assertSame('1500.00', $first->unit_price);
        $this->assertSame('㈱モノタロウ', $first->supplier_name);
        $this->assertSame('キッツ', $first->manufacturer);
        $this->assertSame('2026-07-30', $first->order_date->toDateString());
        $this->assertFalse($first->is_provisional);

        $second = PurchaseDetail::where('item_name', '安全弁')->firstOrFail();
        $this->assertNull($second->manufacturer);
    }

    public function test_bulk_paste_treats_category_1_as_unclassified_and_marks_provisional(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        // 分類コード1が実在していても「1」は分類未定の目印として優先される。
        CategoryCode::create(['code' => 1, 'major_category' => '本来の分類1']);

        $pasteData = 'バタフライ弁（キッツ）'."\t1\t1\tG-10BJUE-50A\t1\t1500\t㈱モノタロウ\tキッツ";

        $response = $this->actingAs($manager)->post(route('purchasing.input.bulk-paste'), [
            'item_code' => 'AB123-C45',
            'paste_data' => $pasteData,
            'confirmed' => '1',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $detail = PurchaseDetail::where('item_code', 'AB123-C45')->firstOrFail();
        $this->assertNull($detail->category_id);
        $this->assertTrue($detail->is_provisional);
    }

    public function test_provisional_banner_links_to_search_filtered_by_the_just_bulk_pasted_item_code(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        CategoryCode::create(['code' => 1, 'major_category' => '本来の分類1']);

        $pasteData = 'バタフライ弁（キッツ）'."\t1\t1\tG-10BJUE-50A\t1\t1500\t㈱モノタロウ\tキッツ";
        $this->actingAs($manager)->post(route('purchasing.input.bulk-paste'), [
            'item_code' => 'AB123-C45',
            'paste_data' => $pasteData,
            'confirmed' => '1',
        ]);

        $expectedUrl = route('purchasing.index', ['item_code' => 'AB123-C45', 'item_code_match' => 'perfect']);
        $this->actingAs($manager)->get(route('purchasing.input'))
            ->assertSee(e($expectedUrl), false);
    }

    public function test_provisional_banner_links_to_plain_search_when_not_from_a_bulk_paste(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        PurchaseDetail::create([
            'item_code' => 'AB123-C45', 'item_name' => 'テスト部品', 'order_qty' => 1, 'unit_price' => 100,
            'is_provisional' => true,
        ]);

        $response = $this->actingAs($manager)->get(route('purchasing.input'));

        $response->assertSee(e(route('purchasing.index')), false);
        $response->assertDontSee('item_code=AB123-C45');
    }

    public function test_bulk_paste_skips_a_pasted_header_row(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        CategoryCode::create(['code' => 1, 'major_category' => '機械']);

        $pasteData = "品名\t機械装置No\t分類\t型式\t数量\t単価\t商社名\tメーカー\n"
            ."バタフライ弁\t1\t1\tG-10\t1\t100\t大津屋\tキッツ";

        $response = $this->actingAs($manager)->post(route('purchasing.input.bulk-paste'), [
            'item_code' => 'AB123-C45',
            'paste_data' => $pasteData,
            'confirmed' => '1',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame(1, PurchaseDetail::where('item_code', 'AB123-C45')->count());
    }

    public function test_bulk_paste_rejects_more_than_the_maximum_rows(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        CategoryCode::create(['code' => 1, 'major_category' => '機械']);

        $pasteData = collect(range(1, 201))
            ->map(fn ($i) => "部品{$i}\t\t1\t\t1\t100\t商社\t")
            ->implode("\n");

        $response = $this->actingAs($manager)->post(route('purchasing.input.bulk-paste'), [
            'item_code' => 'AB123-C45',
            'paste_data' => $pasteData,
        ]);

        $response->assertSessionHasErrors('paste_data');
        $errors = session('errors')->getBag('default');
        $this->assertStringContainsString('200行までです', $errors->first('paste_data'));
        $this->assertSame(0, PurchaseDetail::count());
    }

    public function test_bulk_paste_reports_row_level_errors_in_japanese_and_saves_nothing(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        CategoryCode::create(['code' => 1, 'major_category' => '機械']);

        $pasteData = "有効な部品\t\t1\t\t1\t100\t商社\t\n"
            ."\t\t99\tG-10\tabc\t\t\t";

        $response = $this->actingAs($manager)->post(route('purchasing.input.bulk-paste'), [
            'item_code' => 'AB123-C45',
            'paste_data' => $pasteData,
        ]);

        $response->assertSessionHasErrors('paste_data');
        $errors = session('errors')->getBag('default')->get('paste_data');
        $this->assertTrue(collect($errors)->contains(fn ($e) => str_contains($e, '2行目') && str_contains($e, '品名を入力してください')));
        $this->assertTrue(collect($errors)->contains(fn ($e) => str_contains($e, '2行目') && str_contains($e, '分類コード')));
        $this->assertTrue(collect($errors)->contains(fn ($e) => str_contains($e, '2行目') && str_contains($e, '数量を数値')));
        $this->assertTrue(collect($errors)->contains(fn ($e) => str_contains($e, '2行目') && str_contains($e, '商社名を入力してください')));
        $this->assertSame(0, PurchaseDetail::count());
    }

    public function test_bulk_paste_normalizes_full_width_digits_and_yen_signs(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $category = CategoryCode::create(['code' => 5, 'major_category' => '機械']);

        $pasteData = "部品\t\t５\t\t１\t¥1,200\t商社\t";

        $response = $this->actingAs($manager)->post(route('purchasing.input.bulk-paste'), [
            'item_code' => 'AB123-C45',
            'paste_data' => $pasteData,
            'confirmed' => '1',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $detail = PurchaseDetail::where('item_code', 'AB123-C45')->firstOrFail();
        $this->assertSame($category->id, $detail->category_id);
        $this->assertSame('1.00', $detail->order_qty);
        $this->assertSame('1200.00', $detail->unit_price);
    }

    public function test_bulk_paste_requires_procurement_manager(): void
    {
        $staff = Staff::factory()->create();

        $this->actingAs($staff)->post(route('purchasing.input.bulk-paste'), [
            'item_code' => 'AB123-C45',
            'paste_data' => "部品\t\t1\t\t1\t100\t商社\t",
        ])->assertForbidden();
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

    public function test_labor_index_shows_nothing_when_no_filter_is_specified(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $worker = Staff::factory()->create(['is_labor_target' => true, 'position_weight' => 1]);
        LaborCost::create([
            'work_date' => now(), 'staff_id' => $worker->id, 'order_no' => 'A1',
            'work_hours' => 8, 'work_minutes' => 0, 'is_overtime' => false, 'position_weight_cache' => 1,
        ]);

        // ナビゲーションからの遷移など、条件を何も指定していない状態では
        // 全件集計(重い)を行わず、条件入力を促す空の状態を表示する。
        $response = $this->actingAs($manager)->get(route('purchasing.labor.index'));

        // 担当者名は絞り込みフォームのプルダウン候補としては表示されるため、
        // 集計結果側(労務費合計0円・空の一覧)だけを確認する。
        $response->assertOk()
            ->assertSee('労務費合計: ¥0', false)
            ->assertSee('条件を指定して集計を実行してください。');
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

    public function test_labor_index_can_exclude_a_matched_order_number(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $worker = Staff::factory()->create(['is_labor_target' => true, 'position_weight' => 1]);

        LaborCost::create([
            'work_date' => now(), 'staff_id' => $worker->id, 'order_no' => 'AA100-X01',
            'work_hours' => 8, 'work_minutes' => 0, 'is_overtime' => false, 'position_weight_cache' => 1, 'is_provisional' => false,
        ]);
        LaborCost::create([
            'work_date' => now(), 'staff_id' => $worker->id, 'order_no' => 'AA200-X01',
            'work_hours' => 8, 'work_minutes' => 0, 'is_overtime' => false, 'position_weight_cache' => 1, 'is_provisional' => false,
        ]);

        $all = $this->actingAs($manager)->get(route('purchasing.labor.index', ['order_no' => 'AA']));
        $all->assertSee('AA100-X01')->assertSee('AA200-X01')->assertSee('2 / 2 件を対象');

        $excluded = $this->actingAs($manager)->get(route('purchasing.labor.index', [
            'order_no' => 'AA', 'excluded_order_nos' => ['AA200-X01'],
        ]));

        // 除外した注番も「対象注番」プルダウンの選択肢としては表示され続けるため、
        // 除外の確認は集計結果側(労務費合計)で行う。
        $excluded->assertSee('AA100-X01')->assertSee('1 / 2 件を対象')->assertSee('労務費合計: ¥40,000', false);
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

    public function test_cost_analysis_links_to_search_filtered_to_the_same_order_number(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $category = CategoryCode::create(['code' => 3, 'major_category' => '部品', 'is_parts' => true]);
        PurchaseDetail::create([
            'item_code' => 'A1', 'category_id' => $category->id, 'item_name' => '部品X',
            'supplier_name' => '大津屋', 'order_qty' => 2, 'unit_price' => 1000, 'order_amount' => 10000,
            'is_provisional' => false,
        ]);

        $response = $this->actingAs($manager)->get(route('purchasing.cost.index', ['order_no' => 'A1']));

        $response->assertSee('item_code=A1', false)->assertSee('item_code_match=perfect', false);
        $response->assertSee('purchasing/labor?order_no=A1', false)->assertSee('order_no_match=perfect', false);
    }

    public function test_cost_analysis_partial_match_aggregates_multiple_order_numbers(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $category = CategoryCode::create(['code' => 3, 'major_category' => '部品', 'is_parts' => true]);
        PurchaseDetail::create([
            'item_code' => 'DH013-N01', 'category_id' => $category->id, 'item_name' => '部品A',
            'supplier_name' => '大津屋', 'order_qty' => 1, 'unit_price' => 1000, 'order_amount' => 1000,
            'is_provisional' => false,
        ]);
        PurchaseDetail::create([
            'item_code' => 'DH013-N02', 'category_id' => $category->id, 'item_name' => '部品B',
            'supplier_name' => '大津屋', 'order_qty' => 1, 'unit_price' => 2000, 'order_amount' => 2000,
            'is_provisional' => false,
        ]);

        $response = $this->actingAs($manager)->get(route('purchasing.cost.index', [
            'order_no' => 'DH013', 'order_no_match' => 'partial',
        ]));

        // 受注金額1,000+2,000=3,000
        $response->assertSee('2 / 2 件を対象')->assertSee('3,000');
    }

    public function test_cost_analysis_can_exclude_a_matched_order_number(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $category = CategoryCode::create(['code' => 3, 'major_category' => '部品', 'is_parts' => true]);
        PurchaseDetail::create([
            'item_code' => 'DH013-N01', 'category_id' => $category->id, 'item_name' => '対象部品',
            'supplier_name' => '大津屋', 'order_qty' => 1, 'unit_price' => 1000, 'order_amount' => 1000,
            'is_provisional' => false,
        ]);
        PurchaseDetail::create([
            'item_code' => 'DH013-N02', 'category_id' => $category->id, 'item_name' => '除外部品',
            'supplier_name' => '大津屋', 'order_qty' => 1, 'unit_price' => 2000, 'order_amount' => 2000,
            'is_provisional' => false,
        ]);

        $response = $this->actingAs($manager)->get(route('purchasing.cost.index', [
            'order_no' => 'DH013', 'order_no_match' => 'partial',
            'excluded_order_nos' => ['DH013-N02'],
        ]));

        $response->assertSee('1 / 2 件を対象')->assertSee('対象部品')->assertDontSee('除外部品');
    }

    public function test_cost_analysis_breaks_down_labor_cost_by_sub_category(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $worker = Staff::factory()->create(['is_labor_target' => true, 'position_weight' => 1]);

        // コード60は「人工」「機械組付」の2つの細分が同じコード値を共有しているため、
        // sub_categoryで正しく分離表示されるかも合わせて確認する。
        $designCategory = CategoryCode::create(['code' => 63, 'major_category' => '社内人工', 'sub_category' => '機械設計']);
        $laborCategory = CategoryCode::create(['code' => 60, 'major_category' => '社内人工', 'sub_category' => '人工']);
        $assemblyCategory = CategoryCode::create(['code' => 60, 'major_category' => '社内人工', 'sub_category' => '機械組付']);

        LaborCost::create([
            'work_date' => now(), 'staff_id' => $worker->id, 'order_no' => 'A1', 'category_id' => $designCategory->id,
            'work_hours' => 8, 'work_minutes' => 0, 'is_overtime' => false, 'position_weight_cache' => 1, 'is_provisional' => false,
        ]);
        LaborCost::create([
            'work_date' => now(), 'staff_id' => $worker->id, 'order_no' => 'A1', 'category_id' => $laborCategory->id,
            'work_hours' => 4, 'work_minutes' => 0, 'is_overtime' => false, 'position_weight_cache' => 1, 'is_provisional' => false,
        ]);
        LaborCost::create([
            'work_date' => now(), 'staff_id' => $worker->id, 'order_no' => 'A1', 'category_id' => $assemblyCategory->id,
            'work_hours' => 2, 'work_minutes' => 0, 'is_overtime' => false, 'position_weight_cache' => 1, 'is_provisional' => false,
        ]);

        $response = $this->actingAs($manager)->get(route('purchasing.cost.index', ['order_no' => 'A1']));

        // 機械設計: 8h*40,000/8h=40,000、人工: 4h分=20,000、機械組付: 2h分=10,000
        $response->assertSee('機械設計')->assertSee('40,000')
            ->assertSee('人工')->assertSee('20,000')
            ->assertSee('機械組付')->assertSee('10,000');
    }
}
