<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['card_id', 'author_id', 'body'])]
class CardComment extends Model
{
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class)->withTrashed();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'author_id');
    }
}
