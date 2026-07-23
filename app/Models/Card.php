<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['workflow_type_id', 'order_number_id', 'item_name', 'manufacturer', 'quantity', 'unit', 'due_date', 'created_by', 'current_stage'])]
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

    public function orderNumber(): BelongsTo
    {
        return $this->belongsTo(OrderNumber::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by');
    }

    public function stageLogs(): HasMany
    {
        return $this->hasMany(CardStageLog::class)->orderBy('moved_at');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(CardComment::class)->orderBy('created_at');
    }

    public function editLogs(): HasMany
    {
        return $this->hasMany(CardEditLog::class)->orderBy('created_at');
    }

    public function currentStageLabel(): string
    {
        return $this->workflowType->stageLabel($this->current_stage);
    }

    public function isAtFinalStage(): bool
    {
        return $this->current_stage >= $this->workflowType->lastStageIndex();
    }

    /**
     * ある段階に「現在」誰が紐付いているかを返す。差し戻し操作のログは
     * 紐付けとしては無視し、直近の正規の移動ログのみを見る。
     * 依頼者はcreated_byが常に正なので、段階0はcreatorを直接返す。
     */
    public function latestActorForStage(int $stageIndex): ?Staff
    {
        if ($stageIndex === 0) {
            return $this->creator;
        }

        return $this->stageLogs
            ->where('stage_index', $stageIndex)
            ->where('is_reversal', false)
            ->last()
            ?->actor;
    }
}
