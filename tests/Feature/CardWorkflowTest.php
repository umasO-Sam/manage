<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\WorkflowType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function purchaseWorkflow(): WorkflowType
    {
        return WorkflowType::create([
            'slug' => 'purchase',
            'name' => '購入部品手配',
            'stage_definition' => [
                ['label' => '新規依頼', 'actor_label' => '依頼者'],
                ['label' => '手配中', 'actor_label' => '手配担当者'],
                ['label' => '入荷', 'actor_label' => '受入担当者'],
            ],
            'retention_days' => 7,
        ]);
    }

    public function test_any_staff_member_can_create_a_card(): void
    {
        $this->purchaseWorkflow();
        $staff = Staff::factory()->create();

        $response = $this->actingAs($staff)->post('/cards', [
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
        $this->purchaseWorkflow();
        $staff = Staff::factory()->create();

        $response = $this->actingAs($staff)->post('/cards', [
            'order_no' => 'invalid!!',
            'item_name' => 'テスト部品',
            'manufacturer' => 'テストメーカー',
            'quantity' => 2,
            'due_date' => now()->addWeek()->toDateString(),
        ]);

        $response->assertSessionHasErrors('order_no');
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
}
