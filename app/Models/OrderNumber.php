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

    /**
     * 「英数5〜7文字-英数3〜10文字」の標準形式に合致するか。
     * 保護レコード(未定/社内)や形式チェックを解除して登録した注番はfalseになる。
     */
    public function matchesStandardFormat(): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9]{5,7}-[A-Za-z0-9]{3,10}$/', $this->code);
    }
}
