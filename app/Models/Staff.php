<?php

namespace App\Models;

use Database\Factories\StaffFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'department', 'display_order', 'sid', 'login_id', 'email', 'role', 'is_labor_target', 'position_weight', 'password', 'must_change_password', 'hire_date', 'paid_leave_granted_current_year', 'paid_leave_granted_last_year', 'is_supervisor'])]
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

    /**
     * 勤務状況一覧・担当者一覧で使う部署の並び順。この配列に無い部署名は末尾にまとめる。
     *
     * @var array<int, string>
     */
    public const DEPARTMENT_ORDER = ['役員', '営業', '事務', '機械設計', '電気制御設計', '機械製造'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_labor_target' => 'boolean',
            'must_change_password' => 'boolean',
            'hire_date' => 'date',
            'is_supervisor' => 'boolean',
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

    /**
     * DEPARTMENT_ORDERの部署順、同じ部署内はdisplay_order順(同値は氏名順)で並べる。
     * DEPARTMENT_ORDERに無い部署名は末尾にまとめる。
     */
    public static function orderedForRoster(): Builder
    {
        $cases = implode(' ', array_map(fn ($index) => "WHEN department = ? THEN {$index}", array_keys(self::DEPARTMENT_ORDER)));

        return static::query()
            ->orderByRaw("CASE {$cases} ELSE ".count(self::DEPARTMENT_ORDER).' END', array_values(self::DEPARTMENT_ORDER))
            ->orderBy('display_order')
            ->orderBy('name');
    }

    public function createdCards(): HasMany
    {
        return $this->hasMany(Card::class, 'created_by');
    }

    public function stageLogs(): HasMany
    {
        return $this->hasMany(CardStageLog::class, 'actor_id');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * 自分が承認者に指定されている承認待ちの休暇・勤務申請数(ナビゲーションのバッジ表示用)。
     */
    public function pendingApprovalsCount(): int
    {
        return LeaveRequest::where('approver_id', $this->id)
            ->where('status', LeaveRequest::STATUS_PENDING)
            ->count();
    }

    /**
     * 有給休暇の残日数(前年度繰越分・当年度付与分の2バケツ管理)。
     * 承認済みの有給休暇申請の消化分を、前年度繰越分から優先して差し引く
     * (繰越分は失効が近いため先に使い切る想定)。
     *
     * @return array{grantedLastYear: float, grantedCurrentYear: float, grantedTotal: float,
     *     consumed: float, remainingLastYear: float, remainingCurrentYear: float, remainingTotal: float}
     */
    public function paidLeaveBalance(): array
    {
        $grantedLastYear = (float) ($this->paid_leave_granted_last_year ?? 0);
        $grantedCurrentYear = (float) ($this->paid_leave_granted_current_year ?? 0);

        $consumed = (float) $this->leaveRequests()
            ->where('type', 'paid_leave')
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->sum('day_count');

        $consumedFromLastYear = min($consumed, $grantedLastYear);
        $consumedFromCurrentYear = max(0.0, $consumed - $grantedLastYear);

        $remainingLastYear = max(0.0, $grantedLastYear - $consumedFromLastYear);
        $remainingCurrentYear = max(0.0, $grantedCurrentYear - $consumedFromCurrentYear);

        return [
            'grantedLastYear' => $grantedLastYear,
            'grantedCurrentYear' => $grantedCurrentYear,
            'grantedTotal' => $grantedLastYear + $grantedCurrentYear,
            'consumed' => $consumed,
            'remainingLastYear' => $remainingLastYear,
            'remainingCurrentYear' => $remainingCurrentYear,
            'remainingTotal' => $remainingLastYear + $remainingCurrentYear,
        ];
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
