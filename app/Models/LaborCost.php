<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'work_date', 'staff_id', 'order_no', 'machine_no', 'category_id', 'work_hours',
    'work_minutes', 'is_overtime', 'position_weight_cache', 'note', 'is_provisional', 'daily_report_id',
])]
class LaborCost extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'is_overtime' => 'boolean',
            'is_provisional' => 'boolean',
            'position_weight_cache' => 'decimal:2',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CategoryCode::class, 'category_id');
    }

    public function dailyReport(): BelongsTo
    {
        return $this->belongsTo(DailyReport::class);
    }

    /**
     * 標準単価40,000円/時間外50,000円、480分=1人工、役職荷重を掛けて算出する概算労務費。
     * 役職荷重が未設定またはゼロの場合は通常荷重(1.0)として扱う
     * （荷重0のまま掛けると労務費が0円になってしまうため、単純な `?:` 判定は使わない）。
     */
    public function estimatedCost(): float
    {
        $laborUnit = $this->totalMinutes() / 480;
        $hourlyRate = $this->is_overtime ? 50000 : 40000;
        $weight = (float) $this->position_weight_cache;
        $multiplier = $weight > 0 ? $weight : 1.0;

        return round($laborUnit * $hourlyRate * $multiplier);
    }

    public function totalMinutes(): int
    {
        return ($this->work_hours * 60) + $this->work_minutes;
    }
}
