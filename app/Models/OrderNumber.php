<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'is_protected', 'project_name', 'show_in_dropdown'])]
class OrderNumber extends Model
{
    /** 標準形式: 「英数1〜8文字」-「英数2〜12文字」。OrderNumberControllerの登録バリデーションと共有する。 */
    public const FORMAT_REGEX = '/^[A-Za-z0-9]{1,8}-[A-Za-z0-9]{2,12}$/';

    protected function casts(): array
    {
        return [
            'is_protected' => 'boolean',
            'show_in_dropdown' => 'boolean',
        ];
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    /**
     * 依頼・作業日報・休暇申請の注番プルダウンに出す注番を、注番の昇順で返す。
     * 終わった案件を選択肢から外しても、既存のレコードが持つ注番は変わらない
     * (編集画面では現在の注番を選択肢に足して、選び直さずに保存できるようにする)。
     *
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public static function forDropdown(): \Illuminate\Database\Eloquent\Builder
    {
        return static::where('show_in_dropdown', true)->orderBy('code');
    }

    /**
     * 標準形式(self::FORMAT_REGEX)に合致するか。
     * 保護レコード(未定/社内)や形式チェックを解除して登録した注番はfalseになる。
     */
    public function matchesStandardFormat(): bool
    {
        return (bool) preg_match(self::FORMAT_REGEX, $this->code);
    }

    /** 注番選択プルダウン等での表示用ラベル(工事名があれば併記)。 */
    public function displayLabel(): string
    {
        return $this->project_name ? "{$this->code}（{$this->project_name}）" : $this->code;
    }
}
