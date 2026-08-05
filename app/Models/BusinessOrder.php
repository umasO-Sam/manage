<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * 受注(物件)のヘッダ。注番につき1件で、受注先・納入先・受注日・受注金額・売上日を持つ。
 * 仕入の明細(purchase_details)は注番(item_code)で紐づく従属側。
 *
 * 原価計算・原価一覧・見積補助の「受注金額」は必ずこのテーブルを参照する
 * (明細側にも同名の列が残っているが、集計には使わない。移行期間の参照専用)。
 */
#[Fillable(['order_no', 'product_name', 'recipient', 'delivery_dest', 'order_received_date', 'order_amount', 'sales_date'])]
class BusinessOrder extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'order_received_date' => 'date',
            'sales_date' => 'date',
            'order_amount' => 'decimal:2',
        ];
    }

    /**
     * 明細(purchase_details)側の入力欄と対応する受注項目。仕入管理のデータ入力・編集・直接編集で
     * これらを触ったときは、明細だけでなくこのヘッダにも反映する(集計はヘッダしか見ないため、
     * 反映しないと入力が黙って効かなくなる)。
     *
     * @var array<int, string>
     */
    public const DETAIL_SYNC_FIELDS = ['product_name', 'recipient', 'delivery_dest', 'order_received_date', 'order_amount', 'sales_date'];

    public function purchaseDetails(): HasMany
    {
        return $this->hasMany(PurchaseDetail::class, 'item_code', 'order_no');
    }

    /**
     * 明細画面で入力された受注項目をヘッダへ反映する。空欄は「未入力」として無視し、
     * 既にヘッダに入っている値を消さない(受注金額と無関係な項目だけを直したときに
     * 金額が消えてしまうのを防ぐため)。
     *
     * @param  array<string, mixed>  $data
     */
    public static function syncFromDetail(?string $orderNo, array $data): ?self
    {
        $orderNo = trim((string) $orderNo);

        if ($orderNo === '') {
            return null;
        }

        $fields = [];
        foreach (self::DETAIL_SYNC_FIELDS as $field) {
            $value = $data[$field] ?? null;
            if ($value !== null && $value !== '') {
                $fields[$field] = $value;
            }
        }

        if ($fields === []) {
            return null;
        }

        return static::updateOrCreate(['order_no' => $orderNo], $fields);
    }

    /**
     * 更新時は、実際に変更された受注項目だけをヘッダへ反映する。直接編集では画面上の
     * 全項目が毎回送られてくるため、同じ注番の別の行を編集しただけでヘッダの値が
     * 入れ替わるのを避ける。
     *
     * @param  array<string, mixed>  $data
     */
    public static function syncChangedFromDetail(PurchaseDetail $detail, array $data): ?self
    {
        $changed = [];
        foreach (self::DETAIL_SYNC_FIELDS as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $before = $detail->getOriginal($field);
            $before = $before instanceof \DateTimeInterface ? $before->format('Y-m-d') : $before;

            if ((string) $before !== (string) ($data[$field] ?? '')) {
                $changed[$field] = $data[$field];
            }
        }

        return $changed === [] ? null : static::syncFromDetail($data['item_code'] ?? $detail->item_code, $changed);
    }

    /**
     * 注番 => 受注金額。集計側から使う共通の取得口。
     *
     * @param  \Illuminate\Support\Collection<int, string>|array<int, string>  $orderNos
     * @return Collection<string, float>
     */
    public static function amountsByOrderNo($orderNos): Collection
    {
        return static::whereIn('order_no', $orderNos)
            ->pluck('order_amount', 'order_no')
            ->map(fn ($amount) => (float) $amount);
    }
}
