<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'is_protected', 'project_name', 'show_in_dropdown'])]
class OrderNumber extends Model
{
    /**
     * 標準形式: 「英字1〜3文字＋数字」-「見積区分1英字＋2桁通番」の繰り返し。
     * 例: Q001-N01 / R101-N01B01 / MEI001-N01 / JSS11-N05B01H01
     * OrderNumberController・ProjectBoardControllerの登録バリデーションと共有する。
     */
    public const FORMAT_REGEX = '/^[A-Za-z]{1,3}\d{1,5}-(?:[A-Za-z]\d{2})+$/';

    /** ハイフン以降を持たない装置番号だけの入力に補う既定の見積区分・通番。 */
    public const DEFAULT_SUFFIX = 'N01';

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
     * プルダウンから外した注番。画面の「非表示の注番も表示」で追加の選択肢として出す
     * (外した注番をどうしても選びたい場合に、注番管理を開かずに済ませるため)。
     *
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public static function hiddenFromDropdown(): \Illuminate\Database\Eloquent\Builder
    {
        return static::where('show_in_dropdown', false)->orderBy('code');
    }

    /**
     * 入力された注番を登録前に整える。
     *
     * 全角で入力された英数字・ハイフンは半角に直す(過去に全角のＱで登録された注番が
     * 混ざっており、形式チェックにも検索にも掛からなくなるため)。
     * 「Q511」のように装置番号だけが入力された場合は、見積区分・通番の既定値を補って
     * 「Q511-N01」として扱う。
     */
    public static function normalizeCode(?string $code): string
    {
        $code = trim(mb_convert_kana((string) $code, 'as'));

        if (preg_match('/^[A-Za-z]{1,3}\d{1,5}$/', $code)) {
            $code .= '-'.self::DEFAULT_SUFFIX;
        }

        return $code;
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
