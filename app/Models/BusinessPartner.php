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
#[Fillable([
    'name', 'customer_code', 'kana_group', 'bank', 'transaction_type', 'closing_day', 'payment_terms',
    'postal_code', 'address', 'tel', 'fax', 'handling_method', 'yayoi_sub_account', 'dust_bag',
    'display_order', 'remarks', 'subcontract_note', 'related_order_nos',
    'is_provisional', 'confirmed_at', 'confirmed_by',
])]
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

    /**
     * 関連注番として持つのは注番の先頭の英字1〜3文字(客先番号)だけ。
     * 「DH013-N01」と入れても「DH」として扱う。通番まで持つと装置1台ごとに
     * 登録が要り、受注先の絞り込みには細かすぎるため。
     *
     * 区切りは改行・読点・カンマ・空白のいずれでもよい(Excelから移した欄で揃っていない)。
     *
     * @return array<int, string>
     */
    public function relatedOrderNoList(): array
    {
        return self::normalizeOrderNoCodes($this->related_order_nos);
    }

    /**
     * 入力された関連注番を、客先番号(英字1〜3文字)の配列にそろえる。
     *
     * @return array<int, string>
     */
    public static function normalizeOrderNoCodes(?string $value): array
    {
        $parts = preg_split('/[\s,、，\/／]+/u', (string) $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $codes = [];
        foreach ($parts as $part) {
            if (preg_match('/^[A-Za-z]{1,3}/', $part, $m) === 1) {
                $codes[] = mb_strtoupper($m[0]);
            }
        }

        return array_values(array_unique($codes));
    }

    /** 入力された注番が、この取引先の関連注番(客先番号)に当てはまるか。 */
    public function matchesOrderNo(string $orderNo): bool
    {
        if (preg_match('/^[A-Za-z]{1,3}/', trim($orderNo), $m) !== 1) {
            return false;
        }

        return in_array(mb_strtoupper($m[0]), $this->relatedOrderNoList(), true);
    }

    /** 受注先プルダウンでの表示。仮登録は調整中であることが分かるようにする。 */
    public function displayLabel(): string
    {
        return $this->is_provisional ? "{$this->name}（取引条件調整中）" : $this->name;
    }
}
