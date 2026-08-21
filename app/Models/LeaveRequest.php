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
    'amend_status', 'amend_reason', 'amend_rejection_reason', 'amend_requested_at', 'amended_at',
    'amend_substitute_holiday_date', 'amend_no_substitute_needed', 'amend_compensatory_date',
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

    /** 変更申請中。上長の判断待ち(statusは承認待ちに戻っている)。 */
    public const AMEND_REQUESTED = 'requested';

    /** 上長が変更を認めた。勤怠管理者の反映確認待ち(statusは承認待ち(勤怠管理者))。 */
    public const AMEND_PENDING_REFLECTION = 'pending_reflection';

    /**
     * 承認後に変更申請を出せる種別。出勤した事実は動かせないが、振替休日・代休日は
     * あとから変わるため、取り消して出し直さずに決裁をやり直せるようにする。
     *
     * @var array<int, string>
     */
    public const AMENDABLE_TYPES = ['holiday_work', 'compensatory_leave'];

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
            'amend_requested_at' => 'datetime',
            'amended_at' => 'datetime',
            'amend_substitute_holiday_date' => 'date',
            'amend_compensatory_date' => 'date',
            'amend_no_substitute_needed' => 'boolean',
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
     *
     * 勤務状況一覧の日付列は52px固定のため、**全角3文字(46px)までに収める**こと。
     * 4文字だと60pxになり、隣の列へはみ出す。
     */
    public function shortLabel(): string
    {
        return match (true) {
            $this->type === 'paid_leave' && $this->granularity === 'full_day' => '1日休',
            $this->type === 'paid_leave' && $this->granularity === 'hours' && $this->half_day_period === 'am' => 'AM2H',
            $this->type === 'paid_leave' && $this->granularity === 'hours' && $this->half_day_period === 'pm' => 'PM2H',
            // 午前/午後を持たない2時間有休・半休は、AM/PM必須化より前に登録されたもの。
            // AM/PMが分からないため「休」を残した表記のままにする。
            $this->type === 'paid_leave' && $this->granularity === 'hours' => '2H休',
            $this->type === 'paid_leave' && $this->granularity === 'half_day' && $this->half_day_period === 'am' => 'AM半',
            $this->type === 'paid_leave' && $this->granularity === 'half_day' && $this->half_day_period === 'pm' => 'PM半',
            $this->type === 'paid_leave' && $this->granularity === 'half_day' => '半休',
            $this->type === 'telework' => '在宅',
            $this->type === 'holiday_work' => '休出',
            // 忌引きは休む日数も周囲の対応も他の慶弔と違うため、一目で分かるように分ける。
            $this->isFuneral() => '忌引',
            $this->type === 'ceremonial_leave' => '慶弔',
            $this->type === 'special_leave_paid' => '特休有',
            $this->type === 'special_leave_unpaid' => '特休無',
            $this->type === 'juror_leave' => '裁判員',
            $this->type === 'volunteer_leave' => 'ボラ',
            $this->type === 'banked_paid_leave' => '積立有',
            // 代休申請のstart_dateは「実際に勤務した日」。休む日(compensatory_date)の
            // 「代休」は呼び出し側で付けるため、ここは勤務した側の表記になる。
            $this->type === 'compensatory_leave' => '出勤',
            // 種別を足したときも列幅(52px)に収まるよう3文字で切る。
            default => mb_substr($this->typeLabel(), 0, 3),
        };
    }

    /**
     * 休出・振休・出勤・代休は必ず2日で1組になる。勤務状況一覧でその日にマウスを
     * 乗せたとき、相方の日付を出すための表記(例: 休出のセルなら「振休2026/08/24」)。
     *
     * $role は勤務状況一覧でのその日の役割(main / substitute / compensatory)。
     * 組にならない種別(有給休暇・在宅など)はnullを返す。
     */
    public function pairedDateNote(string $role): ?string
    {
        $format = fn (?\Illuminate\Support\Carbon $date) => $date?->format('Y/m/d');

        return match (true) {
            // 休出のセル。振替休日を取らない申請もあるため、その旨を出し分ける。
            $role === 'main' && $this->type === 'holiday_work' => '振休'
                .($format($this->substitute_holiday_date) ?? ($this->no_substitute_needed ? 'なし' : '未定')),
            // 代休申請のstart_dateは実際に勤務した日(セルは「出勤」)。
            $role === 'main' && $this->type === 'compensatory_leave' => '代休'
                .($format($this->compensatory_date) ?? '未定'),
            $role === 'substitute' => '休出'.$format($this->start_date),
            $role === 'compensatory' => '出勤'.$format($this->start_date),
            default => null,
        };
    }

    /**
     * 同じ日に「在宅/休出」と「半日・2時間の有給休暇」が出ているとき、勤務状況一覧の
     * セルを1行に収めるための合成表記(在A半・出P2 など)。組み合わせられない場合はnull。
     *
     * 在宅・休出は1日の枠を決める申請、半休・2時間有休はその中の一部を休む申請なので、
     * 同じ日に並んでも矛盾しない。1日有休など終日休む申請とは重ねない(矛盾するため
     * まとめずに2行のまま出し、おかしいことが見えるようにする)。
     */
    public static function combinedShortLabel(self $base, self $paidLeave): ?string
    {
        $prefix = match ($base->type) {
            'telework' => '在',
            'holiday_work' => '出',
            default => null,
        };

        $period = match ($paidLeave->half_day_period) {
            'am' => 'A',
            'pm' => 'P',
            // 午前/午後を持たない古い申請は、どちらを休むか書けないのでまとめない。
            default => null,
        };

        $unit = match ($paidLeave->granularity) {
            'half_day' => '半',
            'hours' => '2',
            default => null,
        };

        if ($prefix === null || $period === null || $unit === null || $paidLeave->type !== 'paid_leave') {
            return null;
        }

        return $prefix.$period.$unit;
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

        // 変更申請中は承認待ちへ戻っている。何の承認待ちかが分かるようにする。
        if ($this->isAmendRequested()) {
            return '変更の承認待ち';
        }

        if ($this->isAmendPendingReflection()) {
            return '変更の承認待ち（勤怠管理者）';
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

    /**
     * 承認後の変更申請を出せるか。出勤日は動かせないため、変えられるのは
     * 振替休日(取らない選択を含む)と代休日だけ。取消手続き中は出せない。
     */
    public function canRequestAmend(): bool
    {
        return $this->status === self::STATUS_APPROVED
            && $this->cancel_status === null
            && $this->amend_status === null
            && in_array($this->type, self::AMENDABLE_TYPES, true);
    }

    /** 変更申請が上長の判断を待っている状態か。 */
    public function isAmendRequested(): bool
    {
        return $this->amend_status === self::AMEND_REQUESTED;
    }

    /** 変更申請が勤怠管理者の反映確認を待っている状態か。 */
    public function isAmendPendingReflection(): bool
    {
        return $this->amend_status === self::AMEND_PENDING_REFLECTION;
    }

    /** 変更の決裁が途中か(上長待ち・勤怠管理者待ちのどちらか)。 */
    public function isAmending(): bool
    {
        return $this->amend_status !== null;
    }

    /**
     * 変更申請の中身。「何が」「今どうで」「どう変わるか」を1か所で作り、
     * 画面の表示と操作ログの記録で同じ文言を使う。
     *
     * @return array<int, array{label: string, before: string, after: string}>
     */
    public function amendmentChanges(): array
    {
        $date = fn (?\Illuminate\Support\Carbon $d) => $d?->format('Y/m/d');

        if ($this->type === 'holiday_work') {
            $before = $this->no_substitute_needed ? '振り替えなし' : ($date($this->substitute_holiday_date) ?? '未定');
            $after = $this->amend_no_substitute_needed
                ? '振り替えなし'
                : ($date($this->amend_substitute_holiday_date) ?? '未定');

            return [['label' => '振替休日', 'before' => $before, 'after' => $after]];
        }

        if ($this->type === 'compensatory_leave') {
            return [[
                'label' => '代休日',
                'before' => $date($this->compensatory_date) ?? '未定',
                'after' => $date($this->amend_compensatory_date) ?? '未定',
            ]];
        }

        return [];
    }

    /**
     * 操作ログに残す「何の申請か」の一文。種別・対象日・申請内容をこの1か所で組み立て、
     * 申請・承認・却下・取消・変更のどのログにも同じ形で載せる。
     *
     * 例: 休日勤務申請 2026/09/12（注番 A-1／本社／振休 2026/09/16）
     *     有給休暇 2026/08/25（1日・1日）
     *     代休申請 2026/08/22（勤務8時間／代休 2026/08/26）
     *     慶弔休暇（忌引き） 2026/08/27〜2026/08/31（3日）
     */
    public function logSummary(): string
    {
        $date = fn (?\Illuminate\Support\Carbon $d) => $d?->format('Y/m/d');

        $head = $this->typeLabel().($this->reasonLabel() ? '（'.$this->reasonLabel().'）' : '');

        $period = $date($this->start_date);
        if ($this->end_date && ! $this->end_date->equalTo($this->start_date)) {
            $period .= '〜'.$date($this->end_date);
        }

        $details = array_values(array_filter($this->logDetailParts()));

        return trim($head.' '.$period).($details === [] ? '' : '（'.implode('／', $details).'）');
    }

    /**
     * logSummary()の括弧に入れる、種別ごとの中身。
     *
     * @return array<int, string|null>
     */
    private function logDetailParts(): array
    {
        $date = fn (?\Illuminate\Support\Carbon $d) => $d?->format('Y/m/d');
        $days = fn () => $this->day_count === null ? null : \App\Support\LeaveDays::format($this->day_count).'日';

        return match ($this->type) {
            'paid_leave' => [
                match ($this->granularity) {
                    // 日数(1日)と並ぶので、粒度は「終日」と書いて重ならないようにする。
                    'full_day' => '終日',
                    'half_day' => '半日',
                    'hours' => ($this->hours !== null ? rtrim(rtrim((string) $this->hours, '0'), '.').'時間' : '時間'),
                    default => null,
                },
                $this->halfDayPeriodLabel(),
                $days(),
            ],
            'holiday_work' => [
                $this->order_no ? '注番 '.$this->order_no : null,
                $this->work_location,
                $this->no_substitute_needed ? '振休なし' : ($this->substitute_holiday_date ? '振休 '.$date($this->substitute_holiday_date) : '振休未定'),
            ],
            'compensatory_leave' => [
                $this->actual_worked_hours !== null ? '勤務'.rtrim(rtrim((string) $this->actual_worked_hours, '0'), '.').'時間' : null,
                $this->compensatory_date ? '代休 '.$date($this->compensatory_date) : '代休未定',
            ],
            'telework' => [$this->work_location],
            default => [$days(), $this->reason_detail],
        };
    }

    /** 操作ログに残す「旧→新」の一文。 */
    public function amendmentSummary(): string
    {
        return implode('／', array_map(
            fn (array $c) => $c['label'].' '.$c['before'].' → '.$c['after'],
            $this->amendmentChanges()
        ));
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }
}
