<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\CardStageLog;
use App\Models\OrderNumber;
use App\Models\Staff;
use App\Models\WorkflowType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * カンバンボードの枠ごとの並び順。新規依頼は依頼された順(古いものが上)、
 * その先の枠はその枠に入った日時の新しいものを上に出す。
 */
class BoardCardOrderTest extends TestCase
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

    /**
     * 指定した枠にいるカードを1枚作る。$movedAt はその枠に入った日時、
     * $createdAt は依頼された日時(新規依頼の並び順に効く)。
     */
    private function cardInStage(
        WorkflowType $workflow,
        Staff $creator,
        string $itemName,
        int $stage,
        Carbon $createdAt,
        ?Carbon $movedAt = null,
        ?Carbon $dueDate = null,
    ): Card {
        $card = Card::create([
            'workflow_type_id' => $workflow->id,
            'order_number_id' => OrderNumber::create(['code' => $itemName, 'is_protected' => false])->id,
            'item_name' => $itemName,
            'model_number' => 'X-1',
            'quantity' => 1,
            'unit' => '個',
            'due_date_type' => $dueDate ? 'specific' : 'asap',
            'due_date' => $dueDate?->toDateString(),
            'created_by' => $creator->id,
            'current_stage' => $stage,
        ]);

        $card->forceFill(['created_at' => $createdAt])->save();

        CardStageLog::create([
            'card_id' => $card->id, 'stage_index' => 0, 'stage_label' => '依頼者',
            'actor_id' => $creator->id, 'moved_at' => $createdAt,
        ]);

        if ($stage > 0) {
            CardStageLog::create([
                'card_id' => $card->id, 'stage_index' => $stage,
                'stage_label' => $workflow->actorLabel($stage),
                'actor_id' => $creator->id, 'moved_at' => $movedAt ?? $createdAt,
            ]);
        }

        return $card;
    }

    /**
     * @return array<int, string> 枠内のカードを上から並べた品名
     */
    private function laneItemNames(WorkflowType $workflow, Staff $viewer, int $stage): array
    {
        $response = $this->actingAs($viewer)->get(route('cards.index', $workflow));
        $response->assertOk();

        return collect($response->viewData('cardsByStage')->get($stage, []))
            ->pluck('item_name')->all();
    }

    public function test_new_request_lane_keeps_the_oldest_request_on_top(): void
    {
        $workflow = $this->purchaseWorkflow();
        $staff = Staff::factory()->procurementManager()->create();

        // 希望納期の順ではなく依頼された順に並ぶことを見るため、納期は逆順にしておく。
        $this->cardInStage($workflow, $staff, '先に依頼', 0, now()->subDays(3), dueDate: now()->addDays(9));
        $this->cardInStage($workflow, $staff, '後で依頼', 0, now()->subDay(), dueDate: now()->addDays(2));

        $this->assertSame(['先に依頼', '後で依頼'], $this->laneItemNames($workflow, $staff, 0));
    }

    public function test_in_progress_lane_puts_the_most_recently_moved_card_on_top(): void
    {
        $workflow = $this->purchaseWorkflow();
        $staff = Staff::factory()->procurementManager()->create();

        // 依頼が古いカードを後から手配中に進めた場合でも、進めた順が優先される。
        $this->cardInStage($workflow, $staff, '先に手配', 1, now()->subDays(10), now()->subDays(4));
        $this->cardInStage($workflow, $staff, '後で手配', 1, now()->subDays(20), now()->subHour());

        $this->assertSame(['後で手配', '先に手配'], $this->laneItemNames($workflow, $staff, 1));
    }

    public function test_arrival_lane_puts_the_most_recently_received_card_on_top(): void
    {
        $workflow = $this->purchaseWorkflow();
        $staff = Staff::factory()->procurementManager()->create();

        $this->cardInStage($workflow, $staff, '先に入荷', 2, now()->subDays(9), now()->subDays(5));
        $this->cardInStage($workflow, $staff, '後で入荷', 2, now()->subDays(2), now()->subDay());

        $this->assertSame(['後で入荷', '先に入荷'], $this->laneItemNames($workflow, $staff, 2));
    }

    /**
     * 差し戻しで戻ってきたカードも「その枠に入った」ものとして扱う。
     * 手配中に戻された直後のカードが埋もれると、対応が要ることに気づけない。
     */
    public function test_a_card_sent_back_counts_as_newly_entering_the_lane(): void
    {
        $workflow = $this->purchaseWorkflow();
        $staff = Staff::factory()->procurementManager()->create();

        $this->cardInStage($workflow, $staff, '手配中のまま', 1, now()->subDays(3), now()->subDay());

        $sentBack = $this->cardInStage($workflow, $staff, '差し戻された', 1, now()->subDays(10), now()->subDays(8));
        CardStageLog::create([
            'card_id' => $sentBack->id, 'stage_index' => 2, 'stage_label' => '受入担当者',
            'actor_id' => $staff->id, 'moved_at' => now()->subDays(7),
        ]);
        CardStageLog::create([
            'card_id' => $sentBack->id, 'stage_index' => 1, 'stage_label' => '差し戻し(手配中へ)',
            'is_reversal' => true, 'actor_id' => $staff->id, 'moved_at' => now()->subMinutes(5),
        ]);

        $this->assertSame(['差し戻された', '手配中のまま'], $this->laneItemNames($workflow, $staff, 1));
    }

    public function test_estimate_board_uses_the_same_order(): void
    {
        $workflow = $this->estimateWorkflow();
        $staff = Staff::factory()->procurementManager()->create();

        $this->cardInStage($workflow, $staff, '古い新規依頼', 0, now()->subDays(5));
        $this->cardInStage($workflow, $staff, '新しい新規依頼', 0, now()->subDay());
        $this->cardInStage($workflow, $staff, '先に依頼中', 1, now()->subDays(6), now()->subDays(3));
        $this->cardInStage($workflow, $staff, '後で依頼中', 1, now()->subDays(2), now()->subHours(2));
        $this->cardInStage($workflow, $staff, '先に回答', 2, now()->subDays(8), now()->subDays(4));
        $this->cardInStage($workflow, $staff, '後で回答', 2, now()->subDays(7), now()->subHour());

        $this->assertSame(['古い新規依頼', '新しい新規依頼'], $this->laneItemNames($workflow, $staff, 0));
        $this->assertSame(['後で依頼中', '先に依頼中'], $this->laneItemNames($workflow, $staff, 1));
        $this->assertSame(['後で回答', '先に回答'], $this->laneItemNames($workflow, $staff, 2));
    }
}
