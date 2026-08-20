<?php

namespace App\Support;

/**
 * 削除された物件の控え。
 *
 * 物件を削除するとカードも受注ヘッダも消えるため、そのときの内容と
 * それまでの物件履歴を文章に書き起こして操作ログに残している。
 * 物件履歴の画面はその文章を読み戻して表にする。
 *
 * 書き出しと読み戻しが食い違うと表が崩れるので、両方をこのクラスに置く。
 */
class DeletedProjectRecord
{
    /** @var array<string, string> キー => 控えの見出し。並び順もこのとおりに書き出す。 */
    public const FIELDS = [
        'order_no' => '注番',
        'product_name' => '件名',
        'recipient' => '受注先',
        'delivery_dest' => '納入先',
        'order_received_date' => '受注日',
        'order_amount' => '受注金額',
        'sales_date' => '売上日',
        'staff_name' => '社内担当者',
        'stage' => '削除時のステージ',
    ];

    public const HISTORY_HEADING = '--- それまでの物件履歴 ---';

    /**
     * @param  array<string, string>  $values  FIELDSのキー => 値
     * @param  array<int, string>  $history  物件履歴の行
     */
    public static function toText(array $values, array $history): string
    {
        $lines = [];

        foreach (self::FIELDS as $key => $label) {
            $lines[] = $label.': '.($values[$key] ?? '—');
        }

        if ($history !== []) {
            $lines[] = self::HISTORY_HEADING;
            foreach ($history as $line) {
                $lines[] = $line;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * 控えの文章を項目と履歴に分けて返す。読み取れない行は履歴として扱わず捨てる。
     *
     * 項目に分けられなかった控え(この形式にする前に削除されたもの)は、
     * fieldsを空のままにしてrawをそのまま渡す。画面側はrawを1行で出す。
     *
     * @return array{fields: array<string, string>, history: array<int, string>, raw: string}
     */
    public static function fromText(?string $text): array
    {
        $fields = array_fill_keys(array_keys(self::FIELDS), '');
        $history = [];
        $inHistory = false;

        foreach (preg_split('/\r\n|\r|\n/', (string) $text) as $line) {
            if ($line === self::HISTORY_HEADING) {
                $inHistory = true;

                continue;
            }

            if ($inHistory) {
                if (trim($line) !== '') {
                    $history[] = $line;
                }

                continue;
            }

            foreach (self::FIELDS as $key => $label) {
                if (str_starts_with($line, $label.': ')) {
                    $fields[$key] = mb_substr($line, mb_strlen($label) + 2);

                    break;
                }
            }
        }

        return ['fields' => $fields, 'history' => $history, 'raw' => (string) $text];
    }
}
