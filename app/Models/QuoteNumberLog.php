<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * 見積番号の取得ログ。administratorだけが参照する。
 */
#[Fillable(['quote_number_id', 'full_no', 'action', 'staff_id', 'assigned_staff_id', 'description'])]
class QuoteNumberLog extends Model
{
    public const ACTION_TAKEN = 'taken';

    public const ACTION_UPDATED = 'updated';

    /** @var array<string, string> */
    public const ACTIONS = [
        self::ACTION_TAKEN => '取得',
        self::ACTION_UPDATED => '修正',
    ];

    public function quoteNumber(): BelongsTo
    {
        return $this->belongsTo(QuoteNumber::class);
    }

    /** 操作した人。 */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    /** 注番の社内担当者。 */
    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'assigned_staff_id');
    }

    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }

    public static function record(QuoteNumber $quote, string $action, ?string $description = null): self
    {
        return static::create([
            'quote_number_id' => $quote->id,
            'full_no' => $quote->canonicalNo(),
            'action' => $action,
            'staff_id' => Auth::id(),
            'assigned_staff_id' => $quote->staff_id,
            'description' => $description,
        ]);
    }
}
