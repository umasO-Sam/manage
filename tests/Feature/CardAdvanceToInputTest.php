<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\CardStageLog;
use App\Models\OrderNumber;
use App\Models\PurchaseDetail;
use App\Models\Staff;
use App\Models\WorkflowType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * 購入手配ボードの新規依頼カードを「手配中に進めてデータ入力する」機能のテスト。
 */
class CardAdvanceToInputTest extends TestCase
{
    use RefreshDatabase;

    private function purchaseWorkflow(): WorkflowType
    {
        return WorkflowType::create([
            'slug' => 'purchase',
            'name' => '購入手配',
            'due_date_label' => '希望納期',
            'icon' => 'shopping-cart',
            'accent' => 'blue',
            'stage_definition' => [
                ['label' => '新規依頼', 'actor_label' => '依頼者'],
                ['label' => '手配中', 'actor_label' => '手配担当者'],
                ['label' => '入荷', 'actor_label' => '受入担当者'],
            ],
            'retention_days' => 7,
        ]);
    }

    private function estimateWorkflow(): WorkflowType
    {
        return WorkflowType::create([
            'slug' => 'estimate',
            'name' => '見積依頼',
            'due_date_label' => '希望回答期限',
            'icon' => 'file-text',
            'accent' => 'orange',
            'stage_definition' => [
                ['label' => '新規依頼', 'actor_label' => '依頼者'],
                ['label' => '見積依頼中', 'actor_label' => '手配担当者'],
                ['label' => '回答受領', 'actor_label' => '確認担当者'],
            ],
            'retention_days' => 7,
        ]);
    }

    private function makeCard(WorkflowType $workflow, Staff $creator, int $stage = 0, string $code = 'AB123-N01'): Card
    {
        $orderNumber = OrderNumber::firstOrCreate(['code' => $code], ['is_protected' => false]);

        return Card::create([
            'workflow_type_id' => $workflow->id,
            'order_number_id' => $orderNumber->id,
            'machine_number' => 'M-9',
            'item_name' => 'ベアリング',
            'model_number' => '6203ZZ',
            'manufacturer' => 'テストメーカー',
            'quantity' => 4,
            'unit' => '個',
            'due_date_type' => 'asap',
            'created_by' => $creator->id,
            'current_stage' => $stage,
        ]);
    }

    public function test_button_is_shown_to_procurement_manager_on_a_new_purchase_card(): void
    {
        $manager = Staff::factory()->create(['role' => Staff::ROLE_PROCUREMENT_MANAGER]);
        $card = $this->makeCard($this->purchaseWorkflow(), Staff::factory()->create());

        $this->actingAs($manager)->get(route('cards.show', $card))
            ->assertOk()
            ->assertSee('手配中に進めてデータ入力する');
    }

    public function test_button_is_hidden_from_general_staff(): void
    {
        $staff = Staff::factory()->create();
        $card = $this->makeCard($this->purchaseWorkflow(), $staff);

        $this->actingAs($staff)->get(route('cards.show', $card))
            ->assertOk()
            ->assertDontSee('手配中に進めてデータ入力する');
    }

    public function test_button_is_hidden_on_the_estimate_board(): void
    {
        $manager = Staff::factory()->create(['role' => Staff::ROLE_PROCUREMENT_MANAGER]);
        $card = $this->makeCard($this->estimateWorkflow(), Staff::factory()->create());

        $this->actingAs($manager)->get(route('cards.show', $card))
            ->assertOk()
            ->assertDontSee('手配中に進めてデータ入力する');
    }

    public function test_button_is_hidden_once_the_card_has_left_the_first_stage(): void
    {
        $manager = Staff::factory()->create(['role' => Staff::ROLE_PROCUREMENT_MANAGER]);
        $card = $this->makeCard($this->purchaseWorkflow(), Staff::factory()->create(), stage: 1);

        $this->actingAs($manager)->get(route('cards.show', $card))
            ->assertOk()
            ->assertDontSee('手配中に進めてデータ入力する');
    }

    public function test_advances_the_card_and_redirects_to_the_input_screen_with_card_values(): void
    {
        Mail::fake();
        $manager = Staff::factory()->create(['role' => Staff::ROLE_PROCUREMENT_MANAGER]);
        $card = $this->makeCard($this->purchaseWorkflow(), Staff::factory()->create());

        $response = $this->actingAs($manager)->post(route('cards.advanceToInput', $card));

        $response->assertRedirect(route('purchasing.input'));
        $response->assertSessionHas('status', 'card-advanced-to-input');

        $this->assertSame(1, $card->fresh()->current_stage);
        $this->assertDatabaseHas('card_stage_logs', [
            'card_id' => $card->id,
            'stage_index' => 1,
            'actor_id' => $manager->id,
        ]);

        // データ入力画面はold()で値を復元するため、直前入力として引き継がれていることを確認する。
        $response->assertSessionHasInput('item_code', 'AB123-N01');
        $response->assertSessionHasInput('machine_no', 'M-9');
        $response->assertSessionHasInput('item_name', 'ベアリング');
        $response->assertSessionHasInput('dimensions', '6203ZZ');
        $response->assertSessionHasInput('manufacturer', 'テストメーカー');
        $response->assertSessionHasInput('order_qty', 4);
        $response->assertSessionHasInput('unit', '個');
    }

