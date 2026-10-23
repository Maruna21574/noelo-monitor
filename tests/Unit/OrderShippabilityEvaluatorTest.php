<?php

declare(strict_types=1);

namespace Noelo\Monitor\Tests\Unit;

use Noelo\Monitor\Rules\OrderAvailabilityEvaluator;
use Noelo\Monitor\Rules\OrderAvailabilityStatus;
use Noelo\Monitor\Rules\OrderShippabilityEvaluator;
use Noelo\Monitor\Tests\Unit\Support\OrderTestFactory;
use PHPUnit\Framework\TestCase;

final class OrderShippabilityEvaluatorTest extends TestCase
{
    private function buildEvaluator(): OrderShippabilityEvaluator
    {
        return new OrderShippabilityEvaluator(new OrderAvailabilityEvaluator());
    }

    public function testUnpaidNonCodOrderReturnsNullVerdict(): void
    {
        $order = OrderTestFactory::order(1, [OrderTestFactory::item(1, 'P1', readyItems: 1)], payedCents: null, isCashOnDelivery: false);

        self::assertNull($this->buildEvaluator()->evaluate($order));
    }

    public function testUnpaidCashOnDeliveryOrderIsStillEvaluatedForAvailability(): void
    {
        $order = OrderTestFactory::order(1, [OrderTestFactory::item(1, 'P1', readyItems: 1)], payedCents: null, isCashOnDelivery: true);

        $result = $this->buildEvaluator()->evaluate($order);

        self::assertNotNull($result);
        self::assertSame(OrderAvailabilityStatus::SHIPPABLE, $result->status);
    }

    public function testPaidOrderIsEvaluatedForAvailability(): void
    {
        $order = OrderTestFactory::order(1, [OrderTestFactory::item(1, 'P1', readyItems: 0)], payedCents: 1000, isCashOnDelivery: false);

        $result = $this->buildEvaluator()->evaluate($order);

        self::assertNotNull($result);
        self::assertSame(OrderAvailabilityStatus::UNAVAILABLE, $result->status);
    }
}
