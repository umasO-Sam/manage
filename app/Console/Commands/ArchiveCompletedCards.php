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
                // 差し戻し→再度最終段階へ、を繰り返したカードでは最終段階到達ログが
                // 複数存在しうるため、最も新しい到達時刻を保持期間の起点にする。
                // stageLogs()はorderBy('moved_at')昇順が既定なので、Collectionの
                // last()で最新のログを取る(query builder側でlatest()を足しても
                // 既定の昇順orderByが優先され最古が返ってしまうため使わない)。
                $finalLog = $card->stageLogs
                    ->where('stage_index', $lastStage)
                    ->where('is_reversal', false)
                    ->last();

                if ($finalLog && $finalLog->moved_at->lte($cutoff)) {
                    $card->delete();
                    $archived++;
                }
            }
        }

        $this->info("{$archived} 件のカードを論理削除しました。");
    }
}
