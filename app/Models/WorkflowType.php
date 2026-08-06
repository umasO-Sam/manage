<?php

namespace App\Models;

use App\Support\BoardAccent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'name', 'due_date_label', 'icon', 'accent', 'stage_definition', 'retention_days'])]
class WorkflowType extends Model
{
    protected function casts(): array
    {
        return [
            'stage_definition' => 'array',
        ];
    }

    /**
     * ルートモデル結合で {workflow} を slug から解決する（IDではなく）。
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** 物件管理ボードのslug。専用画面(projects.*)を持つため、調達ボードとしては扱わない。 */
    public const SLUG_PROJECT = 'project';

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    public function isProjectBoard(): bool
    {
        return $this->slug === self::SLUG_PROJECT;
    }

    /**
     * 調達ボード(購入手配・見積依頼)だけを返す。物件管理は同じカード基盤を使うが、
     * 画面もメニューも履歴も物件管理側に分かれているため、調達ボードの一覧からは外す。
     *
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public static function procurementBoards(): \Illuminate\Database\Eloquent\Builder
    {
        return static::where('slug', '!=', self::SLUG_PROJECT)->orderBy('id');
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

    /**
     * @return array<string, string>
     */
    public function accentClasses(): array
    {
        return BoardAccent::classes($this->accent);
    }
}
