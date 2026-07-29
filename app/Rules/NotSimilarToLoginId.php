<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * パスワードにログインIDが部分文字列として含まれる(またはその逆)ことを禁止する。
 * 完全一致だけでなく「ログインIDを少し変えただけ」のパスワードも弾くための最低限のチェック。
 */
class NotSimilarToLoginId implements ValidationRule
{
    public function __construct(private readonly ?string $loginId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->loginId === null || $this->loginId === '' || ! is_string($value)) {
            return;
        }

        $normalizedValue = mb_strtolower($value);
        $normalizedLoginId = mb_strtolower($this->loginId);

        if (str_contains($normalizedValue, $normalizedLoginId) || str_contains($normalizedLoginId, $normalizedValue)) {
            $fail('パスワードにログインIDと似た文字列を含めることはできません。');
        }
    }
}
