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

    public function test_edit_link_from_a_filtered_search_carries_the_filters(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $detail = PurchaseDetail::create(['item_code' => 'AAA111-X01', 'item_name' => '対象品']);

        $response = $this->actingAs($manager)->get(route('purchasing.index', ['item_code' => 'AAA111']));

        $response->assertSee('return_query=item_code%3DAAA111', false);
    }

    public function test_updating_a_record_redirects_back_to_the_search_with_filters_preserved(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $category = \App\Models\CategoryCode::create(['code' => 1, 'major_category' => '部品']);
        $detail = PurchaseDetail::create([
            'item_code' => 'AAA111-X01', 'item_name' => '対象品', 'manufacturer' => 'メーカーA',
            'category_id' => $category->id, 'order_qty' => 1, 'unit_price' => 100, 'supplier_name' => '商社A',
        ]);

        $response = $this->actingAs($manager)->put(route('purchasing.update', $detail), [
            'item_code' => 'AAA111-X01', 'item_name' => '更新後品名', 'manufacturer' => 'メーカーA',
            'category_id' => $category->id, 'order_qty' => 1, 'unit_price' => 100, 'supplier_name' => '商社A',
            'return_query' => 'item_code=AAA111&item_code_match=partial',
        ]);

        $response->assertRedirect(route('purchasing.index').'?item_code=AAA111&item_code_match=partial');
    }

    public function test_updating_a_record_without_return_query_redirects_to_the_plain_search_page(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $category = \App\Models\CategoryCode::create(['code' => 1, 'major_category' => '部品']);
        $detail = PurchaseDetail::create([
            'item_code' => 'AAA111-X01', 'item_name' => '対象品', 'manufacturer' => 'メーカーA',
            'category_id' => $category->id, 'order_qty' => 1, 'unit_price' => 100, 'supplier_name' => '商社A',
        ]);

        $response = $this->actingAs($manager)->put(route('purchasing.update', $detail), [
            'item_code' => 'AAA111-X01', 'item_name' => '更新後品名', 'manufacturer' => 'メーカーA',
            'category_id' => $category->id, 'order_qty' => 1, 'unit_price' => 100, 'supplier_name' => '商社A',
        ]);

        $response->assertRedirect(route('purchasing.index'));
    }

    public function test_purchase_detail_can_be_updated_without_manufacturer(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $category = \App\Models\CategoryCode::create(['code' => 1, 'major_category' => '部品']);
        $detail = PurchaseDetail::create([
            'item_code' => 'AAA111-X01', 'item_name' => '対象品', 'manufacturer' => 'メーカーA',
            'category_id' => $category->id, 'order_qty' => 1, 'unit_price' => 100, 'supplier_name' => '商社A',
        ]);

        $response = $this->actingAs($manager)->put(route('purchasing.update', $detail), [
            'item_code' => 'AAA111-X01', 'item_name' => '更新後品名', 'manufacturer' => '',
            'category_id' => $category->id, 'order_qty' => 1, 'unit_price' => 100, 'supplier_name' => '商社A',
        ]);

        $response->assertSessionDoesntHaveErrors('manufacturer');
        $response->assertRedirect(route('purchasing.index'));
        $this->assertNull($detail->fresh()->manufacturer);
    }

    public function test_direct_edit_button_is_only_shown_to_procurement_managers(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $sales = Staff::factory()->sales()->create();

        $this->actingAs($manager)->get(route('purchasing.index'))->assertSee('直接編集');
        $this->actingAs($sales)->get(route('purchasing.index'))->assertDontSee('直接編集');
    }

    public function test_bulk_update_saves_changed_fields_for_multiple_records(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $category = \App\Models\CategoryCode::create(['code' => 1, 'major_category' => '部品']);
        $newCategory = \App\Models\CategoryCode::create(['code' => 2, 'major_category' => '材料']);
        $detailA = PurchaseDetail::create([
            'item_code' => 'BLK001-N01', 'item_name' => '対象A', 'manufacturer' => 'メーカーA',
            'category_id' => $category->id, 'order_qty' => 1, 'unit_price' => 100, 'supplier_name' => '商社A',
        ]);
        $detailB = PurchaseDetail::create([
            'item_code' => 'BLK002-N01', 'item_name' => '対象B', 'manufacturer' => 'メーカーB',
            'category_id' => $category->id, 'order_qty' => 1, 'unit_price' => 200, 'supplier_name' => '商社B',
        ]);

        $response = $this->actingAs($manager)->post(route('purchasing.bulk-update'), [
            'updates' => [
                $detailA->id => ['item_code' => 'BLK001-N01', 'item_name' => '更新後A', 'unit_price' => 150, 'category_id' => $newCategory->id],
                $detailB->id => ['item_code' => 'BLK002-N01', 'item_name' => '更新後B', 'unit_price' => 250],
            ],
            'return_query' => 'item_code=BLK',
        ]);

        $response->assertRedirect(route('purchasing.index').'?item_code=BLK');
        $this->assertSame('更新後A', $detailA->fresh()->item_name);
        $this->assertSame('150.00', $detailA->fresh()->unit_price);
        $this->assertSame($newCategory->id, $detailA->fresh()->category_id);
        $this->assertSame('更新後B', $detailB->fresh()->item_name);
        $this->assertSame('250.00', $detailB->fresh()->unit_price);
    }

    public function test_bulk_update_handles_the_is_provisional_checkbox_correctly_when_unchecked(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $detail = PurchaseDetail::create([
            'item_code' => 'BLK003-N01', 'item_name' => '対象', 'is_provisional' => true,
        ]);

        // HTMLのチェックボックスは未チェック時に値が送信されないため、is_provisionalキー自体を省略する。
        $this->actingAs($manager)->post(route('purchasing.bulk-update'), [
            'updates' => [
                $detail->id => ['item_code' => 'BLK003-N01', 'item_name' => '対象'],
            ],
        ]);

        $this->assertFalse($detail->fresh()->is_provisional);
    }

    public function test_bulk_update_rejects_blanking_out_item_code(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $detail = PurchaseDetail::create(['item_code' => 'BLK004-N01', 'item_name' => '対象']);

        $this->actingAs($manager)->post(route('purchasing.bulk-update'), [
            'updates' => [
                $detail->id => ['item_code' => '', 'item_name' => '変更後'],
            ],
        ])->assertSessionHasErrors();

        $this->assertSame('BLK004-N01', $detail->fresh()->item_code);
        $this->assertSame('対象', $detail->fresh()->item_name);
    }

    public function test_sales_role_cannot_bulk_update(): void
    {
        $sales = Staff::factory()->sales()->create();
        $detail = PurchaseDetail::create(['item_code' => 'BLK005-N01', 'item_name' => '対象']);

        $this->actingAs($sales)->post(route('purchasing.bulk-update'), [
            'updates' => [$detail->id => ['item_code' => 'BLK005-N01', 'item_name' => '変更後']],
        ])->assertForbidden();

        $this->assertSame('対象', $detail->fresh()->item_name);
    }

    public function test_procurement_manager_can_delete_a_record_from_the_edit_screen(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $detail = PurchaseDetail::create(['item_code' => 'DEL001-N01', 'item_name' => '削除対象']);

        $response = $this->actingAs($manager)->delete(route('purchasing.destroy', $detail));

        $response->assertRedirect(route('purchasing.index'));
        $this->assertDatabaseMissing('purchase_details', ['id' => $detail->id]);
    }

    public function test_delete_redirects_back_to_the_search_with_filters_preserved(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $detail = PurchaseDetail::create(['item_code' => 'DEL002-N01', 'item_name' => '削除対象']);

        $response = $this->actingAs($manager)->delete(route('purchasing.destroy', $detail), [
            'return_query' => 'item_code=DEL002&item_code_match=partial',
        ]);

        $response->assertRedirect(route('purchasing.index').'?item_code=DEL002&item_code_match=partial');
    }

    public function test_sales_role_cannot_delete_a_record(): void
    {
        $sales = Staff::factory()->sales()->create();
        $detail = PurchaseDetail::create(['item_code' => 'DEL003-N01', 'item_name' => '対象']);

        $this->actingAs($sales)->delete(route('purchasing.destroy', $detail))->assertForbidden();

        $this->assertDatabaseHas('purchase_details', ['id' => $detail->id]);
    }

    public function test_procurement_manager_can_bulk_delete_selected_records(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $target1 = PurchaseDetail::create(['item_code' => 'BDEL01-N01', 'item_name' => '削除対象1']);
        $target2 = PurchaseDetail::create(['item_code' => 'BDEL02-N01', 'item_name' => '削除対象2']);
        $kept = PurchaseDetail::create(['item_code' => 'BDEL03-N01', 'item_name' => '残す']);

        $response = $this->actingAs($manager)->post(route('purchasing.bulk-delete'), [
            'ids' => [$target1->id, $target2->id],
        ]);

        $response->assertRedirect(route('purchasing.index'));
        $this->assertDatabaseMissing('purchase_details', ['id' => $target1->id]);
        $this->assertDatabaseMissing('purchase_details', ['id' => $target2->id]);
        $this->assertDatabaseHas('purchase_details', ['id' => $kept->id]);
    }

    public function test_bulk_delete_redirects_back_to_the_search_with_filters_preserved(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $target = PurchaseDetail::create(['item_code' => 'BDEL04-N01', 'item_name' => '削除対象']);

        $response = $this->actingAs($manager)->post(route('purchasing.bulk-delete'), [
            'ids' => [$target->id],
            'return_query' => 'item_code=BDEL04&item_code_match=partial',
        ]);

        $response->assertRedirect(route('purchasing.index').'?item_code=BDEL04&item_code_match=partial');
    }

    public function test_sales_role_cannot_bulk_delete(): void
    {
        $sales = Staff::factory()->sales()->create();
        $detail = PurchaseDetail::create(['item_code' => 'BDEL05-N01', 'item_name' => '対象']);

        $this->actingAs($sales)->post(route('purchasing.bulk-delete'), [
            'ids' => [$detail->id],
        ])->assertForbidden();

        $this->assertDatabaseHas('purchase_details', ['id' => $detail->id]);
    }

    public function test_delete_button_is_only_shown_to_procurement_managers_on_edit_screen(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $detail = PurchaseDetail::create(['item_code' => 'DEL006-N01', 'item_name' => '対象']);

        $this->actingAs($manager)->get(route('purchasing.edit', $detail))->assertSee('このレコードを削除する');
    }

    public function test_bulk_delete_button_is_only_shown_to_procurement_managers(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $sales = Staff::factory()->sales()->create();

        $this->actingAs($manager)->get(route('purchasing.index'))->assertSee('まとめて削除');
        $this->actingAs($sales)->get(route('purchasing.index'))->assertDontSee('まとめて削除');
    }
}
