<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\WorkflowType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CardWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function purchaseWorkflow(): WorkflowType
    {
        return WorkflowType::create([
            'slug' => 'purchase',
            'name' => '購入部品手配',
            'due_date_label' => '希望納期',
            'icon' => 'shopping-cart',
            'allows_reference_order_no' => false,
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
            'name' => '見積り依頼',
            'due_date_label' => '希望回答期限',
            'icon' => 'file-text',
            'allows_reference_order_no' => true,
            'stage_definition' => [
                ['label' => '新規依頼', 'actor_label' => '依頼者'],
                ['label' => '見積依頼中', 'actor_label' => '手配担当者'],
                ['label' => '回答受領', 'actor_label' => '確認担当者'],
            ],
            'retention_days' => 7,
        ]);
    }

    public function test_any_staff_member_can_create_a_card(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $staff = Staff::factory()->create();

        $response = $this->actingAs($staff)->post(route('cards.store', $workflowType), [
            'order_no' => 'ZZ999-N99T99',
            'item_name' => 'テスト部品',
            'manufacturer' => 'テストメーカー',
            'quantity' => 2,
            'unit' => '個',
            'due_date' => now()->addWeek()->toDateString(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cards', [
            'order_no' => 'ZZ999-N99T99',
            'created_by' => $staff->id,
            'current_stage' => 0,
        ]);
    }

    public function test_order_no_must_match_the_required_format(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $staff = Staff::factory()->create();

        $response = $this->actingAs($staff)->post(route('cards.store', $workflowType), [
            'order_no' => 'invalid!!',
            'item_name' => 'テスト部品',
            'manufacturer' => 'テストメーカー',
            'quantity' => 2,
            'unit' => '個',
            'due_date' => now()->addWeek()->toDateString(),
        ]);

        $response->assertSessionHasErrors('order_no');
    }

    public function test_estimate_workflow_allows_reference_order_no(): void
    {
        $workflowType = $this->estimateWorkflow();
        $staff = Staff::factory()->create();

        $response = $this->actingAs($staff)->post(route('cards.store', $workflowType), [
            'order_no' => '参考',
            'item_name' => 'テスト部品',
            'manufacturer' => 'テストメーカー',
            'quantity' => 2,
            'unit' => '個',
            'due_date' => now()->addWeek()->toDateString(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cards', ['order_no' => '参考', 'workflow_type_id' => $workflowType->id]);
    }

    public function test_purchase_workflow_rejects_reference_order_no(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $staff = Staff::factory()->create();

        $response = $this->actingAs($staff)->post(route('cards.store', $workflowType), [
            'order_no' => '参考',
            'item_name' => 'テスト部品',
            'manufacturer' => 'テストメーカー',
            'quantity' => 2,
            'unit' => '個',
            'due_date' => now()->addWeek()->toDateString(),
        ]);

        $response->assertSessionHasErrors('order_no');
    }

    public function test_boards_only_show_cards_from_their_own_workflow(): void
    {
        $purchase = $this->purchaseWorkflow();
        $estimate = $this->estimateWorkflow();
        $staff = Staff::factory()->create();

        $purchase->cards()->create([
            'order_no' => 'ZZ999-N99T99', 'item_name' => 'ポンプ試験用パーツ', 'manufacturer' => 'メーカーA',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $staff->id, 'current_stage' => 0,
        ]);
        $estimate->cards()->create([
            'order_no' => '参考', 'item_name' => '筐体見積り対象品', 'manufacturer' => 'メーカーB',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $staff->id, 'current_stage' => 0,
        ]);

        // ナビゲーションには両方のボードへのリンクが常に表示されるため、
        // 各ワークフロー固有の品名（カード内容）で判定する。
        $purchaseBoard = $this->actingAs($staff)->get(route('cards.index', $purchase));
        $purchaseBoard->assertSee('ポンプ試験用パーツ')->assertDontSee('筐体見積り対象品');

        $estimateBoard = $this->actingAs($staff)->get(route('cards.index', $estimate));
        $estimateBoard->assertSee('筐体見積り対象品')->assertDontSee('ポンプ試験用パーツ');
    }

    public function test_only_procurement_managers_can_advance_a_card(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $requester = Staff::factory()->create();
        $manager = Staff::factory()->procurementManager()->create();

        $card = $workflowType->cards()->create([
            'order_no' => 'ZZ999-N99T99',
            'item_name' => 'テスト部品',
            'manufacturer' => 'テストメーカー',
            'quantity' => 1,
            'unit' => '個',
            'due_date' => now()->addWeek(),
            'created_by' => $requester->id,
            'current_stage' => 0,
        ]);

        $this->actingAs($requester)->post("/cards/{$card->id}/move")->assertForbidden();

        $this->assertSame(0, $card->fresh()->current_stage);

        $this->actingAs($manager)->post("/cards/{$card->id}/move")->assertRedirect();

        $this->assertSame(1, $card->fresh()->current_stage);
    }

    public function test_procurement_manager_can_revert_a_card_and_it_is_logged(): void
    {
        Mail::fake();

        $workflowType = $this->purchaseWorkflow();
        $requester = Staff::factory()->create();
        $manager = Staff::factory()->procurementManager()->create();

        $card = $workflowType->cards()->create([
            'order_no' => 'ZZ999-N99T99',
            'item_name' => 'テスト部品',
            'manufacturer' => 'テストメーカー',
            'quantity' => 1,
            'unit' => '個',
            'due_date' => now()->addWeek(),
            'created_by' => $requester->id,
            'current_stage' => 1,
        ]);

        $this->actingAs($requester)->post("/cards/{$card->id}/revert")->assertForbidden();
        $this->assertSame(1, $card->fresh()->current_stage);

        $response = $this->actingAs($manager)->post("/cards/{$card->id}/revert");
        $response->assertRedirect();

        $this->assertSame(0, $card->fresh()->current_stage);
        $this->assertDatabaseHas('card_stage_logs', [
            'card_id' => $card->id,
            'stage_index' => 0,
            'is_reversal' => true,
            'actor_id' => $manager->id,
        ]);
    }

    public function test_card_cannot_be_reverted_before_the_first_stage(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $manager = Staff::factory()->procurementManager()->create();

        $card = $workflowType->cards()->create([
            'order_no' => 'ZZ999-N99T99',
            'item_name' => 'テスト部品',
            'manufacturer' => 'テストメーカー',
            'quantity' => 1,
            'unit' => '個',
            'due_date' => now()->addWeek(),
            'created_by' => $manager->id,
            'current_stage' => 0,
        ]);

        $this->actingAs($manager)->post("/cards/{$card->id}/revert")->assertSessionHasErrors('stage');
        $this->assertSame(0, $card->fresh()->current_stage);
    }

    public function test_procurement_manager_can_archive_a_card_at_the_final_stage_immediately(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $manager = Staff::factory()->procurementManager()->create();

        $card = $workflowType->cards()->create([
            'order_no' => 'ZZ999-N99T99',
            'item_name' => 'テスト部品',
            'manufacturer' => 'テストメーカー',
            'quantity' => 1,
            'unit' => '個',
            'due_date' => now()->addWeek(),
            'created_by' => $manager->id,
            'current_stage' => $workflowType->lastStageIndex(),
        ]);

        $this->actingAs($manager)->post("/cards/{$card->id}/archive-now")->assertRedirect();

        $this->assertSoftDeleted('cards', ['id' => $card->id]);
    }

    public function test_card_not_at_final_stage_cannot_be_archived_immediately(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $manager = Staff::factory()->procurementManager()->create();

        $card = $workflowType->cards()->create([
            'order_no' => 'ZZ999-N99T99',
            'item_name' => 'テスト部品',
            'manufacturer' => 'テストメーカー',
            'quantity' => 1,
            'unit' => '個',
            'due_date' => now()->addWeek(),
            'created_by' => $manager->id,
            'current_stage' => 0,
        ]);

        $this->actingAs($manager)->post("/cards/{$card->id}/archive-now")->assertSessionHasErrors('stage');
        $this->assertDatabaseHas('cards', ['id' => $card->id, 'deleted_at' => null]);
    }
}
