<?php

declare(strict_types=1);

namespace Noelo\Monitor\Tests\Unit;

use Noelo\Monitor\StockFeed\FixtureStockFeedProvider;
use PHPUnit\Framework\TestCase;

final class FixtureStockFeedProviderTest extends TestCase
{
    public function testLoadsItemsFromJsonFileKeyedByProductCode(): void
    {
        $provider = FixtureStockFeedProvider::fromJsonFile(
            dirname(__DIR__, 2) . '/resources/stock_fixture.json'
        );

        $stock = $provider->getStock();

        self::assertArrayHasKey('40561', $stock);
        self::assertSame(12, $stock['40561']->quantityInStock);
        self::assertSame(2, $stock['40561']->quantityReserved);
        self::assertFalse($stock['40561']->soldOut);
    }

    public function testParsesSoldOutAndFutureDeliveryDateFlags(): void
    {
        $provider = FixtureStockFeedProvider::fromJsonFile(
            dirname(__DIR__, 2) . '/resources/stock_fixture.json'
        );

        $stock = $provider->getStock();

        self::assertTrue($stock['32732']->soldOut);
        self::assertInstanceOf(\DateTimeImmutable::class, $stock['38605']->expectedDeliveryDate);
        self::assertSame('2026-11-01', $stock['38605']->expectedDeliveryDate->format('Y-m-d'));
    }

    public function testThrowsWhenFixtureFileIsMissing(): void
    {
        $this->expectException(\RuntimeException::class);
        FixtureStockFeedProvider::fromJsonFile('/nonexistent/path.json');
    }
}
