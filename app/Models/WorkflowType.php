<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'name', 'due_date_label', 'icon', 'allows_reference_order_no', 'stage_definition', 'retention_days'])]
class WorkflowType extends Model
{
    protected function casts(): array
    {
        return [
            'stage_definition' => 'array',
            'allows_reference_order_no' => 'boolean',
        ];
    }

    /**
     * ルートモデル結合で {workflow} を slug から解決する（IDではなく）。
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    public function stageCount(): int
    {
        return count($this->stage_definition);
    }

    public function lastStageIndex(): int
    {
        return $this->stageCount() - 1;
    }

    public function stageLabel(int $index): string
    {
        return $this->stage_definition[$index]['label'] ?? '';
    }

    public function actorLabel(int $index): string
    {
        return $this->stage_definition[$index]['actor_label'] ?? '';
    }
}
