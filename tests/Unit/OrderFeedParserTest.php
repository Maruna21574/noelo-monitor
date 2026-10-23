<?php

declare(strict_types=1);

namespace Noelo\Monitor\Tests\Unit;

use Noelo\Monitor\OrderFeed\OrderFeedParseException;
use Noelo\Monitor\OrderFeed\OrderFeedParser;
use PHPUnit\Framework\TestCase;

final class OrderFeedParserTest extends TestCase
{
    private function fixture(string $name): string
    {
        return file_get_contents(__DIR__ . '/../Fixtures/' . $name);
    }

    public function testParsesValidFeedWithMultipleOrders(): void
    {
        $result = (new OrderFeedParser())->parse($this->fixture('orders_valid.xml'));

        self::assertCount(2, $result->orders);
        self::assertSame([], $result->skippedOrderErrors);
    }

    public function testMultiplesUnitPriceByCountForLineTotal(): void
    {
        $result = (new OrderFeedParser())->parse($this->fixture('orders_valid.xml'));

        $order = $result->orders[0];
        $secondItem = $order->items[1];

        self::assertSame(2, $secondItem->count);
        self::assertSame(1459, $secondItem->unitPriceCents);
        self::assertSame(2918, $secondItem->lineTotalCents);
    }

    public function testParsesReadyItemsAndLeavesMissingValueAsUnknown(): void
    {
        $result = (new OrderFeedParser())->parse($this->fixture('orders_valid.xml'));

        self::assertSame(1, $result->orders[0]->items[0]->readyItems);
        self::assertSame(0, $result->orders[0]->items[1]->readyItems);
        self::assertNull($result->orders[1]->items[0]->readyItems);
    }

    public function testNegativeDiscountLineIsParsedAndFlagged(): void
    {
        $result = (new OrderFeedParser())->parse($this->fixture('orders_valid.xml'));

        $discountItem = $result->orders[0]->items[2];

        self::assertSame(-492, $discountItem->unitPriceCents);
        self::assertTrue($discountItem->isDiscount);
        self::assertNull($discountItem->supplier);
        self::assertNull($discountItem->deliveryInfo);
    }

    public function testCashOnDeliveryOrderWithEmptyPayedIsNotTreatedAsUnpaidFailure(): void
    {
        $result = (new OrderFeedParser())->parse($this->fixture('orders_valid.xml'));

        $codOrder = $result->orders[1];

        self::assertTrue($codOrder->isCashOnDelivery);
        self::assertNull($codOrder->payedCents);
    }

    public function testMalformedXmlThrowsExceptionWithoutLeakingPersonalData(): void
    {
        $this->expectException(OrderFeedParseException::class);

        try {
            (new OrderFeedParser())->parse($this->fixture('orders_malformed.xml'));
        } catch (OrderFeedParseException $e) {
            self::assertStringNotContainsString('example.com', $e->getMessage());
            self::assertStringNotContainsString('Nedokoncena', $e->getMessage());
            throw $e;
        }
    }

    public function testOrderWithMissingRequiredFieldIsSkippedWithoutFailingWholeImport(): void
    {
        $result = (new OrderFeedParser())->parse($this->fixture('orders_missing_field.xml'));

        self::assertCount(1, $result->orders);
        self::assertSame(3014235000, $result->orders[0]->id);
        self::assertCount(1, $result->skippedOrderErrors);
        self::assertStringNotContainsString('bad@example.com', $result->skippedOrderErrors[0]);
        self::assertStringNotContainsString('Neplatna Objednavka', $result->skippedOrderErrors[0]);
    }

    public function testRejectsDoctypeDeclarationAsXxeDefense(): void
    {
        $malicious = '<?xml version="1.0"?><!DOCTYPE orders [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><orders></orders>';

        $this->expectException(OrderFeedParseException::class);
        (new OrderFeedParser())->parse($malicious);
    }

    public function testEmptyDeliveryTypeAndCashPickupAreTreatedAsCashOnDelivery(): void
    {
        $xml = '<orders>'
            . $this->minimalOrder(1, '<deliveryType/>')
            . $this->minimalOrder(2, '<deliveryType>Osobný odber - hotovosť</deliveryType>')
            . $this->minimalOrder(3, '<deliveryType>Kuriér - vopred</deliveryType>')
            . '</orders>';

        $orders = (new OrderFeedParser())->parse($xml)->orders;

        self::assertTrue($orders[0]->isCashOnDelivery);
        self::assertTrue($orders[1]->isCashOnDelivery);
        self::assertFalse($orders[2]->isCashOnDelivery);
    }

    private function minimalOrder(int $id, string $deliveryTypeXml): string
    {
        return "<order><id>{$id}</id><created>2026-10-01 10:00:00</created><status>x</status>"
            . '<deliveryName>A</deliveryName><email>a@b.sk</email><totalPrice>10.00</totalPrice>'
            . $deliveryTypeXml . '<deliveryPrice>1.00</deliveryPrice><payed/><orderedItems/></order>';
    }
}
