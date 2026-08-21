<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;

/**
 * 作業日報・休暇/勤務申請まわりの操作履歴。法定保存期間に準じ5年間保持し、
 * PurgeOperationLogsコマンドで期限切れ分を削除する。経理資材担当・上長は
 * 全員分を、それ以外の一般社員・営業担当は自分(owner_staff_id)の分のみ閲覧できる。
 */
#[Fillable(['staff_id', 'owner_staff_id', 'action', 'subject_type', 'subject_id', 'description'])]
class OperationLog extends Model
{
    public const ACTION_DAILY_REPORT_SUBMIT = 'daily_report_submit';

    public const ACTION_DAILY_REPORT_RESUBMIT = 'daily_report_resubmit';

    public const ACTION_DAILY_REPORT_CONFIRM = 'daily_report_confirm';

    public const ACTION_DAILY_REPORT_REJECT = 'daily_report_reject';

    /** 勤怠管理者が本人に代わって提出した作業日報。誰の分かは対象者側に記録する。 */
    public const ACTION_DAILY_REPORT_PROXY_SUBMIT = 'daily_report_proxy_submit';

    public const ACTION_LEAVE_REQUEST_CREATE = 'leave_request_create';

    public const ACTION_LEAVE_REQUEST_PROXY_CREATE = 'leave_request_proxy_create';

    public const ACTION_LEAVE_REQUEST_WITHDRAW = 'leave_request_withdraw';

    public const ACTION_LEAVE_REQUEST_APPROVE = 'leave_request_approve';

    public const ACTION_LEAVE_REQUEST_REJECT = 'leave_request_reject';

    public const ACTION_LEAVE_REQUEST_CANCEL_REQUEST = 'leave_request_cancel_request';

    public const ACTION_LEAVE_REQUEST_CANCEL_APPROVE = 'leave_request_cancel_approve';

    public const ACTION_LEAVE_REQUEST_CANCEL_REJECT = 'leave_request_cancel_reject';

    public const ACTION_LEAVE_REQUEST_CANCEL_REFLECT = 'leave_request_cancel_reflect';

    public const ACTION_LEAVE_REQUEST_CANCEL_SEND_BACK = 'leave_request_cancel_send_back';

    public const ACTION_LEAVE_REQUEST_ATTENDANCE_APPROVE = 'leave_request_attendance_approve';

    public const ACTION_LEAVE_REQUEST_ATTENDANCE_REJECT = 'leave_request_attendance_reject';

    public const ACTION_LABOR_RECORD_UPDATE = 'labor_record_update';

    public const ACTION_LABOR_RECORD_DELETE = 'labor_record_delete';

    /** 物件カードの削除(間違って登録したカードの取り消し)。レコードごと消すため記録を残す。 */
    public const ACTION_PROJECT_CARD_DELETE = 'project_card_delete';

    /** 分類の説明(作業日報の「選択中」に出す内訳)の変更。全員の画面に出るため記録を残す。 */
    public const ACTION_CATEGORY_ITEM_NAME_UPDATE = 'category_item_name_update';

    /** @var array<string, string> action値 => 表示名 */
    public const ACTIONS = [
        self::ACTION_PROJECT_CARD_DELETE => '物件カードを削除',
        self::ACTION_DAILY_REPORT_SUBMIT => '作業日報を提出',
        self::ACTION_DAILY_REPORT_RESUBMIT => '作業日報を修正提出',
        self::ACTION_DAILY_REPORT_CONFIRM => '作業日報を確認',
        self::ACTION_DAILY_REPORT_REJECT => '作業日報を差し戻し',
        self::ACTION_DAILY_REPORT_PROXY_SUBMIT => '作業日報を代理提出（勤怠管理者）',
        self::ACTION_LEAVE_REQUEST_CREATE => '休暇・休出を申請',
        self::ACTION_LEAVE_REQUEST_PROXY_CREATE => '休暇・休出を代理申請（勤怠管理者）',
        self::ACTION_LEAVE_REQUEST_WITHDRAW => '申請を取消',
        self::ACTION_LEAVE_REQUEST_APPROVE => '申請を承認',
        self::ACTION_LEAVE_REQUEST_REJECT => '申請を却下',
        self::ACTION_LEAVE_REQUEST_CANCEL_REQUEST => '承認済み申請の取消を申請',
        self::ACTION_LEAVE_REQUEST_CANCEL_APPROVE => '取消申請を承認（上長）',
        self::ACTION_LEAVE_REQUEST_CANCEL_REJECT => '取消申請を差し戻し（上長）',
        self::ACTION_LEAVE_REQUEST_CANCEL_REFLECT => '取消を反映（勤怠管理者）',
        self::ACTION_LEAVE_REQUEST_CANCEL_SEND_BACK => '取消を差し戻し（勤怠管理者）',
        self::ACTION_LEAVE_REQUEST_ATTENDANCE_APPROVE => '休日勤務を承認（勤怠管理者）',
        self::ACTION_LEAVE_REQUEST_ATTENDANCE_REJECT => '休日勤務を差し戻し（勤怠管理者）',
        self::ACTION_LABOR_RECORD_UPDATE => '人工レコードを修正',
        self::ACTION_LABOR_RECORD_DELETE => '人工レコードを削除',
        self::ACTION_CATEGORY_ITEM_NAME_UPDATE => '分類の説明を変更',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'owner_staff_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }

    /**
     * 現在ログイン中の担当者を実行者として記録する。
     */
    public static function record(string $action, Model $subject, int $ownerStaffId, ?string $description = null): self
    {
        return static::create([
            'staff_id' => Auth::id(),
            'owner_staff_id' => $ownerStaffId,
            'action' => $action,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'description' => $description,
        ]);
    }
}
