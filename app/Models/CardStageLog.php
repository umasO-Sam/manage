<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['card_id', 'stage_index', 'stage_label', 'is_reversal', 'actor_id', 'moved_at'])]
class CardStageLog extends Model
{
    protected function casts(): array
    {
        return [
            'moved_at' => 'datetime',
            'is_reversal' => 'boolean',
        ];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class)->withTrashed();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'actor_id');
    }
}
