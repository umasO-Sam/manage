<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'is_protected'])]
class OrderNumber extends Model
{
    /** 標準形式: 「英数1〜8文字」-「英数2〜12文字」。OrderNumberControllerの登録バリデーションと共有する。 */
    public const FORMAT_REGEX = '/^[A-Za-z0-9]{1,8}-[A-Za-z0-9]{2,12}$/';

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
     * 標準形式(self::FORMAT_REGEX)に合致するか。
     * 保護レコード(未定/社内)や形式チェックを解除して登録した注番はfalseになる。
     */
    public function matchesStandardFormat(): bool
    {
        return (bool) preg_match(self::FORMAT_REGEX, $this->code);
    }
}
