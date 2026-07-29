<?php

namespace App\Console\Commands;

use App\Models\LaborCost;
use App\Models\Staff;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * app:import-legacy-purchasing-data の氏名マッチングが完全一致(===)だったため、
 * Access側の氏名に入っている全角スペース(例:「鵜飼　克彦」)と実アカウントの氏名
 * (スペースなし「鵜飼克彦」)が一致せず、同一人物のダミーアカウント(legacy-*)が
 * 重複作成されてしまった。このコマンドは、スペースを無視して氏名が一致する
 * 実アカウント/ダミーアカウントの組を見つけ、
 *   1. is_labor_target・position_weightをダミー側から実アカウントへコピー
 *   2. labor_costs.staff_idをダミーのIDから実アカウントのIDへ付け替え
 *   3. 重複したダミーアカウントを削除
 * する。実アカウントのlogin_id・email・password等は一切変更しない。
 */
#[Signature('app:merge-duplicate-legacy-staff {--dry-run : 実際には変更せず対象一覧のみ表示する}')]
#[Description('氏名の全角スペース差により重複作成されたlegacy-*ダミーアカウントを実アカウントへ統合する')]
class MergeDuplicateLegacyStaff extends Command
{
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $normalize = fn (string $name): string => str_replace(['　', ' '], '', $name);

        $realStaff = Staff::where('login_id', 'not like', 'legacy-%')->get();
        $legacyStaff = Staff::where('login_id', 'like', 'legacy-%')->get();

        $pairs = [];
        foreach ($realStaff as $real) {
            foreach ($legacyStaff as $legacy) {
                if ($normalize($real->name) === $normalize($legacy->name)) {
                    $pairs[] = [$real, $legacy];
                }
            }
        }

        if (empty($pairs)) {
            $this->info('重複するアカウントは見つかりませんでした。');

            return self::SUCCESS;
        }

        $this->info(count($pairs).'件の重複を検出しました:');
        foreach ($pairs as [$real, $legacy]) {
            $laborCount = LaborCost::where('staff_id', $legacy->id)->count();
            $this->line("  {$real->login_id}({$real->name}) <- {$legacy->login_id}({$legacy->name}) [labor_costs: {$laborCount}件を付け替え]");
        }

        if ($dryRun) {
            $this->warn('--dry-run のため変更は行っていません。');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($pairs) {
            foreach ($pairs as [$real, $legacy]) {
                $real->update([
                    'is_labor_target' => $legacy->is_labor_target,
                    'position_weight' => $legacy->position_weight,
                ]);

                LaborCost::where('staff_id', $legacy->id)->update(['staff_id' => $real->id]);

                $legacy->delete();
            }
        });

        $this->info('統合が完了しました。');

        return self::SUCCESS;
    }
}
