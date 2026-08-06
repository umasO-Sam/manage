<?php

namespace Tests\Feature;

use App\Models\BusinessOrder;
use App\Models\BusinessOrderLog;
use App\Models\BusinessPartner;
use App\Models\Card;
use App\Models\OrderNumber;
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

    public function test_an_order_number_that_already_exists_is_rejected(): void
    {
        OrderNumber::create(['code' => 'PJ001-N01']);

        $this->actingAs($this->manager())
            ->post(route('projects.store'), $this->payload(['is_new_partner' => '1', 'new_partner_name' => 'A社']))
            ->assertSessionHasErrors('order_no');
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

    public function test_the_first_move_needs_nothing(): void
    {
        $card = $this->createCard($this->manager());

        $this->actingAs($this->manager())->post(route('projects.advance', $card))->assertRedirect();

        $this->assertSame(1, $card->fresh()->current_stage);
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
