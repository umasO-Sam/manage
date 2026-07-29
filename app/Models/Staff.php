<?php

namespace App\Models;

use Database\Factories\StaffFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'department', 'login_id', 'email', 'role', 'is_labor_target', 'position_weight', 'password', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class Staff extends Authenticatable
{
    /** @use HasFactory<StaffFactory> */
    use HasFactory, Notifiable;

    public const ROLE_PROCUREMENT_MANAGER = 'procurement_manager';

    public const ROLE_SALES = 'sales';

    public const ROLE_GENERAL = 'general';

    /** @var array<string, string> ロール値 => 表示ラベル */
    public const ROLE_LABELS = [
        self::ROLE_PROCUREMENT_MANAGER => '資材管理担当者',
        self::ROLE_SALES => '営業担当',
        self::ROLE_GENERAL => '一般社員',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_labor_target' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * カードの移動・仕入管理でのレコード編集・担当者管理を行える資材管理担当者かどうか。
     */
    public function getIsProcurementManagerAttribute(): bool
    {
        return $this->role === self::ROLE_PROCUREMENT_MANAGER;
    }

    /**
     * 仕入管理の検索・原価計算を閲覧できるロールかどうか(資材管理担当者・営業担当)。
     */
    public function canAccessPurchasing(): bool
    {
        return in_array($this->role, [self::ROLE_PROCUREMENT_MANAGER, self::ROLE_SALES], true);
    }

    public function roleLabel(): string
    {
        return self::ROLE_LABELS[$this->role] ?? $this->role;
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