    public function test_does_not_create_any_purchase_detail_record(): void
    {
        Mail::fake();
        $manager = Staff::factory()->create(['role' => Staff::ROLE_PROCUREMENT_MANAGER]);
        $card = $this->makeCard($this->purchaseWorkflow(), Staff::factory()->create());

        $this->actingAs($manager)->post(route('cards.advanceToInput', $card));

        // この時点では登録を行わない(作業者が入力画面で登録する)。
        $this->assertDatabaseCount('purchase_details', 0);
    }

    public function test_input_screen_shows_the_card_values_in_the_form(): void
    {
        Mail::fake();
        $manager = Staff::factory()->create(['role' => Staff::ROLE_PROCUREMENT_MANAGER]);
        $card = $this->makeCard($this->purchaseWorkflow(), Staff::factory()->create());

        $content = $this->actingAs($manager)
            ->followingRedirects()
            ->post(route('cards.advanceToInput', $card))
            ->getContent();

        $this->assertStringContainsString('value="AB123-N01"', $content);
        $this->assertStringContainsString('value="ベアリング"', $content);
        $this->assertStringContainsString('value="6203ZZ"', $content);
        $this->assertStringContainsString('まだ登録はされていません。', $content);
    }

    public function test_general_staff_cannot_advance_to_input(): void
    {
        $staff = Staff::factory()->create();
        $card = $this->makeCard($this->purchaseWorkflow(), $staff);

        $this->actingAs($staff)->post(route('cards.advanceToInput', $card))->assertForbidden();

        $this->assertSame(0, $card->fresh()->current_stage);
    }

    public function test_rejects_cards_that_are_not_first_stage_purchase_cards(): void
    {
        Mail::fake();
        $manager = Staff::factory()->create(['role' => Staff::ROLE_PROCUREMENT_MANAGER]);
        $card = $this->makeCard($this->estimateWorkflow(), Staff::factory()->create());

        $this->actingAs($manager)->post(route('cards.advanceToInput', $card))
            ->assertSessionHasErrors('stage');

        $this->assertSame(0, $card->fresh()->current_stage);
        $this->assertSame(0, CardStageLog::where('card_id', $card->id)->count());
    }

    /**
     * ボードの「→ 手配中へ進める」とドラッグ&ドロップも、詳細画面のボタンと同じく
     * データ入力へ繋ぐ。進めただけでレコードが登録されていない状態を作らないため。
     */
    public function test_moving_a_new_purchase_card_also_leads_to_the_input_screen(): void
    {
        Mail::fake();
        $manager = Staff::factory()->create(['role' => Staff::ROLE_PROCUREMENT_MANAGER]);
        $card = $this->makeCard($this->purchaseWorkflow(), Staff::factory()->create());

        $response = $this->actingAs($manager)->post(route('cards.move', $card));

        $response->assertRedirect(route('purchasing.input'));
        $response->assertSessionHas('status', 'card-advanced-to-input');
        $response->assertSessionHasInput('item_code', 'AB123-N01');

        $this->assertSame(1, $card->fresh()->current_stage);
        // 進めただけでは登録しない(入力画面で作業者が登録する)。
        $this->assertDatabaseCount('purchase_details', 0);
    }

    /** 手配中から先や見積依頼ボードは、今までどおりその場に留まる。 */
    public function test_other_moves_stay_on_the_board(): void
    {
        Mail::fake();
        $manager = Staff::factory()->create(['role' => Staff::ROLE_PROCUREMENT_MANAGER]);

        $purchase = $this->makeCard($this->purchaseWorkflow(), Staff::factory()->create(), 1);
        $this->actingAs($manager)->post(route('cards.move', $purchase))
            ->assertSessionHas('status', 'card-moved');

        $estimate = $this->makeCard($this->estimateWorkflow(), Staff::factory()->create(), 0, 'ZZ001-N01');
        $this->actingAs($manager)->post(route('cards.move', $estimate))
            ->assertSessionHas('status', 'card-moved');
    }

    /**
     * 差し戻してから進め直すと、同じ注番・品名の仕入レコードが既にあることがある。
     * 二重登録に気づけるよう件数を知らせる。
     */
    public function test_the_input_screen_warns_when_the_same_record_already_exists(): void
    {
        Mail::fake();
        $manager = Staff::factory()->create(['role' => Staff::ROLE_PROCUREMENT_MANAGER]);
        $card = $this->makeCard($this->purchaseWorkflow(), Staff::factory()->create());

        // 1回目: まだ登録が無いので知らせない
        $this->actingAs($manager)->post(route('cards.move', $card))
            ->assertSessionHas('advanced_card_existing_count', 0);

        PurchaseDetail::create([
            'item_code' => 'AB123-N01', 'item_name' => 'ベアリング',
            'order_qty' => 4, 'unit_price' => 100, 'supplier_name' => '商社A',
        ]);

        // 差し戻してから進め直すと、既に登録があることを知らせる
        $this->actingAs($manager)->post(route('cards.revert', $card))->assertRedirect();
        $response = $this->actingAs($manager)->post(route('cards.move', $card));

        $response->assertSessionHas('advanced_card_existing_count', 1);
        $this->actingAs($manager)->get(route('purchasing.input'))
            ->assertOk()->assertSee('すでに1件あります');
    }
}
