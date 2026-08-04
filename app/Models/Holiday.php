<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['date', 'name', 'type'])]
class Holiday extends Model
{
    public const TYPE_PUBLIC_HOLIDAY = 'public_holiday';

    public const TYPE_COMPANY_HOLIDAY = 'company_holiday';

    public const TYPE_RECOMMENDED_PAID_LEAVE = 'recommended_paid_leave';

    /** @var array<string, string> type値 => 表示名 */
    public const TYPES = [
        self::TYPE_PUBLIC_HOLIDAY => '祝日',
        self::TYPE_COMPANY_HOLIDAY => '会社休日',
        self::TYPE_RECOMMENDED_PAID_LEAVE => '有給休暇取得推奨日',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
