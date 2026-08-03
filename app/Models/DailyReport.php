<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['staff_id', 'work_date', 'submitted_at'])]
class DailyReport extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'submitted_at' => 'datetime',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(DailyReportEntry::class);
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }
}
