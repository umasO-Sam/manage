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
 *   追加請求/改造/修理/部品/変更 既存の注番の後ろに補足区分+通番を足す
 *
 * 補足区分は多段になりうる。特にH(変更)はNの後ろだけでなくT/K/S/Bの後ろにも付く
 * (例: N01K01H01 = 改造案件K01の見積を変更したもの)。そのため元番号は「通番」ではなく
 * ハイフン以降の注番そのもの(N01 / N01K01 など)で受け取る。
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
     * 元注番が無ければ新規案件(N)として採る案件種別。
     * 改造・修理・部品は「過去の自社装置にリンクさせやすいように」補足区分にしているだけで、
     * 対象になる過去注番が無ければ普通の新規案件として採る。
     */
    private const OPTIONAL_BASE_MODES = ['remodel', 'repair', 'parts'];

    /**
     * 採番候補を組み立てる。入力が足りない場合は candidate を null にして
     * 「何を入力すれば決まるか」を missing で返す。
     *
     * @return array{candidate: string|null, unit_no: string|null, quote_type: string|null,
     *     quote_seq: string|null, extra_code: string|null, extra_seq: string|null,
     *     missing: array<int, string>, duplicate: bool}
     */
    public function build(string $customerCode, string $mode, ?string $unitNo, ?string $baseNo): array
    {
        $customerCode = strtoupper(trim($customerCode));
        $extra = self::MODES[$mode]['extra'] ?? null;
        $missing = [];

        if ($customerCode === '') {
            return $this->result(null, null, null, null, null, ['客先番号'], false);
        }

        $baseSuffix = $extra !== null ? $this->normalizeBaseNo($baseNo) : null;

        // 過去注番リストから引用したのに元番号を読み取れない場合は、N01があったものとして扱う。
        // 見積台帳の大半は「Q511」のようにハイフン以降を持たない旧形式で、そのままでは
        // 元番号が空として新規案件に倒れてしまうため。補ったことは画面側で注意喚起する。
        $quotedOldFormat = $extra !== null
            && $baseSuffix === null
            && $this->normalizeUnit($unitNo) !== null;

        if ($quotedOldFormat) {
            $baseSuffix = QuoteNumber::TYPE_NORMAL.'01';
        }

        // 改造・修理・部品で元注番が無い場合は、過去の自社装置に紐づかない案件なので
        // 補足区分を付けず新規案件(N)として採番する。間違えやすいので画面側で注釈を出す。
        $fellBackToNew = $extra !== null
            && $baseSuffix === null
            && in_array($mode, self::OPTIONAL_BASE_MODES, true);

        if ($fellBackToNew) {
            $extra = null;
        }

        // 見積単位: 新規・フェイク(と上の切り替わり)は老番+1、それ以外は入力(過去注番リストから引用)。
        if ($fellBackToNew || in_array($mode, self::NEW_UNIT_MODES, true)) {
            $unitNo = $this->nextUnitNo($customerCode);
        } else {
            $unitNo = $this->normalizeUnit($unitNo);
            if ($unitNo === null) {
                // 画面上の呼び名は「通番」。
                $missing[] = '通番';
            }
        }

        $quoteType = $mode === 'fake' ? QuoteNumber::TYPE_FAKE : QuoteNumber::TYPE_NORMAL;
        $quoteSeq = null;
        $extraSeq = null;

        // 補足区分を採る場合、元番号(ハイフン以降)は必須。足りないものは見積単位と
        // 合わせて一度に返す(1つずつ聞き返さないため)。
        if ($extra !== null && $baseSuffix === null) {
            $missing[] = '元の見積番号';
        }

        if ($missing !== []) {
            return $this->result(null, $unitNo, $quoteType, null, $extra, $missing, false);
        }

        if ($extra !== null) {
            // 元番号の先頭グループを見積区分・見積通番として保持する。
            $parsed = QuoteNumber::parseSuffix($baseSuffix);
            $quoteType = $parsed['quote_type'] ?? $quoteType;
            $quoteSeq = $parsed['quote_seq'] ?? null;
            $extraSeq = $this->nextExtraSeq($customerCode, $unitNo, $baseSuffix, $extra);
        } else {
            // 見積区分側で採る(新規=01から、範囲変更・フェイクは老番+1)。
            $quoteSeq = $this->nextQuoteSeq($customerCode, $unitNo, $quoteType);
        }

        // 元番号(base_suffix)は N01K10 のように多段になりうる。表示や保存で
        // quote_type + quote_seq から組み立て直すと途中の区分が落ちるため、
        // ハイフン以降は必ずこの値を使うこと。
        $baseSuffix ??= $quoteType.$quoteSeq;
        $suffix = $baseSuffix.($extra !== null ? $extra.$extraSeq : '');
        $candidate = $customerCode.$unitNo.'-'.$suffix;

        return [
            'candidate' => $candidate,
            'fell_back_to_new' => $fellBackToNew,
            'quoted_old_format' => $quotedOldFormat,
            'unit_no' => $unitNo,
            'base_suffix' => $baseSuffix,
            'suffix' => $suffix,
            'quote_type' => $quoteType,
            'quote_seq' => $quoteSeq,
            'extra_code' => $extra,
            'extra_seq' => $extraSeq,
            'missing' => [],
            'duplicate' => $this->isTaken($candidate),
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
     * 元番号にぶら下がる補足区分の通番(老番+1、2桁ゼロ埋め)。
     * 元番号は N01 のような通常番号だけでなく、N01K01 のように補足区分付きも取りうる
     * (H は T/K/S/B の後ろにも付くため)。
     */
    public function nextExtraSeq(string $customerCode, string $unitNo, string $baseSuffix, string $extra): string
    {
        // 過去分は通番の桁が揃っていない(TL61 と TL061)。full_no を前方一致で見ると
        // 桁違いを取りこぼすため、同じ通番の行をまとめてから suffix 側だけで比較する。
        $prefix = $baseSuffix.$extra;

        $max = $this->sameUnit($customerCode, $unitNo)
            ->filter(fn (QuoteNumber $q) => str_starts_with((string) $q->suffix, $prefix))
            ->map(fn (QuoteNumber $q) => (int) substr((string) $q->suffix, strlen($prefix), 2))
            ->max() ?? 0;

        return str_pad((string) ($max + 1), 2, '0', STR_PAD_LEFT);
    }

    /**
     * その注番がすでに取得済みか。通番の桁数の違い(1 / 01 / 001)は同じものとして扱う。
     */
    public function isTaken(string $fullNo): bool
    {
        $canonical = $this->canonicalize($fullNo);

        if ($canonical === null) {
            return QuoteNumber::where('full_no', strtoupper(trim($fullNo)))->exists();
        }

        [$customerCode, $unitNo, $suffix] = $canonical;

        return $this->sameUnit($customerCode, $unitNo)
            ->contains(fn (QuoteNumber $q) => (string) $q->suffix === $suffix);
    }

    /**
     * 注番を「客先番号 / 3桁ゼロ埋めの通番 / ハイフン以降」に分解する。
     * 分解できない書き方(過去台帳の枝番など)はnull。
     *
     * @return array{0: string, 1: string, 2: string}|null
     */
    public function canonicalize(string $fullNo): ?array
    {
        if (! preg_match('/^([A-Z]{1,5})(\d{1,4})-(.+)$/', strtoupper(trim($fullNo)), $m)) {
            return null;
        }

        return [$m[1], str_pad($m[2], 3, '0', STR_PAD_LEFT), $m[3]];
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

    /**
     * 見積単位を3桁ゼロ埋めにする。旧形式の過去注番は見積単位に区分が混ざるため
     * (「511B」「511T」)、先頭の数字だけを見積単位として扱う。
     */
    private function normalizeUnit(?string $unitNo): ?string
    {
        if (! preg_match('/^(\d{1,4})/', trim((string) $unitNo), $m)) {
            return null;
        }

        return str_pad($m[1], 3, '0', STR_PAD_LEFT);
    }

    /**
     * 元番号(ハイフン以降)を正規化する。数字だけなら通常番号の通番とみなして N を補う
     * (「01」→「N01」)。すでに区分付きならそのまま使う(「N01K01」など)。
     */
    private function normalizeBaseNo(?string $baseNo): ?string
    {
        $baseNo = strtoupper(trim((string) $baseNo));

        if ($baseNo === '') {
            return null;
        }

        if (ctype_digit($baseNo)) {
            $baseNo = QuoteNumber::TYPE_NORMAL.str_pad($baseNo, 2, '0', STR_PAD_LEFT);
        }

        return preg_match('/^[A-Z]\d{2,3}(?:[A-Z]\d{2,3})*$/', $baseNo) ? $baseNo : null;
    }

    /**
     * @param  array<int, string>  $missing
     * @return array<string, mixed>
     */
    private function result(?string $candidate, ?string $unitNo, ?string $quoteType, ?string $quoteSeq, ?string $extra, array $missing, bool $duplicate): array
    {
        return [
            'candidate' => $candidate,
            'fell_back_to_new' => false,
            'quoted_old_format' => false,
            'unit_no' => $unitNo,
            'base_suffix' => null,
            'suffix' => null,
            'quote_type' => $quoteType,
            'quote_seq' => $quoteSeq,
            'extra_code' => $extra,
            'extra_seq' => null,
            'missing' => $missing,
            'duplicate' => $duplicate,
        ];
    }
}
