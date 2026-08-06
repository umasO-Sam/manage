<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 見積番号(注文番号)台帳の1件。
 *
 * 形式: 客先番号 + 見積単位 + '-' + 見積区分 + 見積通番 + 補足区分(最大3組)
 */
#[Fillable([
    'full_no', 'customer_code', 'unit_no', 'suffix', 'quote_type', 'quote_seq', 'extra_code',
    'project_name', 'delivery_dest', 'customer_contact', 'remarks', 'note_no', 'completed_on', 'staff_id', 'source',
])]
class QuoteNumber extends Model
{
    use HasFactory;

    /** 見積区分。 */
    public const TYPE_NORMAL = 'N';

    public const TYPE_FAKE = 'F';

    /**
     * 補足区分。Dは2023/6/1以降使わない(過去データにのみ存在する)。
     *
     * @var array<string, string>
     */
    public const EXTRA_CODES = [
        'T' => '追加（元の見積に売上を合算する）',
        'K' => '改造（個別に売上管理）',
        'S' => '修理（個別に売上管理）',
        'B' => '部品（個別に売上管理）',
        'H' => '変更（金額・数量・納期などの変更、再提出）',
    ];

    /** 過去データにのみ存在し、新規では選べない補足区分。 */
    public const RETIRED_EXTRA_CODES = ['D' => '電気（2023/6/1以降は使用しない）'];

    /**
     * ハイフン以降を「見積区分 + 見積通番 + 補足区分(1英字+2桁)…」としてパースする。
     * 規約どおりでない過去データ(枝番 -A、廃止区分Dの単独使用など)はnullを返し、
     * 表示は原文のまま、採番候補の計算からは外す。
     *
     * @return array{quote_type: string, quote_seq: string, extra_code: string|null}|null
     */
    public static function parseSuffix(?string $suffix): ?array
    {
        if (! $suffix || ! preg_match('/^([A-Z])(\d{2,3})((?:[A-Z]\d{2,3})*)$/', trim($suffix), $m)) {
            return null;
        }

        return [
            'quote_type' => $m[1],
            'quote_seq' => $m[2],
            'extra_code' => $m[3] !== '' ? substr($m[3], 0, 1) : null,
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function customerCode(): BelongsTo
    {
        return $this->belongsTo(CustomerCode::class, 'customer_code', 'code');
    }

    /** 見積単位を3桁ゼロ埋めで揃えた表示(過去データは2桁のこともある)。 */
    public function paddedUnitNo(): string
    {
        return ctype_digit($this->unit_no) ? str_pad($this->unit_no, 3, '0', STR_PAD_LEFT) : $this->unit_no;
    }
}
