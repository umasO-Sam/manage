<?php

namespace App\Console\Commands;

use App\Models\Staff;
use Illuminate\Console\Command;

/**
 * 社内人工(日報)の一括登録でSIDによる担当者特定を行うため、既存アカウントへSIDを付与する。
 * 対応する担当者は氏名の完全一致(全角/半角スペース除去後)で特定する。
 */
class AssignStaffSid extends Command
{
    protected $signature = 'app:assign-staff-sid {--dry-run : 実際には保存せず結果だけ表示する}';

    protected $description = '氏名をキーにSIDを既存の担当者アカウントへ割り当てる';

    /** @var array<int, string> SID => 氏名 */
    private const SID_BY_NAME = [
        3 => '伊藤秀樹',
        4 => '鵜飼克彦',
        5 => '小林正治',
        6 => '久保田浩史',
        7 => '安保善司',
        8 => '松本好夫',
        9 => '戸田恭介',
        10 => '平塚庸時',
        11 => '逢澤俊輔',
        12 => '西山功',
        13 => '成沢友作',
        14 => '市邦安',
        15 => '水谷昭彦',
        17 => '森孝典',
        19 => '山口翔',
        20 => '柴田拓弥',
        22 => '佐竹一馬',
        24 => '瀧上祥子',
        25 => '斉藤浩一',
        26 => '斉藤富美',
        27 => '木村光昭',
        28 => '斉藤修',
        29 => '斉藤裕',
        30 => '戸谷正八',
        31 => '宮崎芳美',
        33 => '久保田真也',
        35 => '的屋俊一',
        36 => '安田将吾',
        39 => '服部蒼真',
        44 => '髙橋堅斗',
        46 => '石川雄清',
    ];

    /**
     * 「髙橋」(はしごだか)はアカウント側では標準字体「高橋」で登録されているため、
     * この1件だけ個別に読み替える(他の氏名には適用しない一対一の例外)。
     *
     * @var array<string, string>
     */
    private const NAME_ALIASES = [
        '髙橋堅斗' => '高橋堅斗',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $existingByNormalizedName = Staff::all()->keyBy(fn (Staff $s) => $this->normalize($s->name));

        $assigned = 0;
        $unchanged = 0;
        $notFound = [];

        foreach (self::SID_BY_NAME as $sid => $name) {
            $lookupName = self::NAME_ALIASES[$name] ?? $name;
            $staff = $existingByNormalizedName->get($this->normalize($lookupName));

            if (! $staff) {
                $notFound[] = "SID {$sid}: {$name}";

                continue;
            }

            if ($staff->sid === $sid) {
                $unchanged++;

                continue;
            }

            $this->line("SID {$sid} -> {$staff->name} (id={$staff->id})".($staff->sid !== null ? " ※既存SID {$staff->sid} を上書き" : ''));

            if (! $dryRun) {
                $staff->sid = $sid;
                $staff->save();
            }
            $assigned++;
        }

        $this->info(($dryRun ? '[dry-run] ' : '')."割当 {$assigned}件 / 変更なし {$unchanged}件");

        if ($notFound !== []) {
            $this->warn('該当する担当者が見つかりませんでした:');
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
