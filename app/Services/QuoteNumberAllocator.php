<?php

namespace App\Services;

use App\Models\QuoteNumber;
use Illuminate\Support\Collection;

/**
 * 見積番号(注文番号)の採番。
 *
 * 形式: 客先番号 + 見積単位(3桁) + '-' + 見積区分(1英字) + 見積通番(2桁) [+ 補足区分(1英字) + 通番(2桁)]
 *
 * 案件の種類ごとに、どこを新しく採るかが変わる:
 *   新規案件            見積単位を老番+1、N01
 *   フェイク            見積単位を老番+1、F+通番
 *   構成や工事範囲の変更 見積単位は既存、Nの通番を+1
 *   追加請求/改造/修理/部品/変更 見積単位と元のN通番は既存、補足区分の通番を+1
 */
class QuoteNumberAllocator
{
    /** 案件種別 => [ラベル, 補足区分(nullなら見積区分側で採る)]。 */
    public const MODES = [
        'new' => ['label' => '新規案件', 'extra' => null],
        'additional' => ['label' => '未検収案件の追加請求', 'extra' => 'T'],
        'remodel' => ['label' => '改造案件', 'extra' => 'K'],
        'repair' => ['label' => '修理案件', 'extra' => 'S'],
        'parts' => ['label' => '部品提供', 'extra' => 'B'],
        'change' => ['label' => '金額・数量・納期の変更／期限切れ再提出', 'extra' => 'H'],
        'scope_change' => ['label' => '構成や工事範囲の変更', 'extra' => null],
        'fake' => ['label' => 'フェイク', 'extra' => null],
    ];

    /** 見積単位を新しく採る案件種別。 */
    private const NEW_UNIT_MODES = ['new', 'fake'];

    /**
     * 採番候補を組み立てる。入力が足りない場合は candidate を null にして
     * 「何を入力すれば決まるか」を missing で返す。
     *
     * @return array{candidate: string|null, unit_no: string|null, quote_type: string|null,
     *     quote_seq: string|null, extra_code: string|null, extra_seq: string|null,
     *     missing: array<int, string>, duplicate: bool}
     */
    public function build(string $customerCode, string $mode, ?string $unitNo, ?string $baseSeq): array
    {
        $customerCode = strtoupper(trim($customerCode));
        $extra = self::MODES[$mode]['extra'] ?? null;
        $missing = [];

        if ($customerCode === '') {
            return $this->result(null, null, null, null, null, ['客先番号'], false);
        }

        // 見積単位: 新規・フェイクは老番+1、それ以外は入力(過去注番リストから引用)。
        if (in_array($mode, self::NEW_UNIT_MODES, true)) {
            $unitNo = $this->nextUnitNo($customerCode);
        } else {
            $unitNo = $this->normalizeUnit($unitNo);
            if ($unitNo === null) {
                $missing[] = '見積単位';
            }
        }

        $quoteType = $mode === 'fake' ? QuoteNumber::TYPE_FAKE : QuoteNumber::TYPE_NORMAL;
        $quoteSeq = null;
        $extraSeq = null;

        // 補足区分を採る場合、元のN通番は入力してもらう。足りないものは
        // 見積単位と合わせて一度に返す(1つずつ聞き返さないため)。
        if ($extra !== null) {
            $quoteSeq = $this->normalizeSeq($baseSeq);
            if ($quoteSeq === null) {
                $missing[] = '元の見積通番';
            }
        }

        if ($missing === [] && $unitNo !== null) {
            $quoteSeq = $extra !== null
                ? $quoteSeq
                // 見積区分側で採る(新規=01から、範囲変更・フェイクは老番+1)。
                : $this->nextQuoteSeq($customerCode, $unitNo, $quoteType);

            if ($extra !== null) {
                $extraSeq = $this->nextExtraSeq($customerCode, $unitNo, $quoteType, $quoteSeq, $extra);
            }
        }

        if ($missing !== []) {
            return $this->result(null, $unitNo, $quoteType, $quoteSeq, $extra, $missing, false);
        }

        $candidate = $customerCode.$unitNo.'-'.$quoteType.$quoteSeq.($extra !== null ? $extra.$extraSeq : '');

        return [
            'candidate' => $candidate,
            'unit_no' => $unitNo,
            'quote_type' => $quoteType,
            'quote_seq' => $quoteSeq,
            'extra_code' => $extra,
            'extra_seq' => $extraSeq,
            'missing' => [],
            'duplicate' => QuoteNumber::where('full_no', $candidate)->exists(),
        ];
    }

