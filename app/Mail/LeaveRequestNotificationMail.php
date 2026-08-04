<?php

namespace App\Mail;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Xserver共用サーバーには常駐のキューワーカーを置けないため、CardNotificationMail
 * と同様にShouldQueueにせず同期送信する。
 */
class LeaveRequestNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string  $headline  例: 「休暇申請が届きました」「申請が承認されました」
     */
    public function __construct(
        public LeaveRequest $leaveRequest,
        public string $headline,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "【勤怠】{$this->headline}（{$this->leaveRequest->typeLabel()}・{$this->leaveRequest->staff->name}）",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.leave-request-notification',
        );
    }
}
