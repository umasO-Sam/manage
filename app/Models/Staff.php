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

#[Fillable(['name', 'department', 'display_order', 'sid', 'login_id', 'email', 'role', 'is_labor_target', 'position_weight', 'password', 'must_change_password', 'hire_date', 'paid_leave_granted_current_year', 'paid_leave_granted_last_year', 'is_supervisor', 'excluded_from_rosters', 'is_daily_report_reviewer', 'is_attendance_manager', 'is_executive', 'is_fund_manager', 'is_administrator'])]
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
        self::ROLE_PROCUREMENT_MANAGER => '経理資材担当',
        self::ROLE_SALES => '営業担当',
        self::ROLE_GENERAL => '一般社員',
    ];

    /**
     * 勤務状況一覧・担当者一覧で使う部署の並び順。この配列に無い部署名は末尾にまとめる。
     *
     * @var array<int, string>
     */
    public const DEPARTMENT_ORDER = ['役員', '営業', '機械設計', '電気制御', '製造', '経理資材'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_labor_target' => 'boolean',
            'must_change_password' => 'boolean',
            'hire_date' => 'date',
            'is_supervisor' => 'boolean',
            'excluded_from_rosters' => 'boolean',
            'is_daily_report_reviewer' => 'boolean',
            'is_attendance_manager' => 'boolean',
            'is_executive' => 'boolean',
            'is_fund_manager' => 'boolean',
            'is_administrator' => 'boolean',
        ];
    }

    /**
     * カードの移動・仕入管理でのレコード編集・担当者管理を行える経理資材担当かどうか。
     * administratorはすべての機能を使えるため、常に真として扱う。
     */
    public function getIsProcurementManagerAttribute(): bool
    {
        // 資金管理者はadministrator権限が必要ない全機能を使えるため、
        // 経理資材担当限定の画面も同じ扱いにする。
        return $this->role === self::ROLE_PROCUREMENT_MANAGER
            || (bool) $this->is_administrator
            || (bool) $this->is_fund_manager;
    }

    /**
     * 担当者管理(ＩＤ管理)を開ける立場かどうか。
     * 経理資材担当に加え、役員・資金管理者も権限の付与・変更のためにこの画面を使う。
     */
    public function canManageStaff(): bool
    {
        return $this->is_procurement_manager || (bool) $this->is_executive || (bool) $this->is_fund_manager;
    }

    /**
     * 権限付与のはしご: 経理資材担当 ＜ 役員 ＜ 資金管理者 ＜ administrator。
     * 自分より上のフラグは付け外しできない。
     */
    public function canGrantExecutive(): bool
    {
        return (bool) $this->is_executive || (bool) $this->is_fund_manager || (bool) $this->is_administrator;
    }

    public function canGrantFundManager(): bool
    {
        return (bool) $this->is_fund_manager || (bool) $this->is_administrator;
    }

    public function canGrantAdministrator(): bool
    {
        return (bool) $this->is_administrator;
    }

    /**
     * 勤怠管理フラグを付け外しできるか。勤怠は部署をまたいで見る必要があるため、
     * 役員・勤怠管理者自身・administratorに限る(はしごとは別の判定)。
     */
    public function canGrantAttendanceManager(): bool
    {
        return (bool) $this->is_administrator
            || (bool) $this->is_attendance_manager
            || (bool) $this->is_executive;
    }

    /**
     * 権限のはしごの高さ。数字が大きいほど強い。
     * 経理資材担当・営業担当・一般社員・上長はいずれも1（互いに編集できる）。
     */
    public function permissionLevel(): int
    {
        return match (true) {
            (bool) $this->is_administrator => 4,
            (bool) $this->is_fund_manager => 3,
            (bool) $this->is_executive => 2,
            default => 1,
        };
    }

    /**
     * 対象アカウントを編集・削除できるか。自分より上の権限のアカウントには一切触れない。
     *
     * パスワードを再設定できる＝そのアカウントでログインできる、ということなので、
     * 「フラグの付け外しだけを禁止して氏名やパスワードは編集できる」形にすると
     * 経理資材担当が役員・資金管理者になりすませてしまう(権限昇格の抜け道になる)。
     * 同じ高さ同士は互いに編集できる(役員は役員を、資金管理者は資金管理者を編集する運用)。
     */
    public function canEditAccount(self $target): bool
    {
        return $this->permissionLevel() >= $target->permissionLevel();
    }

    /**
     * 取引先一覧(銀行・取引区分・締め日・支払条件)を扱えるのは資金管理者とadministratorのみ。
     */
    public function canManageBusinessPartners(): bool
    {
        return (bool) $this->is_fund_manager || (bool) $this->is_administrator;
    }

    /**
     * 作業日報確認の画面を開けるかどうか(経理資材担当・上長・役員・資金管理者・administrator)。
     * 提出された日報の内容と確認済/未確認は見られるが、確定・差し戻しは日報管理者だけが行う。
     */
    public function canViewDailyReportReviews(): bool
    {
        return $this->isSupervisorOrManager() || (bool) $this->is_executive;
    }

    /**
     * 作業日報の確認(人工データの確定・差し戻し)を担当するかどうか。
     * 経理資材担当の全員ではなく日報管理者フラグを付けた人だけが行い、
     * 未確認バッジもその人にしか出さない。administratorはすべての機能を使える。
     */
    public function canReviewDailyReports(): bool
    {
        return (bool) $this->is_administrator
            || ($this->is_procurement_manager && (bool) $this->is_daily_report_reviewer);
    }

    /**
     * 承認済み申請の取消を、上長の承認後に反映してよいか判断できるか。
     * 日報管理者と違いロールは問わない(勤怠は部署をまたいで見る必要があるため)。
     */
    public function canManageAttendance(): bool
    {
        return (bool) $this->is_administrator || (bool) $this->is_attendance_manager;
    }

    /**
     * 他の社員の勤怠・原価情報をまとめて閲覧できる立場かどうか(経理資材担当・上長)。
     * 作業日報確認・作業日報一覧・操作ログ・原価一覧・申請承認一覧の表示可否に使う。
     */
    public function isSupervisorOrManager(): bool
    {
        return $this->is_procurement_manager || $this->is_supervisor;
    }

    /**
     * 仕入管理の検索・原価計算を閲覧できるかどうか
     * (経理資材担当・上長・営業担当・役員・資金管理者)。
     * 役員は物件管理ボードで受注金額を扱うため、原価も見られるようにしている。
     */
    public function canAccessPurchasing(): bool
    {
        return $this->isSupervisorOrManager()
            || $this->role === self::ROLE_SALES
            || (bool) $this->is_executive
            || (bool) $this->is_fund_manager;
    }

    /**
     * ロールに重ねて付与する上位フラグ(役員・資金管理者・administrator)を持つかどうか。
     * 上長は同格の担当者を見る立場で使える機能が増えるだけなので、ここには含めない。
     */
    public function hasElevatedFlag(): bool
    {
        return (bool) $this->is_executive
            || (bool) $this->is_fund_manager
            || (bool) $this->is_administrator;
    }

    /**
     * 見積番号を採番できるかどうか(営業担当・上長・役員に加え、代行する経理資材担当・資金管理者)。
     */
    public function canAllocateQuoteNumber(): bool
    {
        return $this->is_procurement_manager
            || $this->is_supervisor
            || (bool) $this->is_executive
            || (bool) $this->is_fund_manager
            || $this->role === self::ROLE_SALES;
    }

    /**
     * 物件管理ボードを使えるかどうか(経理資材担当・役員・営業担当・資金管理者・administrator)。
     */
    public function canUseProjectBoard(): bool
    {
        return $this->is_procurement_manager
            || (bool) $this->is_executive
            || (bool) $this->is_fund_manager
            || $this->role === self::ROLE_SALES;
    }

    public function roleLabel(): string
    {
        return self::ROLE_LABELS[$this->role] ?? $this->role;
    }

    /**
     * 名簿(担当者リスト)に出す担当者を、部署順・表示順で並べる。
     * 「名簿に表示しない」が付いたアカウント(テスト用・管理用・退職者など)は除外する。
     * ＩＤ管理そのものは orderedForRoster() を使い、除外せず全件を扱う。
     */
    public static function forRoster(): Builder
    {
        return static::orderedForRoster()->where('excluded_from_rosters', false);
    }

    /**
     * DEPARTMENT_ORDERの部署順、同じ部署内はdisplay_order順(同値は氏名順)で並べる。
     * DEPARTMENT_ORDERに無い部署名は末尾にまとめる。除外フラグは考慮しない。
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
        // 承認待ちに加えて、承認済みのあとに出された取消申請も自分の判断待ち。
        return LeaveRequest::where('approver_id', $this->id)
            ->where(fn ($q) => $q
                ->where('status', LeaveRequest::STATUS_PENDING)
                ->orWhere(fn ($w) => $w
                    ->where('status', LeaveRequest::STATUS_APPROVED)
                    ->where('cancel_status', LeaveRequest::CANCEL_REQUESTED)))
            ->count();
    }

    /**
     * 勤怠管理者の確認待ち件数(取消の反映確認 ＋ 上長承認済みの休日勤務)。
     * 担当が1人に固定されていないため、勤怠管理者全員に同じ件数を出す。
     */
    public function pendingCancelReflectionCount(): int
    {
        if (! $this->canManageAttendance()) {
            return 0;
        }

        return LeaveRequest::where(fn ($q) => $q
            ->where(fn ($w) => $w
                ->where('status', LeaveRequest::STATUS_APPROVED)
                ->where('cancel_status', LeaveRequest::CANCEL_PENDING_REFLECTION))
            ->orWhere('status', LeaveRequest::STATUS_PENDING_ATTENDANCE))
            ->count();
    }

    /**
     * 自分が代理提出した作業日報のうち、差し戻されたままのもの(バッジ・一覧表示用)。
     * 本人ではなく代理提出した勤怠管理者が直す約束のため、本人の画面には出さない。
     *
     * @return \Illuminate\Database\Eloquent\Builder<DailyReport>
     */
    public function rejectedProxyReportsQuery()
    {
        return DailyReport::where('proxy_staff_id', $this->id)
            ->whereNotNull('rejected_at')
            ->with('staff')
            ->orderBy('work_date');
    }

    /**
     * 有給休暇の年度(7/1〜翌6/30)の開始・終了日を返す。
     *
     * 会社の付与日が7/1のためこの区切りを使う(4/1入社なら3か月後の7/1に初回付与)。
     * 36協定・休日マスタの年度(4/21〜翌4/20)とは別物なので混同しないこと。
     *
     * @return array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon}
     */
    public static function paidLeaveYearPeriod(\Illuminate\Support\Carbon $date): array
    {
        $year = $date->month >= 7 ? $date->year : $date->year - 1;

        return [
            \Illuminate\Support\Carbon::create($year, 7, 1)->startOfDay(),
            \Illuminate\Support\Carbon::create($year + 1, 6, 30)->endOfDay(),
        ];
    }

    /**
     * 有給休暇の残日数(前年度繰越分・当年度付与分の2バケツ管理)。
     *
     * 消化分は「当年度(7/1〜翌6/30)に開始する有給休暇申請」だけを数える。
     * 年度を区切らずに全期間を合計すると、勤続年数が増えるほど過去の消化分が
     * 積み上がり、残日数が不当に0へ張り付いてしまうため。
     *
     * 承認待ちの申請も消化見込みとして残日数から差し引く。承認前は残数が減らないと、
     * 残5日の状態で5日の申請を何本でも出せてしまい、承認時点で付与日数を超過するため。
     *
     * @return array{grantedLastYear: float, grantedCurrentYear: float, grantedTotal: float,
     *     consumed: float, pending: float, remainingLastYear: float, remainingCurrentYear: float,
     *     remainingTotal: float}
     */
    public function paidLeaveBalance(): array
    {
        return static::paidLeaveBalancesFor(collect([$this]))[$this->id];
    }

    /**
     * 複数人分の有給休暇残日数を、集計クエリ1本でまとめて求める(担当者一覧のように
     * 全社員分を並べる画面で1人1クエリになるのを避けるため)。
     *
     * @param  \Illuminate\Support\Collection<int, Staff>  $staffList
     * @return array<int, array<string, float>> staff_id => 残日数の内訳
     */
    public static function paidLeaveBalancesFor(\Illuminate\Support\Collection $staffList): array
    {
        [$yearStart, $yearEnd] = static::paidLeaveYearPeriod(now());

        $ids = $staffList->pluck('id')->all();

        $totals = $ids === [] ? collect() : LeaveRequest::whereIn('staff_id', $ids)
            ->where('type', 'paid_leave')
            ->whereIn('status', [LeaveRequest::STATUS_APPROVED, LeaveRequest::STATUS_PENDING])
            ->whereDate('start_date', '>=', $yearStart->toDateString())
            ->whereDate('start_date', '<=', $yearEnd->toDateString())
            ->selectRaw('staff_id, status, SUM(day_count) as days')
            ->groupBy('staff_id', 'status')
            ->get()
            ->groupBy('staff_id');

        $result = [];

        foreach ($staffList as $staff) {
            $rows = $totals->get($staff->id) ?? collect();
            $consumed = (float) ($rows->firstWhere('status', LeaveRequest::STATUS_APPROVED)->days ?? 0);
            $pending = (float) ($rows->firstWhere('status', LeaveRequest::STATUS_PENDING)->days ?? 0);

            $grantedLastYear = (float) ($staff->paid_leave_granted_last_year ?? 0);
            $grantedCurrentYear = (float) ($staff->paid_leave_granted_current_year ?? 0);

            // 繰越分は失効が近いため先に使い切る想定で、前年度繰越分から差し引く。
            $used = $consumed + $pending;
            $usedFromLastYear = min($used, $grantedLastYear);
            $usedFromCurrentYear = max(0.0, $used - $grantedLastYear);

            $remainingLastYear = max(0.0, $grantedLastYear - $usedFromLastYear);
            $remainingCurrentYear = max(0.0, $grantedCurrentYear - $usedFromCurrentYear);

            $result[$staff->id] = [
                'grantedLastYear' => $grantedLastYear,
                'grantedCurrentYear' => $grantedCurrentYear,
                'grantedTotal' => $grantedLastYear + $grantedCurrentYear,
                'consumed' => $consumed,
                'pending' => $pending,
                'remainingLastYear' => $remainingLastYear,
                'remainingCurrentYear' => $remainingCurrentYear,
                'remainingTotal' => $remainingLastYear + $remainingCurrentYear,
            ];
        }

        return $result;
    }

    /**
     * ボード上（未アーカイブ）のカードのうち、このスタッフから見て
     * 未確認・新着コメントありのものを、ワークフロー種別ごとに集計する。
     * ナビゲーションのバッジ表示用。
     *
     * 経理資材担当はすべてのカードを処理する立場なので全件を数える。
     * それ以外は自分が起票したカードだけを数え、他人の依頼の未読で
     * バッジが埋まらないようにする(ボード上の未読マーク自体は全件に付く)。
     *
     * @return array<int, int> workflow_type_id => 件数
     */
    public function unreadCardCountsByWorkflow(): array
    {
        return Card::query()
            ->when(! $this->is_procurement_manager, fn ($query) => $query->where('created_by', $this->id))
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
