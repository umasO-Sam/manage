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
}
