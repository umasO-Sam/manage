<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['daily_report_id', 'start_minute', 'end_minute', 'order_no', 'category_id', 'is_other', 'free_text', 'is_break', 'is_leave', 'leave_type'])]
class DailyReportEntry extends Model
{
    use HasFactory;

    /** @var array<string, string> leave_type値 => 表示名 */
    public const LEAVE_TYPES = [
        'full_day' => '休暇（1日）',
        'half_day' => '休暇（半日）',
        'hours' => '休暇（2時間）',
    ];

    protected function casts(): array
    {
        return [
            'is_other' => 'boolean',
            'is_break' => 'boolean',
            'is_leave' => 'boolean',
        ];
    }

    public function dailyReport(): BelongsTo
    {
        return $this->belongsTo(DailyReport::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CategoryCode::class, 'category_id');
    }
}
