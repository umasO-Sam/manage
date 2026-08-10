<?php

namespace Tests\Feature;

use App\Models\BusinessOrder;
use App\Models\BusinessOrderLog;
use App\Models\BusinessPartner;
use App\Models\Card;
use App\Models\OrderNumber;
use App\Models\PurchaseDetail;
use App\Models\Staff;
use App\Models\WorkflowType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 物件管理ボード。受注1件＝カード1枚＝受注ヘッダ1件。
 * ステージ移動の条件は workflow_types.stage_definition の requires に持たせている。
 */
class ProjectBoardTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): Staff
    {
        return Staff::factory()->procurementManager()->create();
    }

    private function fundManager(): Staff
    {
        return Staff::factory()->create(['is_fund_manager' => true]);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return [
            'order_no' => 'PJ001-N01',
            'product_name' => '搬送装置',
            'delivery_dest' => '第一工場',
            'order_received_date' => '2026-08-01',
            'order_amount' => 3000000,
            'staff_id' => Staff::factory()->create(['role' => Staff::ROLE_SALES])->id,
            ...$overrides,
        ];
    }

    private function createCard(Staff $actor, array $overrides = []): Card
    {
        $this->actingAs($actor)->post(route('projects.store'), $this->payload([
            'is_new_partner' => '1',
            'new_partner_name' => '新規商事',
            ...$overrides,
        ]))->assertRedirect();

        return Card::latest('id')->firstOrFail();
    }

    public function test_creating_a_card_also_creates_the_order_header_the_order_number_and_a_provisional_partner(): void
    {
        $card = $this->createCard($this->manager());

        $order = $card->businessOrder;
        $this->assertSame('PJ001-N01', $order->order_no);
        $this->assertSame('搬送装置', $order->product_name);
        $this->assertSame(3000000.0, (float) $order->order_amount);

        // 件名は注番マスタの工事名にも同期する。
        $this->assertSame('搬送装置', OrderNumber::where('code', 'PJ001-N01')->sole()->project_name);

        // 新規取引先は仮登録になる。
        $partner = BusinessPartner::where('name', '新規商事')->sole();
        $this->assertTrue($partner->is_provisional);
        $this->assertSame($partner->id, $order->business_partner_id);

        $this->assertSame(BusinessOrderLog::ACTION_CREATED, $order->logs->first()->action);
    }

    /**
     * 移行期間中は、仕入管理から引き継いだ受注(＝すでに受注ヘッダがある案件)を
     * 物件カードにする必要がある。注番の重複チェックに掛けず、受注ヘッダは
     * 作り直さずに更新する。
     */
    private function migratedOrder(array $overrides = []): BusinessOrder
    {
        return BusinessOrder::create([
            'order_no' => 'OLD001-N01',
            'product_name' => '既存の搬送装置',
            'recipient' => '既存商事',
            'delivery_dest' => '旧工場',
            'order_received_date' => '2025-04-01',
            'order_amount' => 1500000,
            ...$overrides,
        ]);
    }

    public function test_an_existing_order_can_be_turned_into_a_card(): void
    {
        $existing = $this->migratedOrder();
        $staff = Staff::factory()->create(['role' => Staff::ROLE_SALES]);

        $this->actingAs($this->manager())->post(route('projects.store'), [
            'business_order_id' => $existing->id,
            'order_no' => 'OLD001-N01',
            'product_name' => '既存の搬送装置',
            'delivery_dest' => '旧工場',
            'order_received_date' => '2025/04/01',
            'order_amount' => 1500000,
            'staff_id' => $staff->id,
            'is_new_partner' => '1',
            'new_partner_name' => '既存商事',
        ])->assertRedirect();

        // 受注ヘッダは増やさず、既存のものに取引先と担当者が入る。
        $this->assertSame(1, BusinessOrder::count());
        $existing->refresh();
        $this->assertSame($staff->id, $existing->staff_id);
        $this->assertNotNull($existing->business_partner_id);
        $this->assertSame('既存商事', $existing->businessPartner->name);

        // カードは作られ、注番マスタにも登録される。
        $card = Card::latest('id')->sole();
        $this->assertSame($existing->id, $card->business_order_id);
        $this->assertSame('OLD001-N01', OrderNumber::sole()->code);
    }

    public function test_an_existing_order_keeps_its_purchase_details(): void
    {
        $existing = $this->migratedOrder();
        $originalId = $existing->id;

        $this->actingAs($this->manager())->post(route('projects.store'), [
            'business_order_id' => $existing->id,
            'product_name' => '既存の搬送装置',
            'delivery_dest' => '旧工場',
            'order_received_date' => '2025/04/01',
            'order_amount' => 1500000,
            'staff_id' => Staff::factory()->create()->id,
            'is_new_partner' => '1',
            'new_partner_name' => '既存商事',
        ])->assertRedirect();

        // idが変わっていない＝ぶら下がっている仕入明細との紐付きが切れていない。
        $this->assertSame($originalId, Card::latest('id')->sole()->business_order_id);
    }

    public function test_an_existing_order_that_already_has_a_card_is_rejected(): void
    {
        $existing = $this->migratedOrder();
        $this->createCard($this->manager(), ['order_no' => 'PJ777-N01']);
        // 別カードを既存受注に紐づけて、カード済みの状態を作る。
        Card::latest('id')->sole()->update(['business_order_id' => $existing->id]);

        $this->actingAs($this->manager())->post(route('projects.store'), [
            'business_order_id' => $existing->id,
            'product_name' => '既存の搬送装置',
            'delivery_dest' => '旧工場',
            'order_received_date' => '2025/04/01',
            'order_amount' => 1500000,
            'staff_id' => Staff::factory()->create()->id,
            'is_new_partner' => '1',
            'new_partner_name' => '別商事',
        ])->assertSessionHasErrors('business_order_id');
    }

    public function test_the_order_search_lists_orders_without_a_card(): void
    {
        $this->migratedOrder();
        $this->migratedOrder(['order_no' => 'OLD002-N01', 'product_name' => '別の装置']);

        $response = $this->actingAs($this->manager())
            ->getJson(route('projects.orders.search', ['q' => 'OLD']));

        $response->assertOk();
        $orders = collect($response->json('orders'));
        $this->assertSame(['OLD002-N01', 'OLD001-N01'], $orders->pluck('order_no')->sort()->reverse()->values()->all());
        $this->assertNull($orders->firstWhere('order_no', 'OLD001-N01')['card_id']);
    }

    /**
     * カード済みの受注も検索には出す。除外すると「受注が無い」のか「すでに
     * 登録済み」なのかが画面で区別できず、原因を探せなくなるため。
     */
    public function test_the_order_search_marks_orders_that_already_have_a_card(): void
    {
        $withCard = $this->createCard($this->manager(), ['order_no' => 'PJ900-N01']);

        $response = $this->actingAs($this->manager())
            ->getJson(route('projects.orders.search', ['q' => 'PJ900']));

        $response->assertOk();
        $found = collect($response->json('orders'))->firstWhere('order_no', 'PJ900-N01');
        $this->assertNotNull($found, 'カード済みの受注も検索結果に出ること');
        $this->assertSame($withCard->id, $found['card_id']);
        $this->assertFalse($found['card_hidden']);
    }

    public function test_a_hidden_card_still_counts_as_registered(): void
    {
        $card = $this->createCard($this->fundManager(), ['order_no' => 'PJ901-N01']);
        $order = $card->businessOrder;
        $card->delete(); // 非表示にする

        // 検索では「登録済み（非表示）」として出る。
        $found = collect($this->actingAs($this->manager())
            ->getJson(route('projects.orders.search', ['q' => 'PJ901']))->json('orders'))
            ->firstWhere('order_no', 'PJ901-N01');
        $this->assertSame($card->id, $found['card_id']);
        $this->assertTrue($found['card_hidden']);

        // 非表示にしただけの受注を、もう一度カード化できてはいけない。
        $this->actingAs($this->manager())->post(route('projects.store'), [
            'business_order_id' => $order->id,
            'product_name' => '二重登録',
            'delivery_dest' => '工場',
            'order_received_date' => '2025/04/01',
            'order_amount' => 100,
            'staff_id' => Staff::factory()->create()->id,
            'is_new_partner' => '1',
            'new_partner_name' => '二重商事',
        ])->assertSessionHasErrors('business_order_id');
    }

    public function test_the_order_search_needs_a_keyword(): void
    {
        $this->migratedOrder();

        $this->actingAs($this->manager())
            ->getJson(route('projects.orders.search', ['q' => '']))
            ->assertOk()
            ->assertJsonCount(0, 'orders');
    }

    /**
     * 受注ヘッダが無い注番は、受注ヘッダ代わりに使っていた仕入明細から拾う。
     * 明細は受注情報が入っている行と空の行が混在し、行によって値が違うこともある。
     */
    public function test_an_order_number_only_in_the_purchase_details_is_offered(): void
    {
        PurchaseDetail::create(['item_code' => 'LEG001-N01']); // 受注情報が空の行
        PurchaseDetail::create([
            'item_code' => 'LEG001-N01', 'product_name' => 'レガシー装置',
            'recipient' => '旧商事', 'delivery_dest' => 'JRA',
            'order_received_date' => '2025-12-15', 'order_amount' => 22352000,
        ]);
        PurchaseDetail::create([
            'item_code' => 'LEG001-N01', 'product_name' => 'レガシー装置',
            'recipient' => '旧商事', 'delivery_dest' => '第二工場',
            'order_received_date' => '2025-12-15', 'order_amount' => 22352000,
        ]);
        PurchaseDetail::create([
            'item_code' => 'LEG001-N01', 'product_name' => 'レガシー装置',
            'recipient' => '旧商事', 'delivery_dest' => '第二工場',
            'order_received_date' => '2025-12-15', 'order_amount' => 22352000,
        ]);

        $found = collect($this->actingAs($this->manager())
            ->getJson(route('projects.orders.search', ['q' => 'LEG001']))->json('orders'))
            ->firstWhere('order_no', 'LEG001-N01');

        $this->assertSame('detail', $found['source']);
        $this->assertNull($found['business_order_id'], '受注ヘッダが無いので新規登録扱いになること');
        $this->assertSame('レガシー装置', $found['product_name']);
        $this->assertSame('旧商事', $found['recipient']);
        $this->assertSame('2025/12/15', $found['order_received_date']);
        $this->assertSame('22352000.00', $found['order_amount']);
        $this->assertSame(4, $found['detail_count']);
        // 納入先は行によって違う。多い方(第二工場が2行)を代表にする。
        $this->assertSame('第二工場', $found['delivery_dest']);
    }

    public function test_the_purchase_details_do_not_duplicate_an_existing_order_header(): void
    {
        $this->migratedOrder(['order_no' => 'DUP001-N01']);
        PurchaseDetail::create(['item_code' => 'DUP001-N01', 'product_name' => '明細側の名前']);

        $rows = collect($this->actingAs($this->manager())
            ->getJson(route('projects.orders.search', ['q' => 'DUP001']))->json('orders'))
            ->where('order_no', 'DUP001-N01');

        $this->assertCount(1, $rows, '受注ヘッダがある注番は明細側から重ねて出さない');
        $this->assertSame('order', $rows->first()['source']);
    }

    /**
     * 受注より先に見積番号を採番し、注番管理に登録してから受注が決まることがある。
     * 注番マスタに既にあっても、明細から選んで受注登録できなければならない。
     */
    public function test_a_purchase_detail_whose_order_number_is_already_in_the_master_can_still_be_picked(): void
    {
        PurchaseDetail::create(['item_code' => 'TAKEN01-N01', 'product_name' => '既に採番済み']);
        OrderNumber::create(['code' => 'TAKEN01-N01']);

        $found = collect($this->actingAs($this->manager())
            ->getJson(route('projects.orders.search', ['q' => 'TAKEN01']))->json('orders'))
            ->firstWhere('order_no', 'TAKEN01-N01');

        $this->assertNotNull($found, '注番マスタにあっても候補から外さない');
        $this->assertSame('既に採番済み', $found['product_name']);
    }

    public function test_a_card_can_be_created_from_a_purchase_detail_order_number(): void
    {
        PurchaseDetail::create([
            'item_code' => 'LEG002-N01', 'product_name' => 'レガシー装置B',
            'recipient' => '旧商事', 'delivery_dest' => '第二工場',
            'order_received_date' => '2025-12-15', 'order_amount' => 500000,
        ]);

        // 明細から拾った内容をそのまま送る(business_order_id は無い＝新規登録)。
        $this->actingAs($this->manager())->post(route('projects.store'), [
            'order_no' => 'LEG002-N01',
            'product_name' => 'レガシー装置B',
            'delivery_dest' => '第二工場',
            'order_received_date' => '2025/12/15',
            'order_amount' => 500000,
            'staff_id' => Staff::factory()->create()->id,
            'is_new_partner' => '1',
            'new_partner_name' => '旧商事',
        ])->assertRedirect();

        $order = BusinessOrder::where('order_no', 'LEG002-N01')->sole();
        $this->assertSame('レガシー装置B', $order->product_name);
        $this->assertSame('2025-12-15', $order->order_received_date->format('Y-m-d'));
        $this->assertNotNull($order->card);
        $this->assertSame('LEG002-N01', OrderNumber::where('code', 'LEG002-N01')->sole()->code);
    }

    /**
     * 受注前に採番して注番管理に登録しておく流れがあるため、注番マスタに既にある
     * 注番でも受注登録できる。マスタは作り直さず同じレコードを使い回す。
     */
    public function test_an_order_number_that_is_already_in_the_master_is_reused(): void
    {
        $existing = OrderNumber::create(['code' => 'PJ001-N01', 'project_name' => '採番時の工事名']);

        $card = $this->createCard($this->manager());

        $this->assertSame(1, OrderNumber::where('code', 'PJ001-N01')->count(), '注番マスタを二重に作らない');
        $this->assertSame($existing->id, $card->order_number_id);
        // 件名は受注ヘッダを正とするため、採番時の工事名は受注の件名で上書きする。
        $this->assertSame('搬送装置', $existing->fresh()->project_name);
    }

    /** 英字の大小で注番マスタが2件に分かれないこと。 */
    public function test_a_lowercase_order_number_is_stored_in_uppercase(): void
    {
        OrderNumber::create(['code' => 'PJ001-N01']);

        $this->createCard($this->manager(), ['order_no' => 'pj001-n01']);

        $this->assertSame(1, OrderNumber::where('code', 'PJ001-N01')->count());
        $this->assertSame('PJ001-N01', BusinessOrder::sole()->order_no);
    }

    /**
     * 過去の注番の大半は注番マスタに存在せず受注ヘッダにしかないため、
     * 受注ヘッダ側も見ないと重複がすり抜ける。
     */
    public function test_an_order_number_that_exists_only_in_the_order_headers_is_rejected(): void
    {
        BusinessOrder::create(['order_no' => 'PJ001-N01', 'order_amount' => 100]);

        $this->actingAs($this->manager())
            ->post(route('projects.store'), $this->payload(['is_new_partner' => '1', 'new_partner_name' => 'A社']))
            ->assertSessionHasErrors('order_no');
    }

    public function test_the_order_number_format_is_checked_unless_bypassed(): void
    {
        $this->actingAs($this->manager())
            ->post(route('projects.store'), $this->payload(['order_no' => '日本語注番', 'is_new_partner' => '1', 'new_partner_name' => 'A社']))
            ->assertSessionHasErrors('order_no');

        $this->actingAs($this->manager())
            ->post(route('projects.store'), $this->payload([
                'order_no' => '日本語注番', 'bypass_order_no_format' => '1',
                'is_new_partner' => '1', 'new_partner_name' => 'A社',
            ]))->assertRedirect();
    }

    public function test_the_first_move_needs_nothing_and_returns_to_the_board(): void
    {
        $card = $this->createCard($this->manager());

        $this->actingAs($this->manager())->post(route('projects.advance', $card))
            ->assertRedirect(route('projects.index'));

        $this->assertSame(1, $card->fresh()->current_stage);
    }

    public function test_the_history_screen_lists_hidden_cards_too(): void
    {
        $manager = $this->manager();
        $visible = $this->createCard($manager);
        $hidden = $this->createCard($manager, ['order_no' => 'PJ009-N01', 'new_partner_name' => '別商事']);
        $hidden->update(['current_stage' => 5]);
        $this->actingAs($this->fundManager())->delete(route('projects.hide', $hidden));

        $response = $this->actingAs($manager)->get(route('projects.history'));
        $response->assertOk()
            ->assertSee($visible->businessOrder->order_no)
            ->assertSee('PJ009-N01')
            ->assertSee('非表示');

        // 非表示だけに絞り込める
        $this->actingAs($manager)->get(route('projects.history', ['hidden' => 1]))
            ->assertOk()
            ->assertSee('PJ009-N01')
            ->assertDontSee($visible->businessOrder->order_no);
    }

    public function test_moving_to_shipped_requires_a_completion_proof_and_a_sales_date(): void
    {
        Storage::fake('local');
        $manager = $this->manager();
        $card = $this->createCard($manager);
        $card->update(['current_stage' => 1]);

        // 添付も売上日も無いので進めない
        $this->actingAs($manager)->post(route('projects.advance', $card))->assertSessionHasErrors('stage');
        $this->assertSame(1, $card->fresh()->current_stage);

        // 完了確認書を添付
        $this->actingAs($manager)->post(route('projects.attachments.store', $card), [
            'file' => UploadedFile::fake()->create('完了確認書.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        // 売上日がまだ無いので進めない
        $this->actingAs($manager)->post(route('projects.advance', $card))->assertSessionHasErrors('stage');

        $card->businessOrder->update(['sales_date' => '2026-08-05']);

        $this->actingAs($manager)->post(route('projects.advance', $card))->assertRedirect();
        $this->assertSame(2, $card->fresh()->current_stage);
    }

    public function test_moving_to_invoiced_is_blocked_while_the_partner_terms_are_pending(): void
    {
        Storage::fake('local');
        $manager = $this->manager();
        $card = $this->createCard($manager);
        $card->update(['current_stage' => 3]);
        // 請求済チェックは入れておく(足りないのは取引条件だけの状態にする)
        $card->businessOrder->update(['invoice_confirmed' => true]);

        $this->actingAs($manager)->post(route('projects.advance', $card))->assertSessionHasErrors('stage');
        $this->assertSame(3, $card->fresh()->current_stage);

        // 資金管理者が取引条件を確定すると進めるようになる
        $partner = $card->businessOrder->businessPartner;
        $this->actingAs($this->fundManager())->put(route('business-partners.update', $partner), [
            'name' => $partner->name, 'bank' => 'A銀行', 'transaction_type' => '振込',
            'closing_day' => '月末', 'payment_terms' => '翌月末',
        ])->assertRedirect();
        $this->actingAs($this->fundManager())->post(route('business-partners.confirm', $partner))->assertRedirect();

        $this->assertFalse($partner->fresh()->is_provisional);

        $this->actingAs($manager)->post(route('projects.advance', $card))->assertRedirect();
        $this->assertSame(4, $card->fresh()->current_stage);
    }

    public function test_trade_terms_cannot_be_confirmed_until_all_four_fields_are_filled(): void
    {
        $card = $this->createCard($this->manager());
        $partner = $card->businessOrder->businessPartner;

        $this->actingAs($this->fundManager())->post(route('business-partners.confirm', $partner))
            ->assertSessionHasErrors('confirm');

        $this->assertTrue($partner->fresh()->is_provisional);
    }

    /**
     * バッジは取引先の状態から導出するため、同じ新規取引先の2枚目のカードにもバッジが出る。
     */
    public function test_the_pending_badge_follows_the_partner_not_the_card(): void
    {
        $manager = $this->manager();
        $first = $this->createCard($manager);
        $partner = $first->businessOrder->businessPartner;

        $this->actingAs($manager)->post(route('projects.store'), $this->payload([
            'order_no' => 'PJ002-N01',
            'business_partner_id' => $partner->id,
        ]))->assertRedirect();

        $second = Card::latest('id')->firstOrFail();

        $this->assertTrue($first->businessOrder->isTradeTermsPending());
        $this->assertTrue($second->businessOrder->fresh()->isTradeTermsPending());
    }

    public function test_only_fund_managers_can_hide_a_paid_card(): void
    {
        $manager = $this->manager();
        $card = $this->createCard($manager);
        $card->update(['current_stage' => 5]);

        // 経理資材担当(資金管理者ではない)は非表示にできない
        $plainManager = Staff::factory()->create(['role' => Staff::ROLE_PROCUREMENT_MANAGER]);
        $this->actingAs($plainManager)->delete(route('projects.hide', $card))->assertForbidden();

        $this->actingAs($this->fundManager())->delete(route('projects.hide', $card))->assertRedirect();

        $this->assertSoftDeleted('cards', ['id' => $card->id]);
        $this->assertDatabaseHas('business_orders', ['id' => $card->business_order_id]);
    }

    public function test_a_card_cannot_be_hidden_before_the_final_stage(): void
    {
        $card = $this->createCard($this->manager());

        $this->actingAs($this->fundManager())->delete(route('projects.hide', $card))->assertSessionHasErrors('stage');
    }

    public function test_general_staff_cannot_open_the_board(): void
    {
        $this->actingAs(Staff::factory()->create())->get(route('projects.index'))->assertForbidden();
    }

    public function test_only_fund_managers_can_open_the_partner_list(): void
    {
        $this->actingAs($this->fundManager())->get(route('business-partners.index'))->assertOk();
        $this->actingAs(Staff::factory()->create(['role' => Staff::ROLE_PROCUREMENT_MANAGER]))
            ->get(route('business-partners.index'))->assertForbidden();
    }

    public function test_project_cards_are_excluded_from_the_retention_batches(): void
    {
        $card = $this->createCard($this->manager());
        $card->update(['current_stage' => 5]);

        // 自動アーカイブされない(retention_days が null のため)
        $this->artisan('app:archive-completed-cards')->assertSuccessful();
        $this->assertNull($card->fresh()->deleted_at);

        // 非表示(アーカイブ)にしても5年削除の対象外
        $card->delete();
        $card->update(['deleted_at' => now()->subYears(6)]);

        $this->artisan('app:purge-archived-cards')->assertSuccessful();
        $this->assertNotNull(Card::withTrashed()->find($card->id));
    }

    public function test_the_board_workflow_has_six_stages(): void
    {
        $workflow = WorkflowType::where('slug', 'project')->sole();

        $this->assertSame(
            ['受注', '線表反映済', '部品発送・検収済', '納品書送付済', '請求済', '入金済'],
            array_column($workflow->stage_definition, 'label')
        );
        $this->assertNull($workflow->retention_days);
    }
}
