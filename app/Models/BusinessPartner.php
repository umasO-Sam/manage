<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 取引先(受注先)。物件管理ボードのカード作成で受注先名だけの仮登録として作られ、
 * 資金管理者が取引条件を入力して確定すると本登録になる。
 */
#[Fillable(['name', 'bank', 'transaction_type', 'closing_day', 'payment_terms', 'is_provisional', 'confirmed_at', 'confirmed_by'])]
class BusinessPartner extends Model
{
    use HasFactory;

    /** 取引条件として揃っている必要がある項目。すべて埋まると確定できる。 */
    public const TERM_FIELDS = ['bank', 'transaction_type', 'closing_day', 'payment_terms'];

    protected function casts(): array
    {
        return [
            'is_provisional' => 'boolean',
            'confirmed_at' => 'datetime',
        ];
    }

    public function businessOrders(): HasMany
    {
        return $this->hasMany(BusinessOrder::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'confirmed_by');
    }

    /** 取引条件がまだ確定していない(＝カードに「取引条件調整中」バッジを出す)。 */
    public function isPending(): bool
    {
        return $this->is_provisional;
    }

    /** 取引条件が4項目とも埋まっているか。「取引条件調整完了」ボタンの活性条件。 */
    public function hasAllTerms(): bool
    {
        foreach (self::TERM_FIELDS as $field) {
            if (trim((string) $this->$field) === '') {
                return false;
            }
        }

        return true;
    }

    /** 受注先プルダウンでの表示。仮登録は調整中であることが分かるようにする。 */
    public function displayLabel(): string
    {
        return $this->is_provisional ? "{$this->name}（取引条件調整中）" : $this->name;
    }
}
