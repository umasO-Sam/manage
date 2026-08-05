<?php

namespace App\Console\Commands;

use App\Models\Staff;
use App\Services\TimecardService;
use Illuminate\Console\Command;

/**
 * 別システム「timecard-new」の担当者(stuff.wid)と、manageの担当者アカウントを
 * 氏名で突き合わせて staff.timecard_wid に初期設定する。
 *
 * timecard側の氏名は「斉藤　修」のように全角スペース入りで、manage側はスペースなしの
 * ことが多いため、必ず正規化(全角/半角スペース除去)してから比較する
 * (AssignStaffSid・ImportLegacyPurchasingDataと同じ方針)。
 * 一致しなかった分はＩＤ管理画面から手動で紐づける。
 */
class MapTimecardStaff extends Command
{
    protected $signature = 'app:map-timecard-staff {--dry-run : 実際には保存せず結果だけ表示する}';

    protected $description = 'タイムカードの担当者ID(wid)を氏名一致でmanageの担当者へ割り当てる';

    public function handle(TimecardService $timecard): int
    {
        if (! $timecard->isEnabled()) {
            $this->error('タイムカードDBの接続が設定されていません(.env の TIMECARD_DB_DATABASE)。');

            return self::FAILURE;
        }

        $choices = $timecard->staffChoices();

        if ($choices === []) {
            $this->error('タイムカード側の担当者一覧を取得できませんでした。');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        $staffByNormalizedName = Staff::all()->keyBy(fn (Staff $s) => $this->normalize($s->name));

        $assigned = 0;
        $unchanged = 0;
        $notFound = [];

        foreach ($choices as $wid => $wname) {
            $staff = $staffByNormalizedName->get($this->normalize((string) $wname));

            if (! $staff) {
                $notFound[] = "wid {$wid}: {$wname}";

                continue;
            }

            if ((int) $staff->timecard_wid === (int) $wid) {
                $unchanged++;

                continue;
            }

            $this->line("wid {$wid}（{$wname}） -> {$staff->name} (id={$staff->id})"
                .($staff->timecard_wid !== null ? " ※既存 wid {$staff->timecard_wid} を上書き" : ''));

            if (! $dryRun) {
                $staff->timecard_wid = (int) $wid;
                $staff->save();
            }
            $assigned++;
        }

        $this->info(($dryRun ? '[dry-run] ' : '')."割当 {$assigned}件 / 変更なし {$unchanged}件");

        if ($notFound !== []) {
            $this->warn('manage側に該当する担当者が見つかりませんでした（ＩＤ管理画面から手動で紐づけてください）:');
            foreach ($notFound as $line) {
                $this->warn("  {$line}");
            }
        }

        return self::SUCCESS;
    }

    private function normalize(string $name): string
    {
        return str_replace(['　', ' '], '', $name);
    }
}
