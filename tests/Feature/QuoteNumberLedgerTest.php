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

    /**
     * 台帳CSVはUTF-8。Shift_JISと決め打ちして変換すると件名・納入先が全て文字化けするため、
     * すでにUTF-8として妥当ならそのまま使うこと。
     */
    public function test_it_imports_utf8_csv_without_mangling_japanese(): void
    {
        $dir = storage_path('framework/testing/quote-ledger');
        \Illuminate\Support\Facades\File::ensureDirectoryExists($dir);
        \Illuminate\Support\Facades\File::put("{$dir}/台帳(D).csv", implode("\n", [
            ',,,,,,,,,,',
            'D,,大幸,,,,,,,,',
            ',,,,,,,,,,',
            ',D,013,N01,,保温筒ストッカー,パラマウント硝子,,7,S59.5,',
        ]));

        try {
            $this->artisan('app:import-quote-number-ledger', ['--dir' => $dir])->assertSuccessful();

            $quote = QuoteNumber::where('full_no', 'D013-N01')->sole();
            $this->assertSame('保温筒ストッカー', $quote->project_name);
            $this->assertSame('パラマウント硝子', $quote->delivery_dest);
            $this->assertSame('大幸', \App\Models\CustomerCode::where('code', 'D')->sole()->company_name);
        } finally {
            \Illuminate\Support\Facades\File::deleteDirectory($dir);
        }
    }

    public function test_the_unit_number_is_padded_for_display_only(): void
    {
        // 過去分は2桁のこともあるが、原文は保持したまま表示だけ3桁に揃える。
        $quote = new QuoteNumber(['unit_no' => '15']);
        $this->assertSame('015', $quote->paddedUnitNo());
        $this->assertSame('15', $quote->unit_no);
    }
}
