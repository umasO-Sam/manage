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

        // retention_days が null のボード(物件管理)は「非表示後も削除しない」方針のため対象外。
        $cards = Card::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->whereHas('workflowType', fn ($q) => $q->whereNotNull('retention_days'))
            ->with('attachments')
            ->get();

        foreach ($cards as $card) {
            foreach ($card->attachments as $attachment) {
                Storage::disk('local')->delete($attachment->path);
            }

            $card->forceDelete();
        }

        $this->info("{$cards->count()} 件のカードを完全削除しました。");
    }
}
