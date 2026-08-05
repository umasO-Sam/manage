<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\OrderNumber;
use App\Models\Staff;
use App\Models\WorkflowType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 購入手配ボード・見積依頼ボードの簡易表示切り替えのテスト。
 * 表示の切り替え自体はAlpine(localStorage)で行うため、ここでは
 * 切り替えUIと簡易表示に必要な3行分の要素が全員に描画されることを確認する。
 */
class BoardCompactViewTest extends TestCase
{
    use RefreshDatabase;

    private function workflow(string $slug, string $name): WorkflowType
    {
        return WorkflowType::create([
            'slug' => $slug,
            'name' => $name,
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

    private function makeCard(WorkflowType $workflow, Staff $creator): Card
    {
        $orderNumber = OrderNumber::create(['code' => 'CD456-N02', 'is_protected' => false]);

        return Card::create([
            'workflow_type_id' => $workflow->id,
            'order_number_id' => $orderNumber->id,
            'item_name' => 'コンパクト表示部品',
            'model_number' => 'X-1',
            'manufacturer' => 'メーカーA',
            'quantity' => 1,
            'unit' => '個',
            'due_date_type' => 'asap',
            'created_by' => $creator->id,
            'current_stage' => 0,
        ]);
    }

    public function test_toggle_is_shown_on_the_purchase_board_to_general_staff(): void
    {
        $workflow = $this->workflow('purchase', '購入手配');
        $staff = Staff::factory()->create();
        $this->makeCard($workflow, $staff);

        $this->actingAs($staff)->get(route('cards.index', $workflow))
            ->assertOk()
            ->assertSee('簡易表示')
            ->assertSee('compact = ! compact', false);
    }

    public function test_toggle_is_shown_on_the_estimate_board(): void
    {
        $workflow = $this->workflow('estimate', '見積依頼');
        $staff = Staff::factory()->create();
        $this->makeCard($workflow, $staff);

        $this->actingAs($staff)->get(route('cards.index', $workflow))
            ->assertOk()
            ->assertSee('簡易表示');
    }

    public function test_compact_block_contains_order_no_item_name_created_at_and_creator(): void
    {
        $workflow = $this->workflow('purchase', '購入手配');
        $creator = Staff::factory()->create(['name' => '依頼太郎']);
        $card = $this->makeCard($workflow, $creator);

        $content = $this->actingAs($creator)->get(route('cards.index', $workflow))->getContent();

        // 簡易表示ブロック(x-show="compact")が描画されていること。
        $this->assertStringContainsString('x-show="compact"', $content);
        // 3行分の内容(注番・品名・作成日時・依頼者)が含まれていること。
        $this->assertStringContainsString('CD456-N02', $content);
        $this->assertStringContainsString('コンパクト表示部品', $content);
        $this->assertStringContainsString($card->created_at->format('Y/m/d H:i'), $content);
        $this->assertStringContainsString('依頼太郎', $content);
    }

    public function test_detailed_block_is_still_rendered_for_the_non_compact_state(): void
    {
        $workflow = $this->workflow('purchase', '購入手配');
        $staff = Staff::factory()->create();
        $this->makeCard($workflow, $staff);

        $this->actingAs($staff)->get(route('cards.index', $workflow))
            ->assertOk()
            ->assertSee('x-show="! compact"', false)
            ->assertSee('メーカー: メーカーA');
    }

    public function test_preference_is_persisted_in_local_storage(): void
    {
        $workflow = $this->workflow('purchase', '購入手配');
        $staff = Staff::factory()->create();
        $this->makeCard($workflow, $staff);

        // 購入手配・見積依頼のどちらでも同じキーを使い、再訪時も状態を引き継ぐ。
        $this->actingAs($staff)->get(route('cards.index', $workflow))
            ->assertOk()
            ->assertSee("localStorage.getItem('boardCompactView')", false)
            ->assertSee("localStorage.setItem('boardCompactView'", false);
    }
}
