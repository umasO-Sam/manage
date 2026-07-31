<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * 拡張子がjpg/jpeg/png/gif/webpを名乗るファイルは、実際に画像データであることを
 * getimagesize()で検証する。HTML/スクリプトを画像拡張子に偽装したアップロードを
 * ここで弾く（他の許可拡張子はextensionsルールのみで、ここでは何もしない）。
 */
class UploadedFileMatchesImageExtension implements ValidationRule
{
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $extension = strtolower($value->getClientOriginalExtension());

        if (! in_array($extension, self::IMAGE_EXTENSIONS, true)) {
            return;
        }

        if (@getimagesize($value->getRealPath()) === false) {
            $fail('画像ファイルとして認識できない内容のため、アップロードできません。');
        }
    }
}
