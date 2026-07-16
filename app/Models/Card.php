<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['workflow_type_id', 'order_no', 'item_name', 'manufacturer', 'quantity', 'unit', 'due_date', 'created_by', 'current_stage'])]
class Card extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'current_stage' => 'integer',
        ];
    }

    public function workflowType(): BelongsTo
    {
        return $this->belongsTo(WorkflowType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by');
    }

    public function stageLogs(): HasMany
    {
        return $this->hasMany(CardStageLog::class)->orderBy('stage_index');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function currentStageLabel(): string
    {
        return $this->workflowType->stageLabel($this->current_stage);
    }

    public function isAtFinalStage(): bool
    {
        return $this->current_stage >= $this->workflowType->lastStageIndex();
    }
}