    /**
     * その客先の見積単位の老番+1(3桁ゼロ埋め)。数字として読める過去分だけを見る。
     */
    public function nextUnitNo(string $customerCode): string
    {
        $max = QuoteNumber::where('customer_code', $customerCode)
            ->pluck('unit_no')
            ->filter(fn ($v) => ctype_digit((string) $v))
            ->map(fn ($v) => (int) $v)
            ->max() ?? 0;

        return str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * 同じ見積単位・見積区分の中で使える通番(老番+1、2桁ゼロ埋め)。
     */
    public function nextQuoteSeq(string $customerCode, string $unitNo, string $quoteType): string
    {
        $max = $this->sameUnit($customerCode, $unitNo)
            ->where('quote_type', $quoteType)
            ->whereNull('extra_code')
            ->pluck('quote_seq')
            ->filter(fn ($v) => ctype_digit((string) $v))
            ->map(fn ($v) => (int) $v)
            ->max() ?? 0;

        return str_pad((string) ($max + 1), 2, '0', STR_PAD_LEFT);
    }

    /**
     * 元の見積番号にぶら下がる補足区分の通番(老番+1、2桁ゼロ埋め)。
     */
    public function nextExtraSeq(string $customerCode, string $unitNo, string $quoteType, string $quoteSeq, string $extra): string
    {
        $prefix = $customerCode.$unitNo.'-'.$quoteType.$quoteSeq.$extra;

        $max = $this->sameUnit($customerCode, $unitNo)
            ->filter(fn (QuoteNumber $q) => str_starts_with((string) $q->full_no, $prefix))
            ->map(fn (QuoteNumber $q) => (int) substr((string) $q->full_no, strlen($prefix), 2))
            ->max() ?? 0;

        return str_pad((string) ($max + 1), 2, '0', STR_PAD_LEFT);
    }

    /**
     * 過去注番リスト。見積単位の降順で、案件種別に応じて絞り込む。
     *
     * @return Collection<int, QuoteNumber>
     */
    public function history(string $customerCode, ?string $mode = null): Collection
    {
        // 補足区分(T/K/S/B/H)が付いた注番も含めて全件出す。どの区分が何番まで
        // 使われているかを見ながら採番するため、絞り込まない。
        return QuoteNumber::where('customer_code', strtoupper(trim($customerCode)))
            ->with('staff')
            ->get()
            ->sortByDesc(fn (QuoteNumber $q) => [ctype_digit((string) $q->unit_no) ? (int) $q->unit_no : 0, $q->full_no])
            ->values();
    }

    /** @return Collection<int, QuoteNumber> */
    private function sameUnit(string $customerCode, string $unitNo): Collection
    {
        return QuoteNumber::where('customer_code', $customerCode)
            ->get()
            // 過去分は見積単位が2桁のこともあるため、数値として比較する。
            ->filter(fn (QuoteNumber $q) => ctype_digit((string) $q->unit_no) && (int) $q->unit_no === (int) $unitNo)
            ->values();
    }

    private function normalizeUnit(?string $unitNo): ?string
    {
        $unitNo = trim((string) $unitNo);

        return $unitNo !== '' && ctype_digit($unitNo) ? str_pad($unitNo, 3, '0', STR_PAD_LEFT) : null;
    }

    private function normalizeSeq(?string $seq): ?string
    {
        $seq = trim((string) $seq);

        return $seq !== '' && ctype_digit($seq) ? str_pad($seq, 2, '0', STR_PAD_LEFT) : null;
    }

    /**
     * @param  array<int, string>  $missing
     * @return array<string, mixed>
     */
    private function result(?string $candidate, ?string $unitNo, ?string $quoteType, ?string $quoteSeq, ?string $extra, array $missing, bool $duplicate): array
    {
        return [
            'candidate' => $candidate,
            'unit_no' => $unitNo,
            'quote_type' => $quoteType,
            'quote_seq' => $quoteSeq,
            'extra_code' => $extra,
            'extra_seq' => null,
            'missing' => $missing,
            'duplicate' => $duplicate,
        ];
    }
}
