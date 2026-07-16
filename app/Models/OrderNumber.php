<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'is_protected'])]
class OrderNumber extends Model
{
    protected function casts(): array
    {
        return [
            'is_protected' => 'boolean',
        ];
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }
}
