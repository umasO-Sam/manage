<?php

namespace Tests\Feature;

use App\Models\PurchaseDetail;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseDetailSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_staff_member_can_view_the_search_page(): void
    {
        $staff = Staff::factory()->create();

        $this->actingAs($staff)->get(route('purchasing.index'))->assertOk();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('purchasing.index'))->assertRedirect(route('login'));
    }

    public function test_search_by_item_code_partial_match(): void
    {
        $staff = Staff::factory()->create();
        PurchaseDetail::create(['item_code' => 'ABC123-D01', 'item_name' => '近接センサ']);
        PurchaseDetail::create(['item_code' => 'ZZZ999-D01', 'item_name' => '別部品']);

        $response = $this->actingAs($staff)->get(route('purchasing.index', ['item_code' => 'ABC']));

        $response->assertSee('ABC123-D01')->assertDontSee('ZZZ999-D01');
    }

    public function test_search_by_item_code_perfect_match(): void
    {
        $staff = Staff::factory()->create();
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
        $staff = Staff::factory()->create();
        PurchaseDetail::create(['item_code' => 'ABC123-D01', 'item_name' => 'A始まり']);
        PurchaseDetail::create(['item_code' => 'ZZZ999-D01', 'item_name' => 'Z始まり']);

        $response = $this->actingAs($staff)->get(route('purchasing.index', ['alpha' => ['A']]));

        $response->assertSee('A始まり')->assertDontSee('Z始まり');
    }

    public function test_err_alpha_filter_finds_item_codes_not_starting_with_a_letter(): void
    {
        $staff = Staff::factory()->create();
        PurchaseDetail::create(['item_code' => '123-BAD', 'item_name' => '異常データ']);
        PurchaseDetail::create(['item_code' => 'ABC123-D01', 'item_name' => '正常データ']);

        $response = $this->actingAs($staff)->get(route('purchasing.index', ['alpha' => ['ERR']]));

        $response->assertSee('異常データ')->assertDontSee('正常データ');
    }

    public function test_rows_with_sales_order_information_are_listed_first(): void
    {
        $staff = Staff::factory()->create();
        PurchaseDetail::create(['item_code' => 'AAA111-X01', 'item_name' => '受注なし']);
        PurchaseDetail::create(['item_code' => 'AAA111-X02', 'item_name' => '受注あり', 'recipient' => '株式会社テスト']);

        $response = $this->actingAs($staff)->get(route('purchasing.index'));

        $content = $response->getContent();
        $this->assertLessThan(
            strpos($content, '受注なし'),
            strpos($content, '受注あり')
        );
    }
}
