<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['staff_id', 'proxy_staff_id', 'work_date', 'submitted_at', 'remarks', 'rejected_at', 'rejection_reason'])]
class DailyReport extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'submitted_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * 本人に代わって提出した勤怠管理者。本人が自分で出した(あるいは代理提出のあとに
     * 本人が出し直した)場合はnull。
     */
    public function proxyStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'proxy_staff_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(DailyReportEntry::class);
    }

    public function laborCosts(): HasMany
    {
        return $this->hasMany(LaborCost::class);
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    public function isRejected(): bool
    {
        return $this->rejected_at !== null;
    }

    /** 勤怠管理者が本人に代わって提出したものか。 */
    public function isProxySubmitted(): bool
    {
        return $this->proxy_staff_id !== null;
    }
}
