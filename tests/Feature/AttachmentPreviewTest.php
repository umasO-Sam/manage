<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\OrderNumber;
use App\Models\Staff;
use App\Models\WorkflowType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentPreviewTest extends TestCase
{
    use RefreshDatabase;

    private function makeCardWithAttachment(Staff $staff, string $fileName): Attachment
    {
        $workflowType = WorkflowType::create([
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
        $orderNumber = OrderNumber::create(['code' => 'ZZ999-N99T99', 'is_protected' => false]);

        $card = $workflowType->cards()->create([
            'order_number_id' => $orderNumber->id, 'item_name' => 'テスト部品', 'manufacturer' => 'メーカーA',
            'quantity' => 1, 'unit' => '個', 'due_date' => now()->addWeek(), 'created_by' => $staff->id, 'current_stage' => 0,
        ]);

        return Attachment::create([
            'card_id' => $card->id,
            'file_name' => $fileName,
            'path' => Storage::disk('local')->putFile("attachments/{$card->id}", UploadedFile::fake()->image($fileName)),
            'size_bytes' => 1000,
            'uploaded_by' => $staff->id,
        ]);
    }

    public function test_image_attachment_can_be_previewed_inline(): void
    {
        Storage::fake('local');
        $staff = Staff::factory()->create();
        $attachment = $this->makeCardWithAttachment($staff, 'photo.jpg');

        $response = $this->actingAs($staff)->get(route('attachments.preview', $attachment));

        $response->assertOk();
    }

    public function test_non_image_attachment_cannot_be_previewed(): void
    {
        Storage::fake('local');
        $staff = Staff::factory()->create();
        $attachment = $this->makeCardWithAttachment($staff, 'quote.pdf');

        $response = $this->actingAs($staff)->get(route('attachments.preview', $attachment));

        $response->assertNotFound();
    }

    public function test_attachment_is_image_detection_is_case_insensitive(): void
    {
        Storage::fake('local');
        $staff = Staff::factory()->create();
        $attachment = $this->makeCardWithAttachment($staff, 'PHOTO.JPG');

        $this->assertTrue($attachment->isImage());
    }

    public function test_preview_content_type_is_fixed_by_extension_not_sniffed_from_file_content(): void
    {
        Storage::fake('local');
        $staff = Staff::factory()->create();
        $attachment = $this->makeCardWithAttachment($staff, 'photo.png');

        // 過去にアップロードされ既にディスク上にある不正ファイル（実体はHTML）を模した回帰テスト。
        // previewAttachmentがファイル内容の自動判定に頼っていれば、ここでtext/htmlが
        // 返ってしまいXSSにつながる。
        Storage::disk('local')->put($attachment->path, '<script>alert(document.cookie)</script>');

        $response = $this->actingAs($staff)->get(route('attachments.preview', $attachment));

        $response->assertOk();
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
    }
}
