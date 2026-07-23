<?php

namespace App\Models;

use Database\Factories\StaffFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'department', 'login_id', 'email', 'is_procurement_manager', 'password'])]
#[Hidden(['password', 'remember_token'])]
class Staff extends Authenticatable
{
    /** @use HasFactory<StaffFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_procurement_manager' => 'boolean',
        ];
    }

    public function createdCards(): HasMany
    {
        return $this->hasMany(Card::class, 'created_by');
    }

    public function stageLogs(): HasMany
    {
        return $this->hasMany(CardStageLog::class, 'actor_id');
    }

    /**
     * ボード上（未アーカイブ）のカードのうち、このスタッフから見て
     * 未確認・新着コメントありのものを、ワークフロー種別ごとに集計する。
     * ナビゲーションのバッジ表示用。
     *
     * @return array<int, int> workflow_type_id => 件数
     */
    public function unreadCardCountsByWorkflow(): array
    {
        return Card::query()
            ->with([
                'comments:id,card_id,created_at',
                'views' => fn ($query) => $query->where('staff_id', $this->id),
            ])
            ->get(['id', 'workflow_type_id'])
            ->filter(fn (Card $card) => $card->unreadStatusFor($this) !== null)
            ->countBy('workflow_type_id')
            ->all();
    }
}
