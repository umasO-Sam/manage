<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\WorkflowType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchiveTest extends TestCase
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
            'allows_reference_order_no' => false,
            'stage_definition' => [
                ['label' => '新規依頼', 'actor_label' => '依頼者'],
                ['label' => '手配中', 'actor_label' => '手配担当者'],
                ['label' => '入荷', 'actor_label' => '受入担当者'],
            ],
            'retention_days' => 7,
        ]);
    }

    public function test_archive_only_lists_trashed_cards(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $staff = Staff::factory()->create();

        $activeCard = $workflowType->cards()->create([
            'order_no' => 'ZZ999-N99T99', 'item_name' => '現役ボードの部品', 'manufacturer' => 'メーカーA',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $staff->id, 'current_stage' => 0,
        ]);
        $archivedCard = $workflowType->cards()->create([
            'order_no' => 'ZZ888-N88T88', 'item_name' => 'アーカイブ済み部品', 'manufacturer' => 'メーカーB',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $staff->id, 'current_stage' => 2,
        ]);
        $archivedCard->delete();

        $response = $this->actingAs($staff)->get(route('archive.index'));

        $response->assertSee('アーカイブ済み部品')->assertDontSee('現役ボードの部品');
    }

    public function test_archive_can_be_filtered_by_keyword(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $staff = Staff::factory()->create();

        $matching = $workflowType->cards()->create([
            'order_no' => 'ZZ111-N11T11', 'item_name' => '近接センサ', 'manufacturer' => 'オムロン',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $staff->id, 'current_stage' => 2,
        ]);
        $matching->delete();

        $other = $workflowType->cards()->create([
            'order_no' => 'ZZ222-N22T22', 'item_name' => 'リレーモジュール', 'manufacturer' => 'パナソニック',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $staff->id, 'current_stage' => 2,
        ]);
        $other->delete();

        $response = $this->actingAs($staff)->get(route('archive.index', ['keyword' => 'センサ']));

        $response->assertSee('近接センサ')->assertDontSee('リレーモジュール');
    }

    public function test_trashed_card_detail_is_still_viewable(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $staff = Staff::factory()->create();

        $card = $workflowType->cards()->create([
            'order_no' => 'ZZ333-N33T33', 'item_name' => '削除済み詳細確認用部品', 'manufacturer' => 'メーカーC',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $staff->id, 'current_stage' => 2,
        ]);
        $card->delete();

        $response = $this->actingAs($staff)->get(route('cards.show', $card));

        $response->assertOk()->assertSee('削除済み詳細確認用部品')->assertSee('履歴（アーカイブ）として保存');
    }
}
