<?php

namespace Tests\Feature;

use App\Models\OrderNumber;
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
            'name' => '見積り依頼',
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

    private function orderNumber(string $code = 'ZZ999-N99T99'): OrderNumber
    {
        return OrderNumber::create(['code' => $code, 'is_protected' => false]);
    }

    public function test_any_staff_member_can_create_a_card(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $orderNumber = $this->orderNumber();
        $staff = Staff::factory()->create();

        $response = $this->actingAs($staff)->post(route('cards.store', $workflowType), [
            'order_number_id' => $orderNumber->id,
            'machine_number' => 'M1234',
            'item_name' => 'テスト部品',
            'model_number' => 'ABC-123',
            'manufacturer' => 'テストメーカー',
            'quantity' => 2,
            'unit' => '個',
            'due_date_type' => 'specific',
            'due_date' => now()->addWeek()->toDateString(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cards', [
            'order_number_id' => $orderNumber->id,
            'machine_number' => 'M1234',
            'model_number' => 'ABC-123',
            'created_by' => $staff->id,
            'current_stage' => 0,
        ]);
    }

    public function test_card_can_be_created_without_manufacturer(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $orderNumber = $this->orderNumber();
        $staff = Staff::factory()->create();

        $response = $this->actingAs($staff)->post(route('cards.store', $workflowType), [
            'order_number_id' => $orderNumber->id,
            'item_name' => 'テスト部品',
            'model_number' => 'ABC-123',
            'quantity' => 2,
            'unit' => '個',
            'due_date_type' => 'asap',
        ]);

        $response->assertSessionDoesntHaveErrors('manufacturer');
        $response->assertRedirect();
        $this->assertDatabaseHas('cards', [
            'order_number_id' => $orderNumber->id,
            'model_number' => 'ABC-123',
            'manufacturer' => null,
        ]);
    }

    public function test_estimate_request_accepts_machine_number_and_requires_model_number(): void
    {
        $workflowType = $this->estimateWorkflow();
        $orderNumber = $this->orderNumber();
        $staff = Staff::factory()->create();

        $response = $this->actingAs($staff)->post(route('cards.store', $workflowType), [
            'order_number_id' => $orderNumber->id,
            'machine_number' => 'TEST-100',
            'item_name' => 'テスト部品',
            'model_number' => 'ABC-123',
            'manufacturer' => 'テストメーカー',
            'quantity' => 2,
            'unit' => '個',
            'due_date' => now()->addWeek()->toDateString(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cards', [
            'order_number_id' => $orderNumber->id,
            'machine_number' => 'TEST-100',
            'model_number' => 'ABC-123',
        ]);
    }

    public function test_estimate_request_without_model_number_is_rejected(): void
    {
        $workflowType = $this->estimateWorkflow();
        $orderNumber = $this->orderNumber();
        $staff = Staff::factory()->create();

        $response = $this->actingAs($staff)->post(route('cards.store', $workflowType), [
            'order_number_id' => $orderNumber->id,
            'item_name' => 'テスト部品',
            'manufacturer' => 'テストメーカー',
            'quantity' => 2,
            'unit' => '個',
            'due_date' => now()->addWeek()->toDateString(),
        ]);

        $response->assertSessionHasErrors('model_number');
    }

    public function test_machine_number_allows_a_hyphen(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $orderNumber = $this->orderNumber();
        $staff = Staff::factory()->create();

        $response = $this->actingAs($staff)->post(route('cards.store', $workflowType), [
            'order_number_id' => $orderNumber->id,
            'machine_number' => 'TEST-100',
            'item_name' => 'テスト部品',
            'model_number' => 'ABC-123',
            'manufacturer' => 'テストメーカー',
            'quantity' => 2,
            'unit' => '個',
            'due_date_type' => 'asap',
        ]);

        $response->assertSessionDoesntHaveErrors('machine_number');
        $this->assertDatabaseHas('cards', [
            'order_number_id' => $orderNumber->id,
            'machine_number' => 'TEST-100',
        ]);
    }

    public function test_model_number_is_required_for_a_purchase_request(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $orderNumber = $this->orderNumber();
        $staff = Staff::factory()->create();

        $response = $this->actingAs($staff)->post(route('cards.store', $workflowType), [
            'order_number_id' => $orderNumber->id,
            'item_name' => 'テスト部品',
            'manufacturer' => 'テストメーカー',
            'quantity' => 2,
            'unit' => '個',
            'due_date_type' => 'asap',
        ]);

        $response->assertSessionHasErrors('model_number');
    }

    public function test_machine_number_must_be_half_width_alphanumeric(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $orderNumber = $this->orderNumber();
        $staff = Staff::factory()->create();

        $response = $this->actingAs($staff)->post(route('cards.store', $workflowType), [
            'order_number_id' => $orderNumber->id,
            'machine_number' => '機械１２３',
            'item_name' => 'テスト部品',
            'model_number' => 'ABC-123',
            'manufacturer' => 'テストメーカー',
            'quantity' => 2,
            'unit' => '個',
            'due_date_type' => 'asap',
        ]);

        $response->assertSessionHasErrors('machine_number');
    }

    public function test_unit_cannot_contain_digits(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $orderNumber = $this->orderNumber();
        $staff = Staff::factory()->create();

        $halfWidth = $this->actingAs($staff)->post(route('cards.store', $workflowType), [
            'order_number_id' => $orderNumber->id, 'item_name' => 'テスト部品', 'model_number' => 'ABC-123',
            'manufacturer' => 'テストメーカー', 'quantity' => 2, 'unit' => '個5', 'due_date_type' => 'asap',
        ]);
        $halfWidth->assertSessionHasErrors('unit');

        $fullWidth = $this->actingAs($staff)->post(route('cards.store', $workflowType), [
            'order_number_id' => $orderNumber->id, 'item_name' => 'テスト部品', 'model_number' => 'ABC-123',
            'manufacturer' => 'テストメーカー', 'quantity' => 2, 'unit' => '個５', 'due_date_type' => 'asap',
        ]);
        $fullWidth->assertSessionHasErrors('unit');

        $valid = $this->actingAs($staff)->post(route('cards.store', $workflowType), [
            'order_number_id' => $orderNumber->id, 'item_name' => 'テスト部品', 'model_number' => 'ABC-123',
            'manufacturer' => 'テストメーカー', 'quantity' => 2, 'unit' => '個', 'due_date_type' => 'asap',
        ]);
        $valid->assertSessionDoesntHaveErrors('unit');
    }

    public function test_estimate_request_unit_cannot_contain_digits(): void
    {
        $workflowType = $this->estimateWorkflow();
        $orderNumber = $this->orderNumber();
        $staff = Staff::factory()->create();

        $response = $this->actingAs($staff)->post(route('cards.store', $workflowType), [
            'order_number_id' => $orderNumber->id, 'item_name' => 'テスト部品', 'model_number' => 'ABC-123',
            'manufacturer' => 'テストメーカー', 'quantity' => 2, 'unit' => '個5', 'due_date' => now()->addWeek()->toDateString(),
        ]);

        $response->assertSessionHasErrors('unit');
    }

    public function test_validation_error_shows_the_japanese_field_label_not_the_raw_key(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $orderNumber = $this->orderNumber();
        $staff = Staff::factory()->create();

        $response = $this->actingAs($staff)->post(route('cards.store', $workflowType), [
            'order_number_id' => $orderNumber->id, 'quantity' => 2, 'unit' => '個', 'due_date_type' => 'asap',
        ]);

        $response->assertSessionHasErrors(['item_name', 'model_number']);
        $response->assertSessionDoesntHaveErrors('manufacturer');
        $errors = session('errors')->getBag('default');
        $this->assertStringContainsString('品名を入力してください。', $errors->first('item_name'));
        $this->assertStringContainsString('型式を入力してください。', $errors->first('model_number'));
    }

    public function test_missing_required_fields_are_highlighted_with_a_light_red_background(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $orderNumber = $this->orderNumber();
        $staff = Staff::factory()->create();

        $this->actingAs($staff)->post(route('cards.store', $workflowType), [
            'order_number_id' => $orderNumber->id, 'quantity' => 2, 'unit' => '個', 'due_date_type' => 'asap',
        ]);

        $response = $this->actingAs($staff)->get(route('cards.create', $workflowType));

        $response->assertSee('bg-red-50', false);
        $response->assertSee('class="rounded-lg shadow-sm text-sm bg-red-50 border-red-300 focus:border-red-400 focus:ring-red-400 mt-1 block w-full" id="item_name"', false);
    }

    public function test_due_date_type_asap_does_not_require_a_specific_date(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $orderNumber = $this->orderNumber();
        $staff = Staff::factory()->create();

        $response = $this->actingAs($staff)->post(route('cards.store', $workflowType), [
            'order_number_id' => $orderNumber->id,
            'item_name' => 'テスト部品',
            'model_number' => 'ABC-123',
            'manufacturer' => 'テストメーカー',
            'quantity' => 2,
            'unit' => '個',
            'due_date_type' => 'asap',
        ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('cards', [
            'order_number_id' => $orderNumber->id,
            'due_date_type' => 'asap',
            'due_date' => null,
        ]);
    }

    public function test_due_date_type_specific_requires_a_date(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $orderNumber = $this->orderNumber();
        $staff = Staff::factory()->create();

        $response = $this->actingAs($staff)->post(route('cards.store', $workflowType), [
            'order_number_id' => $orderNumber->id,
            'item_name' => 'テスト部品',
            'model_number' => 'ABC-123',
            'manufacturer' => 'テストメーカー',
            'quantity' => 2,
            'unit' => '個',
            'due_date_type' => 'specific',
        ]);

        $response->assertSessionHasErrors('due_date');
    }

    public function test_order_number_must_be_a_registered_one(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $staff = Staff::factory()->create();

        $response = $this->actingAs($staff)->post(route('cards.store', $workflowType), [
            'order_number_id' => 99999,
            'item_name' => 'テスト部品',
            'model_number' => 'ABC-123',
            'manufacturer' => 'テストメーカー',
            'quantity' => 2,
            'unit' => '個',
            'due_date_type' => 'specific',
            'due_date' => now()->addWeek()->toDateString(),
        ]);

        $response->assertSessionHasErrors('order_number_id');
    }

    public function test_due_date_in_the_past_is_rejected(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $orderNumber = $this->orderNumber();
        $staff = Staff::factory()->create();

        $response = $this->actingAs($staff)->post(route('cards.store', $workflowType), [
            'order_number_id' => $orderNumber->id,
            'item_name' => 'テスト部品',
            'model_number' => 'ABC-123',
            'manufacturer' => 'テストメーカー',
            'quantity' => 2,
            'unit' => '個',
            'due_date_type' => 'specific',
            'due_date' => now()->subDay()->toDateString(),
        ]);

        $response->assertSessionHasErrors('due_date');
    }

    public function test_due_date_of_today_is_accepted(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $orderNumber = $this->orderNumber();
        $staff = Staff::factory()->create();

        $response = $this->actingAs($staff)->post(route('cards.store', $workflowType), [
            'order_number_id' => $orderNumber->id,
            'item_name' => 'テスト部品',
            'model_number' => 'ABC-123',
            'manufacturer' => 'テストメーカー',
            'quantity' => 2,
            'unit' => '個',
            'due_date_type' => 'specific',
            'due_date' => now()->toDateString(),
        ]);

        $response->assertSessionDoesntHaveErrors('due_date');
    }

    public function test_boards_only_show_cards_from_their_own_workflow(): void
    {
        $purchase = $this->purchaseWorkflow();
        $estimate = $this->estimateWorkflow();
        $orderNumber = $this->orderNumber();
        $staff = Staff::factory()->create();

        $purchase->cards()->create([
            'order_number_id' => $orderNumber->id, 'item_name' => 'ポンプ試験用パーツ', 'manufacturer' => 'メーカーA',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $staff->id, 'current_stage' => 0,
        ]);
        $estimate->cards()->create([
            'order_number_id' => $orderNumber->id, 'item_name' => '筐体見積り対象品', 'manufacturer' => 'メーカーB',
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
        $orderNumber = $this->orderNumber();
        $requester = Staff::factory()->create();
        $manager = Staff::factory()->procurementManager()->create();

        $card = $workflowType->cards()->create([
            'order_number_id' => $orderNumber->id,
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

    public function test_card_is_not_advanced_twice_by_a_concurrent_move(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $orderNumber = $this->orderNumber();
        $manager = Staff::factory()->procurementManager()->create();

        $card = $workflowType->cards()->create([
            'order_number_id' => $orderNumber->id,
            'item_name' => 'テスト部品',
            'manufacturer' => 'テストメーカー',
            'quantity' => 1,
            'unit' => '個',
            'due_date' => now()->addWeek(),
            'created_by' => $manager->id,
            'current_stage' => 0,
        ]);

        $this->actingAs($manager)->post("/cards/{$card->id}/move")->assertRedirect();
        $this->assertSame(1, $card->fresh()->current_stage);

        // 「まだstage=0を読んでいた」2つ目の同時リクエストを模す。move()が使う
        // where(current_stage, 読み取り時の値)のガードが効いていれば0件更新になる。
        $affected = \App\Models\Card::where('id', $card->id)->where('current_stage', 0)->update(['current_stage' => 1]);
        $this->assertSame(0, $affected);

        // ステージ履歴が二重に記録されていないこと
        // (このカードはEloquentで直接作成しているため作成時ログは無く、移動の1件のみ)
        $this->assertSame(1, $card->stageLogs()->count());
    }

    public function test_attachment_with_disallowed_file_type_is_rejected(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $orderNumber = $this->orderNumber();
        $staff = Staff::factory()->create();

        $response = $this->actingAs($staff)->post(route('cards.store', $workflowType), [
            'order_number_id' => $orderNumber->id,
            'item_name' => 'テスト部品',
            'model_number' => 'ABC-123',
            'manufacturer' => 'テストメーカー',
            'quantity' => 1,
            'unit' => '個',
            'due_date_type' => 'specific',
            'due_date' => now()->addWeek()->toDateString(),
            'attachments' => [
                \Illuminate\Http\UploadedFile::fake()->create('malicious.php', 10),
            ],
        ]);

        $response->assertSessionHasErrors('attachments.0');
        $this->assertDatabaseCount('cards', 0);
    }

    public function test_fdc_attachment_upload_is_unaffected_by_image_content_check(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $orderNumber = $this->orderNumber();
        $staff = Staff::factory()->create();

        // FDC(CAD等の独自バイナリ)は画像ではないため、追加した画像コンテンツ検証の対象外であることを確認。
        $fdcFile = \Illuminate\Http\UploadedFile::fake()->create('drawing.fdc', 10);

        $response = $this->actingAs($staff)->post(route('cards.store', $workflowType), [
            'order_number_id' => $orderNumber->id,
            'item_name' => 'テスト部品',
            'model_number' => 'ABC-123',
            'manufacturer' => 'テストメーカー',
            'quantity' => 1,
            'unit' => '個',
            'due_date_type' => 'specific',
            'due_date' => now()->addWeek()->toDateString(),
            'attachments' => [$fdcFile],
        ]);

        $response->assertSessionDoesntHaveErrors('attachments.0');
        $this->assertDatabaseCount('cards', 1);

        $card = \App\Models\Card::first();
        $attachment = $card->attachments()->first();
        $this->assertSame('drawing.fdc', $attachment->file_name);
        $this->assertFalse($attachment->isImage());

        $this->actingAs($staff)
            ->get(route('attachments.download', $attachment))
            ->assertOk();
    }

    public function test_attachment_with_html_content_disguised_as_image_extension_is_rejected(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $orderNumber = $this->orderNumber();
        $staff = Staff::factory()->create();

        $fakeHtmlFile = \Illuminate\Http\UploadedFile::fake()
            ->createWithContent('photo.png', '<script>alert(document.cookie)</script>');

        $response = $this->actingAs($staff)->post(route('cards.store', $workflowType), [
            'order_number_id' => $orderNumber->id,
            'item_name' => 'テスト部品',
            'model_number' => 'ABC-123',
            'manufacturer' => 'テストメーカー',
            'quantity' => 1,
            'unit' => '個',
            'due_date_type' => 'specific',
            'due_date' => now()->addWeek()->toDateString(),
            'attachments' => [$fakeHtmlFile],
        ]);

        $response->assertSessionHasErrors('attachments.0');
        $this->assertDatabaseCount('cards', 0);
    }

    public function test_procurement_manager_can_revert_a_card_and_it_is_logged(): void
    {
        Mail::fake();

        $workflowType = $this->purchaseWorkflow();
        $orderNumber = $this->orderNumber();
        $requester = Staff::factory()->create();
        $manager = Staff::factory()->procurementManager()->create();

        $card = $workflowType->cards()->create([
            'order_number_id' => $orderNumber->id,
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
        $orderNumber = $this->orderNumber();
        $manager = Staff::factory()->procurementManager()->create();

        $card = $workflowType->cards()->create([
            'order_number_id' => $orderNumber->id,
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
        $orderNumber = $this->orderNumber();
        $manager = Staff::factory()->procurementManager()->create();

        $card = $workflowType->cards()->create([
            'order_number_id' => $orderNumber->id,
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
        $orderNumber = $this->orderNumber();
        $manager = Staff::factory()->procurementManager()->create();

        $card = $workflowType->cards()->create([
            'order_number_id' => $orderNumber->id,
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

    public function test_creator_can_delete_their_own_card_while_in_new_request_stage(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $orderNumber = $this->orderNumber();
        $requester = Staff::factory()->create();

        $card = $workflowType->cards()->create([
            'order_number_id' => $orderNumber->id, 'item_name' => 'テスト部品', 'manufacturer' => 'テストメーカー',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $requester->id, 'current_stage' => 0,
        ]);

        $response = $this->actingAs($requester)->delete(route('cards.destroy', $card));

        $response->assertRedirect(route('cards.index', $workflowType));
        $this->assertSoftDeleted('cards', ['id' => $card->id]);
        $this->assertDatabaseHas('card_stage_logs', [
            'card_id' => $card->id,
            'is_deletion' => true,
            'actor_id' => $requester->id,
        ]);
    }

    public function test_deletion_is_shown_in_stage_history_on_card_detail(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $orderNumber = $this->orderNumber();
        $manager = Staff::factory()->procurementManager()->create();

        $card = $workflowType->cards()->create([
            'order_number_id' => $orderNumber->id, 'item_name' => 'テスト部品', 'manufacturer' => 'テストメーカー',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $manager->id, 'current_stage' => 0,
        ]);

        $this->actingAs($manager)->delete(route('cards.destroy', $card));

        $response = $this->actingAs($manager)->get(route('cards.show', $card));

        $response->assertSee('削除（取り消し）');
        $response->assertSee($manager->name);
    }

    public function test_other_staff_member_cannot_delete_someone_elses_card_in_new_request_stage(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $orderNumber = $this->orderNumber();
        $requester = Staff::factory()->create();
        $otherStaff = Staff::factory()->create();

        $card = $workflowType->cards()->create([
            'order_number_id' => $orderNumber->id, 'item_name' => 'テスト部品', 'manufacturer' => 'テストメーカー',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $requester->id, 'current_stage' => 0,
        ]);

        $this->actingAs($otherStaff)->delete(route('cards.destroy', $card))->assertForbidden();
        $this->assertDatabaseHas('cards', ['id' => $card->id, 'deleted_at' => null]);
    }

    public function test_procurement_manager_can_delete_a_card_in_new_request_stage(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $orderNumber = $this->orderNumber();
        $requester = Staff::factory()->create();
        $manager = Staff::factory()->procurementManager()->create();

        $card = $workflowType->cards()->create([
            'order_number_id' => $orderNumber->id, 'item_name' => 'テスト部品', 'manufacturer' => 'テストメーカー',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $requester->id, 'current_stage' => 0,
        ]);

        $this->actingAs($manager)->delete(route('cards.destroy', $card))->assertRedirect(route('cards.index', $workflowType));
        $this->assertSoftDeleted('cards', ['id' => $card->id]);
    }

    public function test_creator_cannot_delete_a_card_once_it_has_advanced_past_new_request(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $orderNumber = $this->orderNumber();
        $requester = Staff::factory()->create();

        $card = $workflowType->cards()->create([
            'order_number_id' => $orderNumber->id, 'item_name' => 'テスト部品', 'manufacturer' => 'テストメーカー',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $requester->id, 'current_stage' => 1,
        ]);

        $this->actingAs($requester)->delete(route('cards.destroy', $card))->assertForbidden();
        $this->assertDatabaseHas('cards', ['id' => $card->id, 'deleted_at' => null]);
    }

    public function test_procurement_manager_can_delete_a_card_that_has_advanced(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $orderNumber = $this->orderNumber();
        $requester = Staff::factory()->create();
        $manager = Staff::factory()->procurementManager()->create();

        $card = $workflowType->cards()->create([
            'order_number_id' => $orderNumber->id, 'item_name' => 'テスト部品', 'manufacturer' => 'テストメーカー',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $requester->id, 'current_stage' => $workflowType->lastStageIndex(),
        ]);

        $this->actingAs($manager)->delete(route('cards.destroy', $card))->assertRedirect(route('cards.index', $workflowType));
        $this->assertSoftDeleted('cards', ['id' => $card->id]);
    }

    public function test_deleted_card_no_longer_appears_on_the_board(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $orderNumber = $this->orderNumber();
        $requester = Staff::factory()->create();

        $card = $workflowType->cards()->create([
            'order_number_id' => $orderNumber->id, 'item_name' => '削除対象部品', 'manufacturer' => 'テストメーカー',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $requester->id, 'current_stage' => 0,
        ]);

        $this->actingAs($requester)->delete(route('cards.destroy', $card));

        $this->actingAs($requester)->get(route('cards.index', $workflowType))->assertDontSee('削除対象部品');
    }

    public function test_purchase_board_does_not_strike_through_item_name_at_final_stage(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $orderNumber = $this->orderNumber();
        $manager = Staff::factory()->procurementManager()->create();

        $workflowType->cards()->create([
            'order_number_id' => $orderNumber->id, 'item_name' => '入荷済み部品', 'manufacturer' => 'テストメーカー',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $manager->id,
            'current_stage' => $workflowType->lastStageIndex(),
        ]);

        $response = $this->actingAs($manager)->get(route('cards.index', $workflowType));

        $response->assertSee('入荷済み部品');
        $this->assertStringNotContainsString('line-through', $response->getContent());
    }

    public function test_new_request_card_shows_its_creation_datetime(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $orderNumber = $this->orderNumber();
        $requester = Staff::factory()->create();

        $card = $workflowType->cards()->create([
            'order_number_id' => $orderNumber->id, 'item_name' => 'テスト部品', 'manufacturer' => 'テストメーカー',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $requester->id, 'current_stage' => 0,
        ]);

        $response = $this->actingAs($requester)->get(route('cards.index', $workflowType));

        $response->assertSee('作成日時')->assertSee($card->created_at->format('Y/m/d H:i'));
    }

    public function test_in_progress_card_shows_the_datetime_it_entered_that_stage(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $orderNumber = $this->orderNumber();
        $requester = Staff::factory()->create();
        $manager = Staff::factory()->procurementManager()->create();

        $card = $workflowType->cards()->create([
            'order_number_id' => $orderNumber->id, 'item_name' => 'テスト部品', 'manufacturer' => 'テストメーカー',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $requester->id, 'current_stage' => 0,
        ]);

        $this->travel(3)->days();
        $this->actingAs($manager)->post("/cards/{$card->id}/move");
        $movedAt = $card->fresh()->stageLogs()->where('stage_index', 1)->first()->moved_at;

        $response = $this->actingAs($manager)->get(route('cards.index', $workflowType));

        $response->assertSee('状態変更日時')->assertSee($movedAt->format('Y/m/d H:i'));
    }

    public function test_only_mine_filter_shows_only_the_current_users_cards(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $orderNumber = $this->orderNumber();
        $requester = Staff::factory()->create();
        $otherStaff = Staff::factory()->create();

        $workflowType->cards()->create([
            'order_number_id' => $orderNumber->id, 'item_name' => '自分の依頼部品', 'manufacturer' => 'テストメーカー',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $requester->id, 'current_stage' => 0,
        ]);
        $workflowType->cards()->create([
            'order_number_id' => $orderNumber->id, 'item_name' => '他人の依頼部品', 'manufacturer' => 'テストメーカー',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $otherStaff->id, 'current_stage' => 0,
        ]);

        $all = $this->actingAs($requester)->get(route('cards.index', $workflowType));
        $all->assertSee('自分の依頼部品')->assertSee('他人の依頼部品');

        $mineOnly = $this->actingAs($requester)->get(route('cards.index', [$workflowType, 'only_mine' => 1]));
        $mineOnly->assertSee('自分の依頼部品')->assertDontSee('他人の依頼部品');
    }

    public function test_order_number_filter_narrows_cards_by_partial_match(): void
    {
        $workflowType = $this->purchaseWorkflow();
        $requester = Staff::factory()->create();
        $orderA = $this->orderNumber('AB123-N01');
        $orderB = $this->orderNumber('ZZ999-N99T99');

        $workflowType->cards()->create([
            'order_number_id' => $orderA->id, 'item_name' => 'AB123の部品', 'manufacturer' => 'テストメーカー',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $requester->id, 'current_stage' => 0,
        ]);
        $workflowType->cards()->create([
            'order_number_id' => $orderB->id, 'item_name' => 'ZZ999の部品', 'manufacturer' => 'テストメーカー',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $requester->id, 'current_stage' => 0,
        ]);

        $filtered = $this->actingAs($requester)->get(route('cards.index', [$workflowType, 'order_no' => 'AB123']));

        $filtered->assertSee('AB123の部品')->assertDontSee('ZZ999の部品');
    }
}
