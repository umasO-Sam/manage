<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'item_code', 'machine_no', 'product_name', 'category_id', 'manufacturer', 'item_name',
    'dimensions', 'remarks', 'required_qty', 'usage_purpose', 'order_qty', 'unit', 'unit_price',
    'stock_qty', 'supplier_name', 'order_date', 'arrival_date', 'invoice_date', 'recipient',
    'order_received_date', 'delivery_dest', 'order_amount', 'supplier_invoice_no', 'is_provisional',
])]
class PurchaseDetail extends Model
{
    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'arrival_date' => 'date',
            'invoice_date' => 'date',
            'order_received_date' => 'date',
            'required_qty' => 'decimal:2',
            'order_qty' => 'decimal:2',
            'stock_qty' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'order_amount' => 'decimal:2',
            'is_provisional' => 'boolean',
        ];
    }

    /**
     * 受注情報（受注先・受注日・受注金額のいずれか）が入っているか。
     * 検索一覧で該当行を強調表示するために使う。
     */
    public function hasSalesOrder(): bool
    {
        return filled($this->recipient) || $this->order_received_date !== null || (float) $this->order_amount > 0;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CategoryCode::class, 'category_id');
    }

    public function lineTotal(): float
    {
        return (float) $this->order_qty * (float) $this->unit_price;
    }

    /** 単価×必要数量。検索画面で「価格」として表示する見込み金額。 */
    public function requiredAmount(): float
    {
        return (float) $this->required_qty * (float) $this->unit_price;
    }

    /** 単価×(必要数量-在庫)。在庫を差し引いた「注文価格」(不足分の発注見込み金額)。 */
    public function orderRequiredAmount(): float
    {
        return ((float) $this->required_qty - (float) $this->stock_qty) * (float) $this->unit_price;
    }
}
