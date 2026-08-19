<?php

namespace App\Support;

/**
 * 有給休暇の日数の扱い。1日・半日(0.5日)・2時間(0.25日)単位で取得するため、
 * 最小単位は0.25日であり、計算も表示も0.25刻みで揃える。
 *
 * 小数第1位に丸めると0.25が0.3になってしまうので、
 * 表示は必ずこのクラスのformat()を通す(小数第2位まで持ち、末尾の0は落とす)。
 */
class LeaveDays
{
    /** 2時間有休 = 所定労働時間8時間の1/4。 */
    public const UNIT = 0.25;

    /** 0.25日単位になっているか(付与日数の入力チェック用)。 */
    public static function isValidUnit(int|float|string $days): bool
    {
        $quarters = (float) $days / self::UNIT;

        return abs($quarters - round($quarters)) < 1e-9;
    }

    /**
     * 日数を0.25刻みのまま表示する。1 → "1"、0.5 → "0.5"、0.25 → "0.25"、14.25 → "14.25"。
     */
    public static function format(int|float|string|null $days): string
    {
        if ($days === null || $days === '') {
            return '';
        }

        $formatted = number_format((float) $days, 2, '.', '');

        return str_contains($formatted, '.')
            ? rtrim(rtrim($formatted, '0'), '.')
            : $formatted;
    }
}
