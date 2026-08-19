<?php

namespace Tests\Unit;

use App\Support\LeaveDays;
use PHPUnit\Framework\TestCase;

class LeaveDaysTest extends TestCase
{
    public function test_formats_days_in_quarter_units_without_trailing_zeros(): void
    {
        $this->assertSame('1', LeaveDays::format(1.0));
        $this->assertSame('0.5', LeaveDays::format(0.5));
        $this->assertSame('0.25', LeaveDays::format(0.25));
        $this->assertSame('14.25', LeaveDays::format('14.25'));
        $this->assertSame('0', LeaveDays::format(0));
        $this->assertSame('', LeaveDays::format(null));
    }

    public function test_recognises_quarter_day_values(): void
    {
        foreach ([0, 0.25, 0.5, 0.75, 1, 14.25, '18', '3.75'] as $valid) {
            $this->assertTrue(LeaveDays::isValidUnit($valid), "{$valid} は0.25単位のはず");
        }

        foreach ([0.1, 0.3, 14.3, '0.8'] as $invalid) {
            $this->assertFalse(LeaveDays::isValidUnit($invalid), "{$invalid} は0.25単位ではないはず");
        }
    }
}
