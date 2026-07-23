<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\OrderNumber;
use App\Models\Staff;
use App\Models\WorkflowType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardUnreadStatusTest extends TestCase
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

    private function makeCard(WorkflowType $workflowType, Staff $creator): Card
    {
        $orderNumber = OrderNumber::create(['code' => 'ZZ'.random_int(100, 999).'-N99T99', 'is_protected' => false]);

        return $workflowType->cards()->create([
            'order_number_id' => $orderNumber->id, 'item_name' => 'テスト部品', 'manufacturer' => 'メーカーA',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $creator->id, 'current_stage' => 0,
        ]);
    }

    public function test_card_never_viewed_is_unconfirmed(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $staff = Staff::factory()->create();
        $card = $this->makeCard($workflowType, $staff);

        $this->assertSame('unconfirmed', $card->fresh()->unreadStatusFor($staff));
    }

    public function test_viewing_a_card_marks_it_as_confirmed(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $staff = Staff::factory()->create();
        $card = $this->makeCard($workflowType, $staff);

        $this->actingAs($staff)->get(route('cards.show', $card))->assertOk();

        $card->refresh();
        $card->load('views');
        $this->assertNull($card->unreadStatusFor($staff));
        $this->assertDatabaseHas('card_views', ['card_id' => $card->id, 'staff_id' => $staff->id]);
    }

    public function test_new_comment_after_viewing_marks_card_as_unread_again(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $staff = Staff::factory()->create();
        $commenter = Staff::factory()->create();
        $card = $this->makeCard($workflowType, $staff);

        $this->actingAs($staff)->get(route('cards.show', $card))->assertOk();

        $this->travel(1)->minutes();
        $this->actingAs($commenter)->post(route('cards.comments.store', $card), ['body' => '追加確認お願いします']);

        $card->refresh();
        $card->load(['views' => fn ($q) => $q->where('staff_id', $staff->id), 'comments']);
        $this->assertSame('new_comment', $card->unreadStatusFor($staff));

        // 見直せば再び確認済みになる
        $this->actingAs($staff)->get(route('cards.show', $card))->assertOk();
        $card->refresh();
        $card->load(['views' => fn ($q) => $q->where('staff_id', $staff->id), 'comments']);
        $this->assertNull($card->unreadStatusFor($staff));
    }

    public function test_viewing_is_tracked_per_staff_member_independently(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $viewer = Staff::factory()->create();
        $otherStaff = Staff::factory()->create();
        $card = $this->makeCard($workflowType, $viewer);

        $this->actingAs($viewer)->get(route('cards.show', $card));

        $card->refresh();
        $card->load('views');
        $this->assertNull($card->unreadStatusFor($viewer));
        $this->assertSame('unconfirmed', $card->unreadStatusFor($otherStaff));
    }

    public function test_navigation_badge_reflects_unread_count_for_current_staff(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $staff = Staff::factory()->create();
        $this->makeCard($workflowType, $staff);
        $this->makeCard($workflowType, $staff);

        $response = $this->actingAs($staff)->get(route('cards.index', $workflowType));

        $response->assertOk();
        // ナビのバッジに未確認2件が表示される
        $response->assertSeeText('2');
    }
}
