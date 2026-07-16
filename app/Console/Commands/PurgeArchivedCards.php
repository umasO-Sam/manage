<?php

namespace App\Console\Commands;

use App\Models\Card;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('app:purge-archived-cards')]
#[Description('論理削除から5年（既定）を過ぎたカードを添付ファイルごと完全削除する')]
class PurgeArchivedCards extends Command
{
    public function handle(): void
    {
        $retentionYears = 5;
        $cutoff = now()->subYears($retentionYears);

        $cards = Card::onlyTrashed()->where('deleted_at', '<=', $cutoff)->with('attachments')->get();

        foreach ($cards as $card) {
            foreach ($card->attachments as $attachment) {
                Storage::disk('local')->delete($attachment->path);
            }

            $card->forceDelete();
        }

        $this->info("{$cards->count()} 件のカードを完全削除しました。");
    }
}
