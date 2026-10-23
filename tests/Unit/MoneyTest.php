<?php

declare(strict_types=1);

namespace Noelo\Monitor\Tests\Unit;

use Noelo\Monitor\Support\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testConvertsPlainDecimalToCents(): void
    {
        self::assertSame(4425, Money::toCents('44.25'));
    }

    public function testConvertsNegativeDecimalToCents(): void
    {
        self::assertSame(-492, Money::toCents('-4.92'));
    }

    public function testHandlesSingleDecimalDigitWithoutTrailingZeroLoss(): void
    {
        // "33.5" znamena 33,50 EUR, nie 33,05 EUR
        self::assertSame(3350, Money::toCents('33.5'));
    }

    public function testHandlesWholeNumberWithoutDecimalPoint(): void
    {
        self::assertSame(9540, Money::toCents('95.4'));
        self::assertSame(100, Money::toCents('1'));
    }

    public function testToDecimalStringRoundTrips(): void
    {
        self::assertSame('44.25', Money::toDecimalString(4425));
        self::assertSame('-4.92', Money::toDecimalString(-492));
    }

    public function testRejectsInvalidInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::toCents('not-a-number');
    }
}
