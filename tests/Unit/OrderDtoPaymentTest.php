<?php

declare(strict_types=1);

namespace Noelo\Monitor\Tests\Unit;

use Noelo\Monitor\Tests\Unit\Support\OrderTestFactory;
use PHPUnit\Framework\TestCase;

final class OrderDtoPaymentTest extends TestCase
{
    public function testOrderWithPositivePayedAmountIsPaid(): void
    {
        $order = OrderTestFactory::order(1, [OrderTestFactory::item(1, 'P1')], payedCents: 1000);

        self::assertTrue($order->isPaid());
        self::assertTrue($order->isEligibleForShipping());
    }

    public function testOrderWithNullPayedIsNotPaid(): void
    {
        $order = OrderTestFactory::order(1, [OrderTestFactory::item(1, 'P1')], payedCents: null);

        self::assertFalse($order->isPaid());
    }

    public function testOrderWithZeroPayedIsTreatedAsNotPaid(): void
    {
        // Nenastalo to zatial v realnych datach, ale "payed=0.00" by nemalo
        // znamenat "zaplatene" len preto, ze pole nie je prazdne.
        $order = OrderTestFactory::order(1, [OrderTestFactory::item(1, 'P1')], payedCents: 0);

        self::assertFalse($order->isPaid());
        self::assertFalse($order->isEligibleForShipping());
    }

    public function testCashOnDeliveryIsEligibleForShippingEvenWithoutPayment(): void
    {
        $order = OrderTestFactory::order(1, [OrderTestFactory::item(1, 'P1')], payedCents: null, isCashOnDelivery: true);

        self::assertFalse($order->isPaid());
        self::assertTrue($order->isEligibleForShipping());
    }
}
