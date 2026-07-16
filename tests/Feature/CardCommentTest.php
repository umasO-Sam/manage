<?php

namespace Tests\Feature;

use App\Models\OrderNumber;
use App\Models\Staff;
use App\Models\WorkflowType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardCommentTest extends TestCase
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

    public function test_any_staff_member_can_comment_on_a_card(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $orderNumber = OrderNumber::create(['code' => 'ZZ999-N99T99', 'is_protected' => false]);
        $requester = Staff::factory()->create();
        $commenter = Staff::factory()->create();

        $card = $workflowType->cards()->create([
            'order_number_id' => $orderNumber->id, 'item_name' => 'テスト部品', 'manufacturer' => 'メーカー',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $requester->id, 'current_stage' => 0,
        ]);

        $response = $this->actingAs($commenter)->post(route('cards.comments.store', $card), [
            'body' => '在庫があるか確認お願いします。',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('card_comments', [
            'card_id' => $card->id,
            'author_id' => $commenter->id,
            'body' => '在庫があるか確認お願いします。',
        ]);

        $show = $this->actingAs($requester)->get(route('cards.show', $card));
        $show->assertSee('在庫があるか確認お願いします。')->assertSee($commenter->name);
    }

    public function test_comment_body_is_required(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $orderNumber = OrderNumber::create(['code' => 'ZZ999-N99T99', 'is_protected' => false]);
        $staff = Staff::factory()->create();

        $card = $workflowType->cards()->create([
            'order_number_id' => $orderNumber->id, 'item_name' => 'テスト部品', 'manufacturer' => 'メーカー',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $staff->id, 'current_stage' => 0,
        ]);

        $response = $this->actingAs($staff)->post(route('cards.comments.store', $card), ['body' => '']);

        $response->assertSessionHasErrors('body');
        $this->assertDatabaseCount('card_comments', 0);
    }

    public function test_cannot_comment_on_an_archived_card(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $orderNumber = OrderNumber::create(['code' => 'ZZ999-N99T99', 'is_protected' => false]);
        $staff = Staff::factory()->create();

        $card = $workflowType->cards()->create([
            'order_number_id' => $orderNumber->id, 'item_name' => 'テスト部品', 'manufacturer' => 'メーカー',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $staff->id, 'current_stage' => 2,
        ]);
        $card->delete();

        $response = $this->actingAs($staff)->post(route('cards.comments.store', $card), [
            'body' => 'アーカイブ後のコメント',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseCount('card_comments', 0);
    }
}
