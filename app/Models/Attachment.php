<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['card_id', 'kind', 'file_name', 'path', 'size_bytes', 'uploaded_by'])]
class Attachment extends Model
{
    /**
     * アーカイブ済み（論理削除済み）カードの添付資料も参照できるよう withTrashed。
     */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class)->withTrashed();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'uploaded_by');
    }

    private const IMAGE_MIME_TYPES = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    ];

    /**
     * サムネイル表示可能な画像かどうか。アップロード時に許可している拡張子
     * （StoreCardRequest/UpdateCardRequestのattachments.*ルール）のうち画像分のみ。
     */
    public function isImage(): bool
    {
        return $this->previewMimeType() !== null;
    }

    /**
     * プレビュー表示で使う固定Content-Type。ファイル内容の自動判定（finfoスニッフィング）
     * に頼ると、拡張子を画像に偽装したHTML/スクリプトファイルがtext/html等として
     * 解釈されXSSにつながるため、拡張子から決め打ちした値のみを返す。
     */
    public function previewMimeType(): ?string
    {
        $extension = strtolower(pathinfo($this->file_name, PATHINFO_EXTENSION));

        return self::IMAGE_MIME_TYPES[$extension] ?? null;
    }
}
