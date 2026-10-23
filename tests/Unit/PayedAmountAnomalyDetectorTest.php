<?php

declare(strict_types=1);

namespace Noelo\Monitor\Tests\Unit;

use Noelo\Monitor\OrderFeed\PayedAmountAnomalyDetector;
use Noelo\Monitor\Tests\Unit\Support\OrderTestFactory;
use PHPUnit\Framework\TestCase;

final class PayedAmountAnomalyDetectorTest extends TestCase
{
    public function testFlagsOrderWherePayedDoesNotMatchTotalPrice(): void
    {
        // Realny priklad z produkcneho feedu: totalPrice=14.88, payed=6708.00
        $order = OrderTestFactory::order(3014234412, [OrderTestFactory::item(1, 'P1', unitPriceCents: 1488)], payedCents: 670800);

        $warnings = (new PayedAmountAnomalyDetector())->detect([$order]);

        self::assertCount(1, $warnings);
        self::assertStringContainsString('#3014234412', $warnings[0]);
        self::assertStringContainsString('14.88', $warnings[0]);
        self::assertStringContainsString('6708.00', $warnings[0]);
    }

    public function testMatchingPayedAndTotalPriceProducesNoWarning(): void
    {
        $order = OrderTestFactory::order(1, [OrderTestFactory::item(1, 'P1', unitPriceCents: 1000)], payedCents: 1000);

        self::assertSame([], (new PayedAmountAnomalyDetector())->detect([$order]));
    }

    public function testUnpaidOrderProducesNoWarning(): void
    {
        $order = OrderTestFactory::order(1, [OrderTestFactory::item(1, 'P1', unitPriceCents: 1000)], payedCents: null);

        self::assertSame([], (new PayedAmountAnomalyDetector())->detect([$order]));
    }

    public function testWarningNeverContainsCustomerNameOrEmail(): void
    {
        $order = OrderTestFactory::order(1, [OrderTestFactory::item(1, 'P1', unitPriceCents: 1000)], payedCents: 2000);

        $warnings = (new PayedAmountAnomalyDetector())->detect([$order]);

        self::assertStringNotContainsString('Test Zakaznik', $warnings[0]);
        self::assertStringNotContainsString('example.com', $warnings[0]);
    }
}
