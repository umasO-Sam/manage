<?php

namespace Tests\Feature;

use App\Models\PurchaseDetail;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseDetailSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_procurement_manager_and_sales_can_view_the_search_page(): void
    {
        $this->actingAs(Staff::factory()->procurementManager()->create())->get(route('purchasing.index'))->assertOk();
        $this->actingAs(Staff::factory()->sales()->create())->get(route('purchasing.index'))->assertOk();
    }

    public function test_general_staff_cannot_view_the_search_page(): void
    {
        $staff = Staff::factory()->create();

        $this->actingAs($staff)->get(route('purchasing.index'))->assertForbidden();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('purchasing.index'))->assertRedirect(route('login'));
    }

    public function test_search_by_item_code_partial_match(): void
    {
        $staff = Staff::factory()->sales()->create();
        PurchaseDetail::create(['item_code' => 'ABC123-D01', 'item_name' => '近接センサ']);
        PurchaseDetail::create(['item_code' => 'ZZZ999-D01', 'item_name' => '別部品']);

        $response = $this->actingAs($staff)->get(route('purchasing.index', ['item_code' => 'ABC']));

        $response->assertSee('ABC123-D01')->assertDontSee('ZZZ999-D01');
    }

    public function test_sales_date_is_shown_and_filterable(): void
    {
        $staff = Staff::factory()->sales()->create();
        PurchaseDetail::create(['item_code' => 'AAA111-X01', 'item_name' => '売上済み', 'order_amount' => 1000, 'sales_date' => '2026-07-29']);
        PurchaseDetail::create(['item_code' => 'AAA111-X02', 'item_name' => '未計上', 'order_amount' => 1000]);

        $response = $this->actingAs($staff)->get(route('purchasing.index', [
            'sales_date_mode' => 'exact', 'sales_date_from' => '2026-07-29',
        ]));

        $response->assertSee('売上済み')->assertDontSee('未計上')->assertSee('2026/07/29');
    }

    public function test_search_by_item_code_perfect_match(): void
    {
        $staff = Staff::factory()->sales()->create();
        PurchaseDetail::create(['item_code' => 'ABC123-D01', 'item_name' => '近接センサ']);
        PurchaseDetail::create(['item_code' => 'ABC123-D02', 'item_name' => '別部品']);

        $response = $this->actingAs($staff)->get(route('purchasing.index', [
            'item_code' => 'ABC123-D01',
            'item_code_match' => 'perfect',
        ]));

        $response->assertSee('ABC123-D01')->assertDontSee('ABC123-D02');
    }

    public function test_alpha_filter_narrows_by_item_code_prefix(): void
    {
        $staff = Staff::factory()->sales()->create();
        PurchaseDetail::create(['item_code' => 'ABC123-D01', 'item_name' => 'A始まり']);
        PurchaseDetail::create(['item_code' => 'ZZZ999-D01', 'item_name' => 'Z始まり']);

        $response = $this->actingAs($staff)->get(route('purchasing.index', ['alpha' => ['A']]));

        $response->assertSee('A始まり')->assertDontSee('Z始まり');
    }

    public function test_err_alpha_filter_finds_item_codes_not_starting_with_a_letter(): void
    {
        $staff = Staff::factory()->sales()->create();
        PurchaseDetail::create(['item_code' => '123-BAD', 'item_name' => '異常データ']);
        PurchaseDetail::create(['item_code' => 'ABC123-D01', 'item_name' => '正常データ']);

        $response = $this->actingAs($staff)->get(route('purchasing.index', ['alpha' => ['ERR']]));

        $response->assertSee('異常データ')->assertDontSee('正常データ');
    }

    public function test_rows_with_sales_order_information_are_listed_first(): void
    {
        $staff = Staff::factory()->sales()->create();
        PurchaseDetail::create(['item_code' => 'AAA111-X01', 'item_name' => '受注なし']);
        PurchaseDetail::create(['item_code' => 'AAA111-X02', 'item_name' => '受注あり', 'recipient' => '株式会社テスト']);

        $response = $this->actingAs($staff)->get(route('purchasing.index'));

        $content = $response->getContent();
        $this->assertLessThan(
            strpos($content, '受注なし'),
            strpos($content, '受注あり')
        );
    }

    public function test_search_results_show_computed_price_and_order_price(): void
    {
        $staff = Staff::factory()->sales()->create();
        PurchaseDetail::create([
            'item_code' => 'AAA111-X01', 'item_name' => '価格計算対象',
            'unit_price' => 1000, 'required_qty' => 5, 'stock_qty' => 2,
        ]);

        $response = $this->actingAs($staff)->get(route('purchasing.index', ['item_code' => 'AAA111-X01']));

        // 価格 = 単価1,000 × 必要数量5 = 5,000
        // 注文価格 = 単価1,000 × (必要数量5 - 在庫2) = 3,000
        $response->assertSee('5,000')->assertSee('3,000');
    }
}
