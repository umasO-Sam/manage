<?php

namespace App\Console\Commands;

use App\Models\OperationLog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:prune-operation-logs')]
#[Description('作成から5年（既定）を過ぎた操作ログを削除する')]
class PruneOperationLogs extends Command
{
    public function handle(): void
    {
        $retentionYears = 5;
        $cutoff = now()->subYears($retentionYears);

        $count = OperationLog::where('created_at', '<=', $cutoff)->delete();

        $this->info("{$count} 件の操作ログを削除しました。");
    }
}
