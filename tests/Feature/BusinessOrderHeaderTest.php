<?php

namespace Tests\Feature;

use App\Models\BusinessOrder;
use App\Models\CategoryCode;
use App\Models\PurchaseDetail;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 受注ヘッダ(business_orders)への分離。
 *
 * 受注先・納入先・受注日・受注金額は仕入の明細行に相乗りしており、「1注番につき金額を持つ行は1つ」
 * という暗黙のルールに依存していた。原価計算・原価一覧は MAX(order_amount)、見積補助だけが
 * SUM(order_amount) で拾っていたため、同じ注番で金額を持つ行が増えると見積補助の売上金額だけが
 * 静かに二重になっていた。受注をヘッダへ分離し、集計は全てヘッダを参照する。
 */
class BusinessOrderHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_estimate_assist_does_not_double_count_when_two_rows_carry_the_amount(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $category = CategoryCode::create(['code' => 3, 'major_category' => '部品', 'is_parts' => true]);

        BusinessOrder::create(['order_no' => 'DUP001-N01', 'order_amount' => 100000]);

        // 明細側にも旧来の列が残っており、同じ注番で2行が金額を持っている状態を再現する。
        foreach ([1000, 2000] as $unitPrice) {
            PurchaseDetail::create([
                'item_code' => 'DUP001-N01', 'category_id' => $category->id, 'item_name' => '部品',
                'order_qty' => 1, 'unit_price' => $unitPrice, 'order_amount' => 100000, 'is_provisional' => false,
            ]);
        }

        $response = $this->actingAs($manager)->get(route('purchasing.estimate.index', [
            'order_no' => 'DUP001-N01', 'order_no_match' => 'perfect',
        ]));

        $response->assertOk();
        // ヘッダの100,000がそのまま出る(以前は明細のSUMで200,000になっていた)。
        $response->assertSee('100,000');
        $response->assertDontSee('200,000');
    }

    public function test_cost_analysis_and_estimate_assist_agree_on_the_order_amount(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $category = CategoryCode::create(['code' => 3, 'major_category' => '部品', 'is_parts' => true]);

        BusinessOrder::create(['order_no' => 'AGREE-N01', 'order_amount' => 50000]);
        PurchaseDetail::create([
            'item_code' => 'AGREE-N01', 'category_id' => $category->id, 'item_name' => '部品',
            'order_qty' => 1, 'unit_price' => 1000, 'is_provisional' => false,
        ]);

        $this->actingAs($manager)->get(route('purchasing.cost.index', ['order_no' => 'AGREE-N01']))
            ->assertOk()->assertSee('50,000');
        $this->actingAs($manager)->get(route('purchasing.estimate.index', ['order_no' => 'AGREE-N01', 'order_no_match' => 'perfect']))
            ->assertOk()->assertSee('50,000');
    }

    /**
     * 受注情報は物件管理ボードの受注登録で受注ヘッダに入れる運用にしたため、
     * 仕入管理のデータ入力画面では受注先・受注日・納入先・受注金額を扱わない。
     */
    public function test_the_input_screen_no_longer_handles_order_information(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $category = CategoryCode::create(['code' => 3, 'major_category' => '部品', 'is_parts' => true]);

        $this->actingAs($manager)->get(route('purchasing.input'))
            ->assertOk()
            ->assertDontSee('受注情報（他社から受注した場合）')
            ->assertDontSee('name="order_amount"', false);

        $this->actingAs($manager)->post(route('purchasing.input.store'), [
            'form_type' => 'purchase',
            'is_provisional' => '0',
            'item_code' => 'NEW001-N01',
            'category_id' => $category->id,
            'item_name' => '近接センサ',
            'order_qty' => 5,
            'unit_price' => 1000,
            'supplier_name' => '大津屋',
            // 送られてきても受注ヘッダは作らない
            'order_amount' => 300000,
        ])->assertRedirect();

        $this->assertDatabaseHas('purchase_details', ['item_code' => 'NEW001-N01']);
        $this->assertNull(BusinessOrder::where('order_no', 'NEW001-N01')->first());
    }

    public function test_editing_the_amount_on_a_detail_updates_the_header(): void
    {
        $manager = Staff::factory()->procurementManager()->create();
        $category = CategoryCode::create(['code' => 3, 'major_category' => '部品', 'is_parts' => true]);

        BusinessOrder::create(['order_no' => 'EDIT001-N01', 'order_amount' => 100000, 'recipient' => '受注先A']);
        $detail = PurchaseDetail::create([
            'item_code' => 'EDIT001-N01', 'category_id' => $category->id, 'item_name' => '部品',
            'order_qty' => 1, 'unit_price' => 1000, 'supplier_name' => '大津屋', 'is_provisional' => false,
        ]);

        $this->actingAs($manager)->put(route('purchasing.update', $detail), [
            'item_code' => 'EDIT001-N01',
            'category_id' => $category->id,
            'item_name' => '部品',
            'order_qty' => 1,
            'unit_price' => 1000,
            'supplier_name' => '大津屋',
            'order_amount' => 250000,
        ])->assertRedirect();

        $header = BusinessOrder::where('order_no', 'EDIT001-N01')->sole();
        $this->assertSame(250000.0, (float) $header->order_amount);
        // 触っていない項目はヘッダの値が残る。
        $this->assertSame('受注先A', $header->recipient);
    }

    public function test_migration_command_moves_order_information_into_headers(): void
    {
        PurchaseDetail::create([
            'item_code' => 'MIG001-N01', 'item_name' => '受注行', 'product_name' => '製品X',
            'recipient' => '受注先X', 'delivery_dest' => '納入先X',
            'order_received_date' => '2024-06-15', 'order_amount' => 800000, 'is_provisional' => false,
        ]);
        PurchaseDetail::create([
            'item_code' => 'MIG001-N01', 'item_name' => '明細', 'order_qty' => 1, 'unit_price' => 500, 'is_provisional' => false,
        ]);
        // 受注情報を持たない注番はヘッダを作らない。
        PurchaseDetail::create(['item_code' => 'MIG002-N01', 'item_name' => '明細のみ', 'is_provisional' => false]);

        $this->artisan('app:migrate-order-headers')->assertSuccessful();

        $header = BusinessOrder::where('order_no', 'MIG001-N01')->sole();
        $this->assertSame('製品X', $header->product_name);
        $this->assertSame('受注先X', $header->recipient);
        $this->assertSame('納入先X', $header->delivery_dest);
        $this->assertSame(800000.0, (float) $header->order_amount);
        $this->assertSame('2024-06-15', $header->order_received_date->format('Y-m-d'));

        $this->assertNull(BusinessOrder::where('order_no', 'MIG002-N01')->first());
    }

    public function test_migration_command_is_idempotent(): void
    {
        PurchaseDetail::create([
            'item_code' => 'MIG003-N01', 'order_received_date' => '2024-06-15', 'order_amount' => 1000, 'is_provisional' => false,
        ]);

        $this->artisan('app:migrate-order-headers')->assertSuccessful();
        $this->artisan('app:migrate-order-headers')->assertSuccessful();

        $this->assertSame(1, BusinessOrder::where('order_no', 'MIG003-N01')->count());
    }
}
