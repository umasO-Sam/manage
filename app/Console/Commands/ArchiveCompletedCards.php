<?php

namespace App\Console\Commands;

use App\Models\Card;
use App\Models\WorkflowType;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:archive-completed-cards')]
#[Description('最終ステージに到達してから保持期間（既定7日）を過ぎたカードを論理削除する')]
class ArchiveCompletedCards extends Command
{
    public function handle(): void
    {
        $archived = 0;

        foreach (WorkflowType::all() as $workflowType) {
            $lastStage = $workflowType->lastStageIndex();
            $cutoff = now()->subDays($workflowType->retention_days);

            $cards = Card::where('workflow_type_id', $workflowType->id)
                ->where('current_stage', $lastStage)
                ->get();

            foreach ($cards as $card) {
                $finalLog = $card->stageLogs()->where('stage_index', $lastStage)->first();

                if ($finalLog && $finalLog->moved_at->lte($cutoff)) {
                    $card->delete();
                    $archived++;
                }
            }
        }

        $this->info("{$archived} 件のカードを論理削除しました。");
    }
}
