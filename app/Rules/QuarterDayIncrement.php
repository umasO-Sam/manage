<?php

namespace App\Rules;

use App\Support\LeaveDays;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * 有給休暇の日数が0.25日単位(1日/半日/2時間)であることを求める。
 * 0.1刻みの値を受け付けると、2時間有休(0.25日)を差し引いた残数が
 * 0.25刻みで表せなくなるため、入口で弾く。
 */
class QuarterDayIncrement implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return;
        }

        if (! LeaveDays::isValidUnit($value)) {
            $fail('日数は0.25日単位（1日・0.5日・0.25日）で入力してください。');
        }
    }
}
