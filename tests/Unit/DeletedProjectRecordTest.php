<?php

namespace Tests\Unit;

use App\Support\DeletedProjectRecord;
use PHPUnit\Framework\TestCase;

/**
 * 削除された物件の控え。書き出した文章を読み戻して表にするため、
 * 両方の形が食い違っていないことを固定する。
 */
class DeletedProjectRecordTest extends TestCase
{
    public function test_the_text_can_be_read_back_into_the_same_fields(): void
    {
        $values = [
            'order_no' => 'DH013-N01',
            'product_name' => '搬送コンベア',
            'recipient' => '取引先㈱',
            'delivery_dest' => '第二工場',
            'order_received_date' => '2026/08/01',
            'order_amount' => '¥3,000,000',
            'sales_date' => '—',
            'staff_name' => '斉藤 修',
            'stage' => '線表反映済',
        ];
        $history = [
            '2026/08/01 10:00 受注登録（斉藤 修）',
            '2026/08/02 09:00 ステージ移動：線表反映済へ移動（斉藤 修）',
        ];

        $parsed = DeletedProjectRecord::fromText(DeletedProjectRecord::toText($values, $history));

        $this->assertSame($values, $parsed['fields']);
        $this->assertSame($history, $parsed['history']);
    }

    public function test_a_record_without_history_still_yields_its_fields(): void
    {
        $text = DeletedProjectRecord::toText(['order_no' => 'ZZ001-N01', 'product_name' => '装置'], []);
        $parsed = DeletedProjectRecord::fromText($text);

        $this->assertSame('ZZ001-N01', $parsed['fields']['order_no']);
        $this->assertSame('装置', $parsed['fields']['product_name']);
        // 埋めなかった項目は「—」として書き出す。
        $this->assertSame('—', $parsed['fields']['recipient']);
        $this->assertSame([], $parsed['history']);
    }

    public function test_unknown_text_does_not_break_the_table(): void
    {
        $parsed = DeletedProjectRecord::fromText("むかしの形式のメモ\n注番などは入っていない");

        $this->assertSame('', $parsed['fields']['order_no']);
        $this->assertSame([], $parsed['history']);
    }
}
