<?php

namespace App\Mail;

use App\Models\DailyReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * 作業日報が差し戻されたことを直す人に伝える。確認(承認)されたときは何も送らない
 * (差し戻しだけが行動を求めるため、ユーザーの指示で確認時の通知は出さない)。
 *
 * Xserver共用サーバーには常駐のキューワーカーを置けないため、他の通知と同様に
 * ShouldQueueにせず同期送信する。
 */
class DailyReportRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public DailyReport $dailyReport) {}

    public function envelope(): Envelope
    {
        $date = $this->dailyReport->work_date->format('Y/m/d');

        return new Envelope(
            subject: "【勤怠】作業日報が差し戻されました（{$date}・{$this->dailyReport->staff?->name}）",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.daily-report-rejected',
        );
    }
}
