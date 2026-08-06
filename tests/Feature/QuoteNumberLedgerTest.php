<?php

namespace Tests\Feature;

use App\Models\QuoteNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 見積番号(注文番号)台帳。過去の台帳は規約どおりでない行が多いため、
 * パースできない行も原文のまま保持し、採番候補の計算からだけ外す。
 */
class QuoteNumberLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_parses_the_documented_format(): void
    {
        $this->assertSame(
            ['quote_type' => 'N', 'quote_seq' => '01', 'extra_code' => null],
            QuoteNumber::parseSuffix('N01')
        );

        $this->assertSame(
            ['quote_type' => 'N', 'quote_seq' => '01', 'extra_code' => 'K'],
            QuoteNumber::parseSuffix('N01K01')
        );

        // 補足区分は複数連結できる(規約の (Z)(9)(9) が3組)。先頭を代表として保持する。
        $this->assertSame('D', QuoteNumber::parseSuffix('N02D07')['extra_code']);

        // 見積通番は3桁も許容する。
        $this->assertSame('001', QuoteNumber::parseSuffix('N001')['quote_seq']);

        $this->assertSame('F', QuoteNumber::parseSuffix('F01')['quote_type']);
    }

    /**
     * 過去データに実在する規約外の表記。パースはできないが取り込みでは捨てない。
     */
    public function test_it_returns_null_for_formats_outside_the_rule(): void
    {
        foreach (['N03-A', 'N-01', '1', '12', '1T', ''] as $suffix) {
            $this->assertNull(QuoteNumber::parseSuffix($suffix), "「{$suffix}」はパースできないはず");
        }

        $this->assertNull(QuoteNumber::parseSuffix(null));
    }

    public function test_the_unit_number_is_padded_for_display_only(): void
    {
        // 過去分は2桁のこともあるが、原文は保持したまま表示だけ3桁に揃える。
        $quote = new QuoteNumber(['unit_no' => '15']);
        $this->assertSame('015', $quote->paddedUnitNo());
        $this->assertSame('15', $quote->unit_no);
    }
}
