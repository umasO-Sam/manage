<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['card_id', 'file_name', 'path', 'size_bytes', 'uploaded_by'])]
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

    /**
     * サムネイル表示可能な画像かどうか。アップロード時に許可している拡張子
     * （StoreCardRequest/UpdateCardRequestのattachments.*ルール）のうち画像分のみ。
     */
    public function isImage(): bool
    {
        $extension = strtolower(pathinfo($this->file_name, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
    }
}
