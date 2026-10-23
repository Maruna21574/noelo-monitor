<?php

declare(strict_types=1);

namespace Noelo\Monitor\Tests\Unit\Support;

use Noelo\Monitor\OrderFeed\Dto\OrderDto;
use Noelo\Monitor\OrderFeed\Dto\OrderedItemDto;

final class OrderTestFactory
{
    public static function item(
        int $id,
        ?string $productCode,
        int $count = 1,
        int $unitPriceCents = 1000,
        bool $isDiscount = false,
        ?int $readyItems = null,
        ?string $deliveryInfo = null,
    ): OrderedItemDto {
        return new OrderedItemDto(
            id: $id,
            productCode: $productCode,
            title: "Produkt {$id}",
            count: $count,
            unitPriceCents: $unitPriceCents,
            lineTotalCents: $unitPriceCents * $count,
            deliveryInfo: $deliveryInfo,
            supplier: null,
            isDiscount: $isDiscount,
            readyItems: $readyItems,
        );
    }

    /** @param OrderedItemDto[] $items */
    public static function order(
        int $id,
        array $items,
        ?int $payedCents = 1000,
        bool $isCashOnDelivery = false,
        string $deliveryType = 'DPD point - vopred',
        string $createdAt = '2026-09-17 12:00:00',
    ): OrderDto {
        return new OrderDto(
            id: $id,
            createdAt: new \DateTimeImmutable($createdAt),
            status: 'spracováva sa',
            deliveryName: 'Test Zakaznik',
            email: 'test@example.com',
            totalPriceCents: array_sum(array_map(static fn ($i) => $i->lineTotalCents, $items)),
            deliveryType: $deliveryType,
            deliveryPriceCents: 0,
            payedCents: $payedCents,
            isCashOnDelivery: $isCashOnDelivery,
            rawHash: hash('sha256', (string) $id),
            items: $items,
        );
    }
}
