<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Card;
use App\Models\CardStageLog;
use App\Models\OrderNumber;
use App\Models\Staff;
use App\Models\WorkflowType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RetentionBatchTest extends TestCase
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

    public function test_card_stale_since_final_stage_is_archived(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $orderNumber = OrderNumber::create(['code' => 'ZZ999-N99T99', 'is_protected' => false]);
        $staff = Staff::factory()->create();
        $lastStage = $workflowType->lastStageIndex();

        $card = $workflowType->cards()->create([
            'order_number_id' => $orderNumber->id, 'item_name' => '古い部品', 'manufacturer' => 'メーカー',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $staff->id, 'current_stage' => $lastStage,
        ]);
        CardStageLog::create([
            'card_id' => $card->id, 'stage_index' => $lastStage, 'stage_label' => '受入担当者',
            'actor_id' => $staff->id, 'moved_at' => now()->subDays(10),
        ]);

        $this->artisan('app:archive-completed-cards');

        $this->assertSoftDeleted('cards', ['id' => $card->id]);
    }

    public function test_card_re_arrived_at_final_stage_recently_is_not_archived_yet(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $orderNumber = OrderNumber::create(['code' => 'ZZ999-N99T99', 'is_protected' => false]);
        $staff = Staff::factory()->create();
        $lastStage = $workflowType->lastStageIndex();

        $card = $workflowType->cards()->create([
            'order_number_id' => $orderNumber->id, 'item_name' => '差し戻し後再到達品', 'manufacturer' => 'メーカー',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $staff->id, 'current_stage' => $lastStage,
        ]);
        // 最初の到達は保持期間(7日)を超えて古いが…
        CardStageLog::create([
            'card_id' => $card->id, 'stage_index' => $lastStage, 'stage_label' => '受入担当者',
            'actor_id' => $staff->id, 'moved_at' => now()->subDays(10),
        ]);
        // …差し戻し後に再到達した直近のログがあるので、まだ保持期間内のはず
        CardStageLog::create([
            'card_id' => $card->id, 'stage_index' => $lastStage, 'stage_label' => '受入担当者',
            'actor_id' => $staff->id, 'moved_at' => now()->subDays(2),
        ]);

        $this->artisan('app:archive-completed-cards');

        $this->assertDatabaseHas('cards', ['id' => $card->id, 'deleted_at' => null]);
    }

    public function test_archived_card_past_five_years_is_purged_with_its_attachment(): void
    {
        Storage::fake('local');

        $workflowType = $this->purchaseWorkflow();
        $orderNumber = OrderNumber::create(['code' => 'ZZ999-N99T99', 'is_protected' => false]);
        $staff = Staff::factory()->create();

        $card = $workflowType->cards()->create([
            'order_number_id' => $orderNumber->id, 'item_name' => '削除対象品', 'manufacturer' => 'メーカー',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $staff->id, 'current_stage' => 0,
        ]);
        $path = "attachments/{$card->id}/dummy.pdf";
        Storage::disk('local')->put($path, 'dummy');
        $attachment = Attachment::create([
            'card_id' => $card->id, 'file_name' => 'dummy.pdf', 'path' => $path,
            'size_bytes' => 5, 'uploaded_by' => $staff->id,
        ]);

        $card->delete();
        $card->deleted_at = now()->subYears(6);
        $card->saveQuietly();

        $this->artisan('app:purge-archived-cards');

        $this->assertDatabaseMissing('cards', ['id' => $card->id]);
        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_archived_card_within_five_years_is_not_purged(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $orderNumber = OrderNumber::create(['code' => 'ZZ999-N99T99', 'is_protected' => false]);
        $staff = Staff::factory()->create();

        $card = $workflowType->cards()->create([
            'order_number_id' => $orderNumber->id, 'item_name' => '保管継続品', 'manufacturer' => 'メーカー',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $staff->id, 'current_stage' => 0,
        ]);
        $card->delete();
        $card->deleted_at = now()->subYears(4);
        $card->saveQuietly();

        $this->artisan('app:purge-archived-cards');

        $this->assertDatabaseHas('cards', ['id' => $card->id]);
    }
}
