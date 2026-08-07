<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'major_category', 'sub_category', 'item_name', 'is_parts', 'is_internal', 'is_outsourcing'])]
class CategoryCode extends Model
{
    /**
     * 作業日報で注番を選ばなくても登録できる分類コード。
     * 打合見積(62)は受注前で注番が決まっていないことがあるため注番を求めない。
     * 研修など(69)・管理(70)・空き(71)はそもそも特定の注番に紐づかない。
     *
     * 画面(なぞって選択の反映ボタン)とサーバー側の保存で同じ判定を使う。
     * 片方だけ変えると「画面では反映できるのに保存されない」というずれが出るため、
     * ここを唯一の定義とし、画面へはビューに渡して使う。
     */
    public const ORDER_NO_OPTIONAL_CODES = [62, 69, 70, 71];

    protected function casts(): array
    {
        return [
            'is_parts' => 'boolean',
            'is_internal' => 'boolean',
            'is_outsourcing' => 'boolean',
        ];
    }
}
