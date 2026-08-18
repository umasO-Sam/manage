<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'staff_id', 'proxy_staff_id', 'type', 'start_date', 'end_date', 'granularity', 'half_day_period', 'hours',
    'reason_code', 'reason_detail', 'day_count', 'order_no', 'work_location',
    'substitute_holiday_date', 'no_substitute_needed', 'actual_worked_hours',
    'compensatory_date', 'approver_id', 'status', 'rejection_reason',
    'approved_at', 'supervisor_approved_at', 'remarks',
    'cancel_status', 'cancel_reason', 'cancel_rejection_reason',
    'cancel_requested_at', 'cancelled_at',
    'funeral_venue_address', 'funeral_venue_phone', 'wake_datetime',
    'funeral_datetime', 'flowers_declined', 'telegram_declined',
])]
class LeaveRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    /**
     * 上長は承認したが、勤怠管理者の確認がまだの状態。休日勤務申請だけが通る。
     * まだ承認済みではないため、有給残日数や全社の終日休み判定からは除外される
     * (承認済みかで判定している箇所を変えずに済むよう、承認済みとは別の値にした)。
     */
    public const STATUS_PENDING_ATTENDANCE = 'pending_attendance';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_WITHDRAWN = 'withdrawn';

    /** 承認済みのあと、取消が確定した状態。 */
    public const STATUS_CANCELLED = 'cancelled';

    /** 取消申請中。上長の判断待ち。 */
    public const CANCEL_REQUESTED = 'requested';

    /** 上長が取消を認めた。勤怠管理者の反映確認待ち。 */
    public const CANCEL_PENDING_REFLECTION = 'pending_reflection';

    /**
     * まだ決裁が終わっていない状態。勤務状況一覧・個人カレンダーはどちらも
     * 同じ「承認待ち」として扱う(上長待ちか勤怠管理者待ちかで見た目は変えない)。
     *
     * @var array<int, string>
     */
    public const PENDING_STATUSES = [self::STATUS_PENDING, self::STATUS_PENDING_ATTENDANCE];

    /**
     * 勤怠管理者の承認を要する申請種別。休日勤務は法定休日の割増や振替の成立に
     * 関わるため、上長の承認だけでは確定させない。
     *
     * @var array<int, string>
     */
    public const ATTENDANCE_APPROVAL_TYPES = ['holiday_work'];

    /** @var array<string, string> type値 => 表示名 */
    public const TYPES = [
        'paid_leave' => '有給休暇',
        'ceremonial_leave' => '慶弔休暇',
        'special_leave_paid' => '特別休暇（有給）',
        'special_leave_unpaid' => '特別休暇（無給）',
        'holiday_work' => '休日勤務申請',
        'compensatory_leave' => '代休申請',
        'telework' => 'テレワーク申請',
        'juror_leave' => '裁判員休暇',
        'volunteer_leave' => 'ボランティア休暇',
        'banked_paid_leave' => '積立有給休暇',
    ];

    /**
     * type => [reason_code => [label, day_count]]。day_countがnullのものは
     * 申請者が自由入力する(忌引きの続柄別日数、特別休暇のその他事由など)。
     *
     * @var array<string, array<string, array{label: string, day_count: float|null}>>
     */
    public const REASONS = [
        'ceremonial_leave' => [
            'marriage' => ['label' => '結婚', 'day_count' => 5.0],
            'funeral' => ['label' => '忌引き', 'day_count' => null],
        ],
        'special_leave_paid' => [
            'spouse_childbirth' => ['label' => '妻の出産', 'day_count' => 3.0],
            'disaster' => ['label' => '罹災', 'day_count' => 4.0],
            'other' => ['label' => 'その他（会社が個別に認めた場合）', 'day_count' => null],
        ],
        'special_leave_unpaid' => [
            'childbirth' => ['label' => '女子従業員の出産（産前産後）', 'day_count' => null],
            'period' => ['label' => '生理休暇', 'day_count' => null],
            'infection_prevention' => ['label' => '感染予防', 'day_count' => null],
        ],
        'volunteer_leave' => [
            'disaster_recovery' => ['label' => '被災地復旧活動（有給）', 'day_count' => null],
            'local_service' => ['label' => '自警団・消防団活動（年5日まで有給、以降無給）', 'day_count' => null],
        ],
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'substitute_holiday_date' => 'date',
            'compensatory_date' => 'date',
            'no_substitute_needed' => 'boolean',
            'approved_at' => 'datetime',
            'supervisor_approved_at' => 'datetime',
            'cancel_requested_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'hours' => 'decimal:1',
            'day_count' => 'decimal:2',
            'actual_worked_hours' => 'decimal:1',
            'wake_datetime' => 'datetime',
            'funeral_datetime' => 'datetime',
            'flowers_declined' => 'boolean',
            'telegram_declined' => 'boolean',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'approver_id');
    }

    /** 代理で申請した勤怠管理者。本人が出した申請ではnull。 */
    public function proxyStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'proxy_staff_id');
    }

    /** 勤怠管理者が本人に代わって出した申請か。 */
    public function isProxySubmitted(): bool
    {
        return $this->proxy_staff_id !== null;
    }

    /** 上長の判断待ちか。勤怠管理者待ち(pending_attendance)はここには含めない。 */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /** この申請は上長の承認のあとに勤怠管理者の確認を要するか。 */
    public function needsAttendanceApproval(): bool
    {
        return in_array($this->type, self::ATTENDANCE_APPROVAL_TYPES, true);
    }

    /** 上長が承認済みで、勤怠管理者の確認を待っている状態か。 */
    public function isPendingAttendance(): bool
    {
        return $this->status === self::STATUS_PENDING_ATTENDANCE;
    }

    /**
     * 誰の対応も待っていない状態か。通知メールのリンクを後から開いたときに
     * 「対処済みです」と伝えるための判定で、承認・却下・取消のいずれで
     * 終わったかは問わない(取消手続きの途中は未対応として扱う)。
     */
    public function isSettled(): bool
    {
        return ! $this->isPending() && ! $this->isPendingAttendance() && $this->cancel_status === null;
    }

    /**
     * 本人が取り下げられるか。決裁が終わるまでは、上長待ち・勤怠管理者待ちの
     * どちらでも取り下げられる(まだ効力が無いため、承認後の取消フローは通さない)。
     */
    public function isWithdrawable(): bool
    {
        return in_array($this->status, self::PENDING_STATUSES, true);
    }

    /**
     * 勤務日の向きが制度の想定と食い違っていれば、その理由を返す。
     *
     * 休日勤務申請は事前に振替休日を決める申請なので勤務日は今日以降、代休は
     * 実際に勤務したあとに出す申請なので勤務日は今日以前になるはず。ただし
     * 実運用では逆になることもあるため、登録は止めずに注意喚起だけを行う。
     */
    public function dateWarning(): ?string
    {
        if ($this->start_date === null) {
            return null;
        }

        return match ($this->type) {
            'holiday_work' => $this->start_date->startOfDay()->isBefore(today())
                ? '勤務日が過去の日付です。すでに勤務した分であれば代休申請の方が合っている可能性があります。'
                : null,
            'compensatory_leave' => $this->start_date->startOfDay()->isAfter(today())
                ? '勤務した日が未来の日付です。これから勤務する分であれば休日勤務申請の方が合っている可能性があります。'
                : null,
            default => null,
        };
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isFuneral(): bool
    {
        return $this->type === 'ceremonial_leave' && $this->reason_code === 'funeral';
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * 勤務状況一覧など、セル内に収める短い文字ラベル。振替休日・代休は対象日での
     * 役割によって表示が変わるため、この短縮ラベルには含めず呼び出し側で切り替える。
     */
    public function shortLabel(): string
    {
        return match (true) {
            $this->type === 'paid_leave' && $this->granularity === 'full_day' => '1日有休',
            $this->type === 'paid_leave' && $this->granularity === 'hours' && $this->half_day_period === 'am' => 'AM2H休',
            $this->type === 'paid_leave' && $this->granularity === 'hours' && $this->half_day_period === 'pm' => 'PM2H休',
            // 午前/午後を持たない2時間有休は、AM/PM必須化より前に登録されたもの。
            $this->type === 'paid_leave' && $this->granularity === 'hours' => '2H休',
            $this->type === 'paid_leave' && $this->granularity === 'half_day' && $this->half_day_period === 'am' => 'AM半休',
            $this->type === 'paid_leave' && $this->granularity === 'half_day' && $this->half_day_period === 'pm' => 'PM半休',
            $this->type === 'paid_leave' && $this->granularity === 'half_day' => '半休',
            $this->type === 'telework' => '在宅',
            $this->type === 'holiday_work' => '休出',
            default => mb_substr($this->typeLabel(), 0, 4),
        };
    }

    /**
     * start_date〜end_dateが終日休みになるか（＝その日は作業日報が不要）。
     *
     * 半日・2時間の有給休暇は残りの時間を勤務するため日報が必要で、falseを返す。
     * テレワークと休日勤務は勤務日、代休申請のstart_dateは「実際に勤務した日」なので
     * いずれもfalse。代休で休む日はcompensatory_date、振替休日は
     * substitute_holiday_dateにあり、どちらも終日休みとして呼び出し側で扱う。
     *
     * 上記以外（慶弔・特別休暇・裁判員・ボランティア・積立有給）は日単位で取得する
     * 休暇のためtrue。新しい休暇種別を追加したときも既定で終日休み扱いになる。
     */
    public function isFullDayOff(): bool
    {
        return match ($this->type) {
            'telework', 'holiday_work', 'compensatory_leave' => false,
            'paid_leave' => $this->granularity === 'full_day',
            default => true,
        };
    }

    /** 半日・2時間有休の午前/午後。設定が無ければnull（詳細画面では行ごと出さない）。 */
    public function halfDayPeriodLabel(): ?string
    {
        return match ($this->half_day_period) {
            'am' => '午前(AM)',
            'pm' => '午後(PM)',
            default => null,
        };
    }

    public function reasonLabel(): ?string
    {
        return self::REASONS[$this->type][$this->reason_code]['label'] ?? null;
    }

    public function statusLabel(): string
    {
        // 取消手続き中は承認済みのままだが、どこまで進んでいるかを出す。
        if ($this->status === self::STATUS_APPROVED && $this->cancel_status !== null) {
            return match ($this->cancel_status) {
                self::CANCEL_REQUESTED => '取消申請中',
                self::CANCEL_PENDING_REFLECTION => '取消の反映確認中',
                default => '承認済み',
            };
        }

        return match ($this->status) {
            self::STATUS_PENDING => '承認待ち',
            // 上長は通したがまだ確定していない。承認済みと誤読されないよう待ち先を添える。
            self::STATUS_PENDING_ATTENDANCE => '承認待ち（勤怠管理者）',
            self::STATUS_APPROVED => '承認済み',
            self::STATUS_REJECTED => '却下',
            self::STATUS_WITHDRAWN => '取消済み',
            self::STATUS_CANCELLED => '取消済み（承認後）',
            default => $this->status,
        };
    }

    /** 本人が取消を申請できるか。承認済みで、まだ取消手続きに入っていないもの。 */
    public function canRequestCancel(): bool
    {
        return $this->status === self::STATUS_APPROVED && $this->cancel_status === null;
    }

    /** 上長の取消判断を待っている状態か。 */
    public function isCancelRequested(): bool
    {
        return $this->status === self::STATUS_APPROVED && $this->cancel_status === self::CANCEL_REQUESTED;
    }

    /** 勤怠管理者の反映確認を待っている状態か。 */
    public function isCancelPendingReflection(): bool
    {
        return $this->status === self::STATUS_APPROVED && $this->cancel_status === self::CANCEL_PENDING_REFLECTION;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }
}
