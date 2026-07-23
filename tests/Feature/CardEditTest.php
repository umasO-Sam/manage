<?php

namespace Tests\Feature;

use App\Models\OrderNumber;
use App\Models\Staff;
use App\Models\WorkflowType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardEditTest extends TestCase
{
    use RefreshDatabase;

    private function purchaseWorkflow(): WorkflowType
    {
        return WorkflowType::create([
            'slug' => 'purchase',
            'name' => '購入部品手配',
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

    private function orderNumber(string $code = 'ZZ999-N99T99'): OrderNumber
    {
        return OrderNumber::create(['code' => $code, 'is_protected' => false]);
    }

    public function test_procurement_manager_can_edit_a_card_and_it_is_logged(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $orderNumber = $this->orderNumber();
        $newOrderNumber = $this->orderNumber('AA111-B22C33');
        $requester = Staff::factory()->create();
        $manager = Staff::factory()->procurementManager()->create();

        $card = $workflowType->cards()->create([
            'order_number_id' => $orderNumber->id, 'item_name' => 'テスト部品', 'manufacturer' => 'メーカーA',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $requester->id, 'current_stage' => 0,
        ]);

        $response = $this->actingAs($manager)->put(route('cards.update', $card), [
            'order_number_id' => $newOrderNumber->id,
            'item_name' => '修正後の部品名',
            'manufacturer' => 'メーカーA',
            'quantity' => 3,
            'unit' => '個',
            'due_date' => $card->due_date->toDateString(),
        ]);

        $response->assertRedirect(route('cards.show', $card));

        $card->refresh();
        $this->assertSame($newOrderNumber->id, $card->order_number_id);
        $this->assertSame('修正後の部品名', $card->item_name);
        $this->assertSame(3, $card->quantity);

        $this->assertDatabaseCount('card_edit_logs', 1);
        $log = $card->editLogs()->first();
        $this->assertSame($manager->id, $log->editor_id);
        $this->assertArrayHasKey('品名', $log->changes);
        $this->assertSame('テスト部品', $log->changes['品名']['old']);
        $this->assertSame('修正後の部品名', $log->changes['品名']['new']);
        $this->assertArrayHasKey('注番', $log->changes);
        $this->assertSame($orderNumber->code, $log->changes['注番']['old']);
        $this->assertSame($newOrderNumber->code, $log->changes['注番']['new']);
        $this->assertArrayNotHasKey('メーカー', $log->changes);
    }

    public function test_requester_cannot_edit_a_card(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $orderNumber = $this->orderNumber();
        $requester = Staff::factory()->create();

        $card = $workflowType->cards()->create([
            'order_number_id' => $orderNumber->id, 'item_name' => 'テスト部品', 'manufacturer' => 'メーカーA',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $requester->id, 'current_stage' => 0,
        ]);

        $this->actingAs($requester)->get(route('cards.edit', $card))->assertForbidden();

        $response = $this->actingAs($requester)->put(route('cards.update', $card), [
            'order_number_id' => $orderNumber->id,
            'item_name' => '不正な修正',
            'manufacturer' => 'メーカーA',
            'quantity' => 1,
            'unit' => '個',
            'due_date' => $card->due_date->toDateString(),
        ]);

        $response->assertForbidden();
        $this->assertSame('テスト部品', $card->fresh()->item_name);
        $this->assertDatabaseCount('card_edit_logs', 0);
    }

    public function test_no_log_is_created_when_nothing_changed(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $orderNumber = $this->orderNumber();
        $manager = Staff::factory()->procurementManager()->create();

        $card = $workflowType->cards()->create([
            'order_number_id' => $orderNumber->id, 'item_name' => 'テスト部品', 'manufacturer' => 'メーカーA',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $manager->id, 'current_stage' => 0,
        ]);

        $response = $this->actingAs($manager)->put(route('cards.update', $card), [
            'order_number_id' => $orderNumber->id,
            'item_name' => 'テスト部品',
            'manufacturer' => 'メーカーA',
            'quantity' => 1,
            'unit' => '個',
            'due_date' => $card->due_date->toDateString(),
        ]);

        $response->assertRedirect(route('cards.show', $card));
        $this->assertDatabaseCount('card_edit_logs', 0);
    }

    public function test_cannot_edit_an_archived_card(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $orderNumber = $this->orderNumber();
        $manager = Staff::factory()->procurementManager()->create();

        $card = $workflowType->cards()->create([
            'order_number_id' => $orderNumber->id, 'item_name' => 'テスト部品', 'manufacturer' => 'メーカーA',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $manager->id,
            'current_stage' => $workflowType->lastStageIndex(),
        ]);
        $card->delete();

        $this->actingAs($manager)->get(route('cards.edit', $card))->assertNotFound();

        $response = $this->actingAs($manager)->put(route('cards.update', $card), [
            'order_number_id' => $orderNumber->id,
            'item_name' => 'アーカイブ後の修正',
            'manufacturer' => 'メーカーA',
            'quantity' => 1,
            'unit' => '個',
            'due_date' => now()->addWeek()->toDateString(),
        ]);

        $response->assertNotFound();
    }

    public function test_due_date_may_stay_in_the_past_when_editing_other_fields(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $orderNumber = $this->orderNumber();
        $manager = Staff::factory()->procurementManager()->create();

        $card = $workflowType->cards()->create([
            'order_number_id' => $orderNumber->id, 'item_name' => 'テスト部品', 'manufacturer' => 'メーカーA',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->subWeek(), 'created_by' => $manager->id, 'current_stage' => 1,
        ]);

        $response = $this->actingAs($manager)->put(route('cards.update', $card), [
            'order_number_id' => $orderNumber->id,
            'item_name' => '修正後の部品名',
            'manufacturer' => 'メーカーA',
            'quantity' => 1,
            'unit' => '個',
            'due_date' => $card->due_date->toDateString(),
        ]);

        $response->assertSessionDoesntHaveErrors('due_date');
        $this->assertSame('修正後の部品名', $card->fresh()->item_name);
    }
}
