<?php

namespace Tests\Feature;

use App\Models\OrderNumber;
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
            'stage_definition' => [
                ['label' => '新規依頼', 'actor_label' => '依頼者'],
                ['label' => '手配中', 'actor_label' => '手配担当者'],
                ['label' => '入荷', 'actor_label' => '受入担当者'],
            ],
            'retention_days' => 7,
        ]);
    }

    private function orderNumber(string $code): OrderNumber
    {
        return OrderNumber::create(['code' => $code, 'is_protected' => false]);
    }

    public function test_archive_only_lists_trashed_cards(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $staff = Staff::factory()->create();

        $activeCard = $workflowType->cards()->create([
            'order_number_id' => $this->orderNumber('ZZ999-N99T99')->id, 'item_name' => '現役ボードの部品', 'manufacturer' => 'メーカーA',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $staff->id, 'current_stage' => 0,
        ]);
        $archivedCard = $workflowType->cards()->create([
            'order_number_id' => $this->orderNumber('ZZ888-N88T88')->id, 'item_name' => 'アーカイブ済み部品', 'manufacturer' => 'メーカーB',
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
            'order_number_id' => $this->orderNumber('ZZ111-N11T11')->id, 'item_name' => '近接センサ', 'manufacturer' => 'オムロン',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $staff->id, 'current_stage' => 2,
        ]);
        $matching->delete();

        $other = $workflowType->cards()->create([
            'order_number_id' => $this->orderNumber('ZZ222-N22T22')->id, 'item_name' => 'リレーモジュール', 'manufacturer' => 'パナソニック',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $staff->id, 'current_stage' => 2,
        ]);
        $other->delete();

        $response = $this->actingAs($staff)->get(route('archive.index', ['keyword' => 'センサ']));

        $response->assertSee('近接センサ')->assertDontSee('リレーモジュール');
    }

    public function test_archive_can_be_filtered_by_order_number_keyword(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $staff = Staff::factory()->create();

        $matching = $workflowType->cards()->create([
            'order_number_id' => $this->orderNumber('AB123-C45D67')->id, 'item_name' => '部品X', 'manufacturer' => 'メーカーX',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $staff->id, 'current_stage' => 2,
        ]);
        $matching->delete();

        $other = $workflowType->cards()->create([
            'order_number_id' => $this->orderNumber('QQ999-Z11Y22')->id, 'item_name' => '部品Y', 'manufacturer' => 'メーカーY',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $staff->id, 'current_stage' => 2,
        ]);
        $other->delete();

        $response = $this->actingAs($staff)->get(route('archive.index', ['keyword' => 'AB123']));

        $response->assertSee('部品X')->assertDontSee('部品Y');
    }

    public function test_trashed_card_detail_is_still_viewable(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $staff = Staff::factory()->create();

        $card = $workflowType->cards()->create([
            'order_number_id' => $this->orderNumber('ZZ333-N33T33')->id, 'item_name' => '削除済み詳細確認用部品', 'manufacturer' => 'メーカーC',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $staff->id, 'current_stage' => 2,
        ]);
        $card->delete();

        $response = $this->actingAs($staff)->get(route('cards.show', $card));

        $response->assertOk()->assertSee('削除済み詳細確認用部品')->assertSee('履歴（アーカイブ）として保存');
    }
}
