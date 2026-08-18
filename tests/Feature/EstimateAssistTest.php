<?php

namespace Tests\Feature;

use App\Models\CategoryCode;
use App\Models\LaborCost;
use App\Models\PurchaseDetail;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EstimateAssistTest extends TestCase
{
    use RefreshDatabase;

    public function test_procurement_manager_and_sales_can_view_the_page(): void
    {
        $this->actingAs(Staff::factory()->procurementManager()->create())->get(route('purchasing.estimate.index'))->assertOk();
        $this->actingAs(Staff::factory()->sales()->create())->get(route('purchasing.estimate.index'))->assertOk();
    }

    public function test_general_staff_cannot_view_the_page(): void
    {
        $staff = Staff::factory()->create();

        $this->actingAs($staff)->get(route('purchasing.estimate.index'))->assertForbidden();
    }

    public function test_order_no_aggregation_combines_purchase_and_labor_totals(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $worker = Staff::factory()->create(['is_labor_target' => true, 'position_weight' => 1]);

        PurchaseDetail::create([
            'item_code' => 'A1', 'item_name' => '部品X', 'unit_price' => 1000,
            'required_qty' => 5, 'stock_qty' => 2, 'order_qty' => 3, 'order_amount' => 4000,
            'is_provisional' => false,
        ]);
        LaborCost::create([
            'work_date' => now(), 'staff_id' => $worker->id, 'order_no' => 'A1',
            'work_hours' => 8, 'work_minutes' => 0, 'is_overtime' => false, 'position_weight_cache' => 1,
            'is_provisional' => false,
        ]);

        $response = $this->actingAs($manager)->get(route('purchasing.estimate.index', ['order_no' => 'A1', 'order_no_match' => 'perfect']));

        // 価格=1000*5=5,000 注文価格=1000*(5-2)=3,000 受注金額=4,000 労務費=40,000
        $response->assertOk()
            ->assertSee('5,000')
            ->assertSee('3,000')
            ->assertSee('4,000')
            ->assertSee('40,000')
            ->assertSee('部品X')
            ->assertSee($worker->name);
    }

    public function test_excluded_order_no_is_removed_from_aggregation(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        PurchaseDetail::create([
            'item_code' => 'AA100-X01', 'item_name' => '対象品A', 'unit_price' => 1000,
            'required_qty' => 1, 'order_amount' => 1000, 'is_provisional' => false,
        ]);
        PurchaseDetail::create([
            'item_code' => 'AA200-X01', 'item_name' => '除外対象品B', 'unit_price' => 2000,
            'required_qty' => 1, 'order_amount' => 2000, 'is_provisional' => false,
        ]);

        $response = $this->actingAs($manager)->get(route('purchasing.estimate.index', [
            'order_no' => 'AA', 'order_no_match' => 'partial',
            'excluded_order_nos' => ['AA200-X01'],
        ]));

        $response->assertSee('対象品A')->assertDontSee('除外対象品B');
    }

    public function test_category_filter_accepts_multiple_selections(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $partsCategory = CategoryCode::create(['code' => 3, 'major_category' => '部品']);
        $materialCategory = CategoryCode::create(['code' => 4, 'major_category' => '材料']);
        $outsourcingCategory = CategoryCode::create(['code' => 5, 'major_category' => '外注']);

        PurchaseDetail::create([
            'item_code' => 'D3', 'item_name' => '部品行', 'category_id' => $partsCategory->id,
            'unit_price' => 1000, 'required_qty' => 1, 'is_provisional' => false,
        ]);
        PurchaseDetail::create([
            'item_code' => 'D3', 'item_name' => '材料行', 'category_id' => $materialCategory->id,
            'unit_price' => 1000, 'required_qty' => 1, 'is_provisional' => false,
        ]);
        PurchaseDetail::create([
            'item_code' => 'D3', 'item_name' => '外注行', 'category_id' => $outsourcingCategory->id,
            'unit_price' => 1000, 'required_qty' => 1, 'is_provisional' => false,
        ]);

        $response = $this->actingAs($manager)->get(route('purchasing.estimate.index', [
            'order_no' => 'D3', 'order_no_match' => 'perfect',
            'category_id' => [$partsCategory->id, $materialCategory->id],
        ]));

        $response->assertSee('部品行')->assertSee('材料行')->assertDontSee('外注行');
    }

    public function test_aggregation_can_be_narrowed_by_category_manufacturer_item_name_dimensions_and_supplier(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $partsCategory = CategoryCode::create(['code' => 3, 'major_category' => '部品']);
        $otherCategory = CategoryCode::create(['code' => 4, 'major_category' => '材料']);

        PurchaseDetail::create([
            'item_code' => 'D1', 'item_name' => '対象品', 'manufacturer' => 'オムロン', 'dimensions' => 'E2E-X1R5E1',
            'supplier_name' => '大津屋', 'category_id' => $partsCategory->id, 'unit_price' => 1000,
            'required_qty' => 1, 'is_provisional' => false,
        ]);
        PurchaseDetail::create([
            'item_code' => 'D1', 'item_name' => '除外品', 'manufacturer' => '別メーカー', 'dimensions' => '別型式',
            'supplier_name' => '別商社', 'category_id' => $otherCategory->id, 'unit_price' => 2000,
            'required_qty' => 1, 'is_provisional' => false,
        ]);

        $base = ['order_no' => 'D1', 'order_no_match' => 'perfect'];

        $this->actingAs($manager)->get(route('purchasing.estimate.index', [...$base, 'category_id' => $partsCategory->id]))
            ->assertSee('対象品')->assertDontSee('除外品');
        $this->actingAs($manager)->get(route('purchasing.estimate.index', [...$base, 'manufacturer' => 'オムロン']))
            ->assertSee('対象品')->assertDontSee('除外品');
        $this->actingAs($manager)->get(route('purchasing.estimate.index', [...$base, 'item_name' => '対象品']))
            ->assertSee('対象品')->assertDontSee('除外品');
        $this->actingAs($manager)->get(route('purchasing.estimate.index', [...$base, 'dimensions' => 'E2E-X1R5E1']))
            ->assertSee('対象品')->assertDontSee('除外品');
        $this->actingAs($manager)->get(route('purchasing.estimate.index', [...$base, 'supplier_name' => '大津屋']))
            ->assertSee('対象品')->assertDontSee('除外品');
    }

    public function test_labor_aggregation_can_be_narrowed_by_category(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $worker = Staff::factory()->create(['is_labor_target' => true, 'position_weight' => 1]);
        $designCategory = CategoryCode::create(['code' => 63, 'major_category' => '社内人工', 'sub_category' => '機械設計', 'item_name' => '機械設計']);
        $siteCategory = CategoryCode::create(['code' => 64, 'major_category' => '社内人工', 'sub_category' => '現地', 'item_name' => '現地']);

        LaborCost::create([
            'work_date' => now(), 'staff_id' => $worker->id, 'order_no' => 'D2', 'category_id' => $designCategory->id,
            'work_hours' => 8, 'work_minutes' => 0, 'is_overtime' => false, 'position_weight_cache' => 1, 'is_provisional' => false,
        ]);
        LaborCost::create([
            'work_date' => now(), 'staff_id' => $worker->id, 'order_no' => 'D2', 'category_id' => $siteCategory->id,
            'work_hours' => 4, 'work_minutes' => 0, 'is_overtime' => false, 'position_weight_cache' => 1, 'is_provisional' => false,
        ]);

        $response = $this->actingAs($manager)->get(route('purchasing.estimate.index', [
            'order_no' => 'D2', 'order_no_match' => 'perfect', 'category_id' => $designCategory->id,
        ]));

        // 分類フィルターのプルダウン自体には「現地」も選択肢として表示されるため、
        // 除外の確認は集計結果側(人工レコード件数)で行う。
        $response->assertSee('人工レコード（1件）')->assertSee('機械設計');
    }

    public function test_reference_price_search_scores_and_sorts_by_relevance(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        PurchaseDetail::create([
            'item_code' => 'B1', 'item_name' => '近接センサ', 'manufacturer' => 'オムロン', 'dimensions' => 'E2E-X1R5E1',
            'unit_price' => 500, 'order_date' => now()->subYear(), 'is_provisional' => false,
        ]);
        PurchaseDetail::create([
            'item_code' => 'B2', 'item_name' => '近接センサ', 'manufacturer' => '別メーカー', 'dimensions' => '別型式',
            'unit_price' => 800, 'order_date' => now(), 'is_provisional' => false,
        ]);

        $response = $this->actingAs($manager)->get(route('purchasing.estimate.index', [
            'ref_item_name' => '近接センサ',
            'ref_manufacturer' => 'オムロン',
            'ref_sort' => 'relevance',
        ]));

        // 一致度2件のB1(品名+メーカー一致)が、一致度1件のB2(品名のみ一致)より先に表示される。
        // 'B1'/'B2'のような短い文字列でページ全体を検索すると、CSRFトナークンやビルドハッシュに
        // たまたま含まれて誤検出するため、その行にしか出ない値(メーカー名)で比較する。
        $response->assertSeeInOrder(['オムロン', '別メーカー']);
    }

    public function test_reference_price_search_matches_across_katakana_width(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        // 旧Accessデータはメーカー名が半角カタカナで登録されていることが多いため、
        // 全角で入力しても半角データがヒットする必要がある。
        PurchaseDetail::create([
            'item_code' => 'C1', 'item_name' => 'リミットスイッチ', 'manufacturer' => 'ｵﾑﾛﾝ',
            'unit_price' => 500, 'is_provisional' => false,
        ]);

        $response = $this->actingAs($manager)->get(route('purchasing.estimate.index', [
            'ref_manufacturer' => 'オムロン',
        ]));

        $response->assertSee('C1');
    }

    public function test_reference_price_search_filters_by_order_no_and_supplier(): void
    {
        $manager = Staff::factory()->procurementManager()->create();

        PurchaseDetail::create([
            'item_code' => 'D9', 'item_name' => 'センサ', 'supplier_name' => '丸紅商事',
            'unit_price' => 500, 'is_provisional' => false,
        ]);
        PurchaseDetail::create([
            'item_code' => 'D8', 'item_name' => 'センサ', 'supplier_name' => '別商社',
            'unit_price' => 800, 'is_provisional' => false,
        ]);

        // 'D8'のような短い文字列でページ全体を検索すると、CSRFトークンやビルドハッシュに
        // たまたま含まれて誤検出する(実際に落ちたことがある)。その行にしか出ない商社名で見る。
        $byOrderNo = $this->actingAs($manager)->get(route('purchasing.estimate.index', ['ref_item_code' => 'D9']));
        $byOrderNo->assertSee('丸紅商事')->assertDontSee('別商社');

        $bySupplier = $this->actingAs($manager)->get(route('purchasing.estimate.index', ['ref_supplier_name' => '丸紅']));
        $bySupplier->assertSee('丸紅商事')->assertDontSee('別商社');
    }
}
