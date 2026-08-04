<?php

namespace App\Console\Commands;

use App\Models\Holiday;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * 日本の国民の祝日(振替休日・国民の休日を含む)を休日マスタに投入する。
 * 春分の日・秋分の日は例年2月頃に官報で正式決定されるため、ここでは広く知られた
 * 天文計算の近似式(国立天文台の計算に基づく式、1980年基準)を使う。将来的に
 * 官報の実際の告示と食い違う場合は、休日マスタ画面から個別に修正できる。
 */
class SeedJapaneseHolidays extends Command
{
    protected $signature = 'holidays:seed-japan {year=2026 : 対象年}';

    protected $description = '指定年(既定2026)の日本の祝日を休日マスタに投入する';

    public function handle(): int
    {
        $year = (int) $this->argument('year');

        $holidays = $this->buildHolidays($year);
        $holidays = $this->applySubstituteHolidays($holidays);
        $holidays = $this->applyCitizensHolidays($holidays);

        usort($holidays, fn (array $a, array $b) => $a['date']->lte($b['date']) ? -1 : 1);

        $created = 0;
        $skipped = 0;
        foreach ($holidays as $h) {
            $holiday = Holiday::firstOrNew(['date' => $h['date']->format('Y-m-d')]);
            if ($holiday->exists) {
                $skipped++;

                continue;
            }
            $holiday->fill(['name' => $h['name'], 'type' => Holiday::TYPE_PUBLIC_HOLIDAY])->save();
            $created++;
        }

        $this->info("{$year}年の祝日: 新規{$created}件 / 既存のためスキップ{$skipped}件");

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{date: Carbon, name: string}>
     */
    private function buildHolidays(int $year): array
    {
        $fixed = [
            [1, 1, '元日'],
            [2, 11, '建国記念の日'],
            [2, 23, '天皇誕生日'],
            [4, 29, '昭和の日'],
            [5, 3, '憲法記念日'],
            [5, 4, 'みどりの日'],
            [5, 5, 'こどもの日'],
            [8, 11, '山の日'],
            [11, 3, '文化の日'],
            [11, 23, '勤労感謝の日'],
        ];

        $holidays = [];
        foreach ($fixed as [$month, $day, $name]) {
            $holidays[] = ['date' => Carbon::create($year, $month, $day), 'name' => $name];
        }

        $holidays[] = ['date' => $this->nthWeekday($year, 1, Carbon::MONDAY, 2), 'name' => '成人の日'];
        $holidays[] = ['date' => $this->nthWeekday($year, 7, Carbon::MONDAY, 3), 'name' => '海の日'];
        $holidays[] = ['date' => $this->nthWeekday($year, 9, Carbon::MONDAY, 3), 'name' => '敬老の日'];
        $holidays[] = ['date' => $this->nthWeekday($year, 10, Carbon::MONDAY, 2), 'name' => 'スポーツの日'];

        $holidays[] = ['date' => $this->equinox($year, 'spring'), 'name' => '春分の日'];
        $holidays[] = ['date' => $this->equinox($year, 'autumn'), 'name' => '秋分の日'];

        return $holidays;
    }

    private function nthWeekday(int $year, int $month, int $weekday, int $nth): Carbon
    {
        $date = Carbon::create($year, $month, 1);
        $offset = ($weekday - $date->dayOfWeek + 7) % 7;

        return $date->addDays($offset + ($nth - 1) * 7);
    }

    /**
     * 春分・秋分の日の近似式(国立天文台の計算式に基づく一般に知られた近似、1980年基準)。
     */
    private function equinox(int $year, string $season): Carbon
    {
        $offset = $year - 1980;
        $base = $season === 'spring' ? 20.8431 : 23.2488;
        $day = (int) floor($base + 0.242194 * $offset - floor($offset / 4));

        return Carbon::create($year, $season === 'spring' ? 3 : 9, $day);
    }

    /**
     * 日曜と重なる祝日は、翌日以降で最初の非祝日を振替休日とする。
     *
     * @param  array<int, array{date: Carbon, name: string}>  $holidays
     * @return array<int, array{date: Carbon, name: string}>
     */
    private function applySubstituteHolidays(array $holidays): array
    {
        $dates = collect($holidays)->map(fn (array $h) => $h['date']->format('Y-m-d'))->all();
        $result = $holidays;

        foreach ($holidays as $h) {
            if ($h['date']->dayOfWeek !== Carbon::SUNDAY) {
                continue;
            }

            $substitute = $h['date']->copy()->addDay();
            while (in_array($substitute->format('Y-m-d'), $dates, true)) {
                $substitute->addDay();
            }

            $result[] = ['date' => $substitute, 'name' => '振替休日'];
            $dates[] = $substitute->format('Y-m-d');
        }

        return $result;
    }

    /**
     * 前後を祝日に挟まれた平日(敬老の日と秋分の日の間など)は「国民の休日」となる。
     *
     * @param  array<int, array{date: Carbon, name: string}>  $holidays
     * @return array<int, array{date: Carbon, name: string}>
     */
    private function applyCitizensHolidays(array $holidays): array
    {
        $dates = collect($holidays)->map(fn (array $h) => $h['date']->format('Y-m-d'))->all();
        $result = $holidays;

        foreach ($holidays as $h) {
            $between = $h['date']->copy()->addDay();
            if ($between->dayOfWeek === Carbon::SUNDAY) {
                continue;
            }
            if (in_array($between->format('Y-m-d'), $dates, true)) {
                continue;
            }
            $nextDay = $between->copy()->addDay();
            if (! in_array($nextDay->format('Y-m-d'), $dates, true)) {
                continue;
            }

            $result[] = ['date' => $between, 'name' => '国民の休日'];
            $dates[] = $between->format('Y-m-d');
        }

        return $result;
    }
}
