<?php

namespace App\Mail;

use App\Models\Card;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Xserver共用サーバーには常駐のキューワーカーを置けないため、あえて
 * ShouldQueue にせず同期送信する（構想仕様書のホスティング制約を参照）。
 */
class CardNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string  $headline  例: 「新しい購入部品手配の依頼が届きました」
     * @param  string  $actorLine  例: 「手配担当者: 山田太郎」（新規依頼時はnull）
     */
    public function __construct(
        public Card $card,
        public string $headline,
        public ?string $actorLine = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "【部品調達管理】{$this->headline}（注番: {$this->card->orderNumber->code}）",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.card-notification',
        );
    }
}
