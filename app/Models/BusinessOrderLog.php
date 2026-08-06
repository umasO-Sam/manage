<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * 物件履歴。物件管理ボードの操作をすべて残し、期間による削除は行わない。
 */
#[Fillable(['business_order_id', 'staff_id', 'action', 'description'])]
class BusinessOrderLog extends Model
{
    public const ACTION_CREATED = 'created';

    public const ACTION_STAGE_MOVED = 'stage_moved';

    public const ACTION_STAGE_REVERTED = 'stage_reverted';

    public const ACTION_ATTACHMENT_ADDED = 'attachment_added';

    public const ACTION_TRADE_TERMS_CONFIRMED = 'trade_terms_confirmed';

    public const ACTION_HIDDEN = 'hidden';

    /** @var array<string, string> */
    public const ACTIONS = [
        self::ACTION_CREATED => '受注登録',
        self::ACTION_STAGE_MOVED => 'ステージ移動',
        self::ACTION_STAGE_REVERTED => '差し戻し',
        self::ACTION_ATTACHMENT_ADDED => '添付追加',
        self::ACTION_TRADE_TERMS_CONFIRMED => '取引条件調整完了',
        self::ACTION_HIDDEN => '非表示',
    ];

    public function businessOrder(): BelongsTo
    {
        return $this->belongsTo(BusinessOrder::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }

    public static function record(BusinessOrder $order, string $action, ?string $description = null): self
    {
        return static::create([
            'business_order_id' => $order->id,
            'staff_id' => Auth::id(),
            'action' => $action,
            'description' => $description,
        ]);
    }
}
