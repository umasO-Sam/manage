<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'staff_id', 'type', 'start_date', 'end_date', 'granularity', 'half_day_period', 'hours',
    'reason_code', 'reason_detail', 'day_count', 'order_no', 'work_location',
    'substitute_holiday_date', 'no_substitute_needed', 'actual_worked_hours',
    'compensatory_date', 'approver_id', 'status', 'rejection_reason',
    'approved_at', 'remarks',
    'funeral_venue_address', 'funeral_venue_phone', 'wake_datetime',
    'funeral_datetime', 'flowers_declined', 'telegram_declined',
])]
class LeaveRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_WITHDRAWN = 'withdrawn';

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

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
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
            $this->type === 'paid_leave' && $this->granularity === 'hours' => '2H有休',
            $this->type === 'paid_leave' && $this->granularity === 'half_day' && $this->half_day_period === 'am' => 'AM半休',
            $this->type === 'paid_leave' && $this->granularity === 'half_day' && $this->half_day_period === 'pm' => 'PM半休',
            $this->type === 'paid_leave' && $this->granularity === 'half_day' => '半休',
            $this->type === 'telework' => '在宅',
            $this->type === 'holiday_work' => '休出',
            default => mb_substr($this->typeLabel(), 0, 4),
        };
    }

    public function reasonLabel(): ?string
    {
        return self::REASONS[$this->type][$this->reason_code]['label'] ?? null;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => '承認待ち',
            self::STATUS_APPROVED => '承認済み',
            self::STATUS_REJECTED => '却下',
            self::STATUS_WITHDRAWN => '取消済み',
            default => $this->status,
        };
    }
}
