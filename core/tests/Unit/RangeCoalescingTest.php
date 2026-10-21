<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Channels\Dto\AvailabilityRange;
use App\Services\Nights;
use App\Services\RateService;
use PHPUnit\Framework\TestCase;

class RangeCoalescingTest extends TestCase
{
    public function test_nights_between_excludes_checkout_date(): void
    {
        $dates = Nights::between('2026-10-14', '2026-10-17');

        $this->assertCount(3, $dates);
        $this->assertEquals(['2026-10-14', '2026-10-15', '2026-10-16'], $dates);
        $this->assertNotContains('2026-10-17', $dates);
    }

    public function test_nights_between_handles_month_and_year_boundaries(): void
    {
        $dates = Nights::between('2026-12-30', '2027-01-02');

        $this->assertCount(3, $dates);
        $this->assertEquals(['2026-12-30', '2026-12-31', '2027-01-01'], $dates);
    }

    public function test_nights_between_empty_when_checkout_before_or_same_as_checkin(): void
    {
        $this->assertEmpty(Nights::between('2026-10-14', '2026-10-14'));
        $this->assertEmpty(Nights::between('2026-10-15', '2026-10-14'));
    }

    public function test_rate_markup_calculation(): void
    {
        // 3500 base price with 1000 bps (10%) markup -> 3850
        $markedUp = RateService::applyMarkup(3500, 1000);
        $this->assertSame(3850, $markedUp);

        // 5200 base price with 1200 bps (12%) markup -> 5824
        $markedUp2 = RateService::applyMarkup(5200, 1200);
        $this->assertSame(5824, $markedUp2);

        // 9800 base price with 800 bps (8%) markup -> 10584
        $markedUp3 = RateService::applyMarkup(9800, 800);
        $this->assertSame(10584, $markedUp3);

        // 100 base with 10 bps (0.10%) -> 100 * 10010 + 5000 = 1006000 / 10000 = 100
        $markedUp4 = RateService::applyMarkup(100, 10);
        $this->assertSame(100, $markedUp4);
    }
}
