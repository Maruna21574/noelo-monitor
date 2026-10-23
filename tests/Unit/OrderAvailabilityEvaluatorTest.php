<?php

declare(strict_types=1);

namespace Noelo\Monitor\Tests\Unit;

use Noelo\Monitor\Rules\OrderAvailabilityEvaluator;
use Noelo\Monitor\Rules\OrderAvailabilityStatus;
use Noelo\Monitor\Tests\Unit\Support\OrderTestFactory;
use PHPUnit\Framework\TestCase;

final class OrderAvailabilityEvaluatorTest extends TestCase
{
    public function testOrderWithAllItemsFullyReadyIsShippable(): void
    {
        $order = OrderTestFactory::order(1, [
            OrderTestFactory::item(1, 'P1', count: 2, readyItems: 2),
            OrderTestFactory::item(2, 'P2', count: 1, readyItems: 1),
        ]);

        $result = (new OrderAvailabilityEvaluator())->evaluate($order);

        self::assertSame(OrderAvailabilityStatus::SHIPPABLE, $result->status);
        self::assertCount(2, $result->availableItems);
        self::assertCount(0, $result->unavailableItems);
    }

    public function testZeroReadyItemsMeansWaitingForGoodsAndOrderIsUnavailable(): void
    {
        $order = OrderTestFactory::order(1, [OrderTestFactory::item(1, 'P1', readyItems: 0)]);

        $result = (new OrderAvailabilityEvaluator())->evaluate($order);

        self::assertSame(OrderAvailabilityStatus::UNAVAILABLE, $result->status);
        self::assertSame(OrderAvailabilityEvaluator::REASON_WAITING, $result->unavailableItems[0]->reason);
        self::assertSame(0, $result->unavailableItems[0]->readyCount);
    }

    public function testMixOfReadyAndWaitingItemsIsPartiallyShippable(): void
    {
        $order = OrderTestFactory::order(1, [
            OrderTestFactory::item(1, 'P1', readyItems: 1),
            OrderTestFactory::item(2, 'P2', readyItems: 0),
        ]);

        $result = (new OrderAvailabilityEvaluator())->evaluate($order);

        self::assertSame(OrderAvailabilityStatus::PARTIALLY_SHIPPABLE, $result->status);
        self::assertCount(1, $result->availableItems);
        self::assertCount(1, $result->unavailableItems);
    }

    public function testSingleItemWithSomePiecesReadyIsPartiallyShippable(): void
    {
        $order = OrderTestFactory::order(1, [OrderTestFactory::item(1, 'P1', count: 3, readyItems: 1)]);

        $result = (new OrderAvailabilityEvaluator())->evaluate($order);

        self::assertSame(OrderAvailabilityStatus::PARTIALLY_SHIPPABLE, $result->status);
        self::assertSame('pripravené 1 z 3 ks', $result->unavailableItems[0]->reason);
    }

    public function testMissingReadyItemsIsFlaggedAsUnknownNotSilentlyAvailable(): void
    {
        $order = OrderTestFactory::order(1, [OrderTestFactory::item(1, 'P1', readyItems: null)]);

        $result = (new OrderAvailabilityEvaluator())->evaluate($order);

        self::assertSame(OrderAvailabilityStatus::UNAVAILABLE, $result->status);
        self::assertSame(OrderAvailabilityEvaluator::REASON_UNKNOWN, $result->unavailableItems[0]->reason);
    }

    public function testDeliveryInfoTextFromFeedIsCarriedThroughForDisplay(): void
    {
        $order = OrderTestFactory::order(1, [
            OrderTestFactory::item(1, 'P1', count: 2, readyItems: 0, deliveryInfo: 'do 7 dní'),
        ]);

        $result = (new OrderAvailabilityEvaluator())->evaluate($order);

        self::assertSame('do 7 dní', $result->unavailableItems[0]->deliveryInfo);
    }

    public function testDiscountLineItemsAreIgnoredInAvailabilityCheck(): void
    {
        $order = OrderTestFactory::order(1, [
            OrderTestFactory::item(1, 'P1', readyItems: 1),
            OrderTestFactory::item(2, '6826', unitPriceCents: -500, isDiscount: true),
        ]);

        $result = (new OrderAvailabilityEvaluator())->evaluate($order);

        self::assertSame(OrderAvailabilityStatus::SHIPPABLE, $result->status);
        self::assertCount(1, $result->availableItems);
    }

    public function testZeroReadyItemsWithSoldOutDeliveryInfoIsReportedAsSoldOut(): void
    {
        $order = OrderTestFactory::order(1, [OrderTestFactory::item(1, 'P1', readyItems: 0, deliveryInfo: 'vypredané')]);

        $result = (new OrderAvailabilityEvaluator())->evaluate($order);

        self::assertSame(OrderAvailabilityStatus::UNAVAILABLE, $result->status);
        self::assertSame(OrderAvailabilityEvaluator::REASON_SOLD_OUT, $result->unavailableItems[0]->reason);
    }
}
