<?php

use App\Models\WorkflowType;
use Illuminate\Database\Migrations\Migration;

/**
 * 物件管理ボードの「請求済」へ進む条件から、取引条件の確定(blocked_when_pending)を外す。
 *
 * 取引条件の調整は請求業務と並行して進むことが多く、条件が未確定というだけで
 * ボードの進行を止めると実態と合わなかった。カードの「取引条件調整中」バッジは
 * 残すので、調整が要ることは今までどおり分かる。
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rewriteInvoiceStage(fn (array $requires) => array_diff_key($requires, ['blocked_when_pending' => true]));
    }

    public function down(): void
    {
        $this->rewriteInvoiceStage(fn (array $requires) => [...$requires, 'blocked_when_pending' => true]);
    }

    private function rewriteInvoiceStage(callable $rewrite): void
    {
        $workflow = WorkflowType::where('slug', WorkflowType::SLUG_PROJECT)->first();

        if ($workflow === null) {
            return;
        }

        $stages = $workflow->stage_definition;

        foreach ($stages as $index => $stage) {
            if (($stage['label'] ?? null) !== '請求済') {
                continue;
            }

            $requires = $rewrite($stage['requires'] ?? []);
            $stages[$index]['requires'] = $requires;
        }

        $workflow->update(['stage_definition' => $stages]);
    }
};
