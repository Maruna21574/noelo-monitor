<?php

declare(strict_types=1);

namespace Noelo\Monitor\OrderFeed\Dto;

final class OrderedItemDto
{
    public function __construct(
        public readonly int $id,
        public readonly ?string $productCode,
        public readonly string $title,
        public readonly int $count,
        public readonly int $unitPriceCents,
        public readonly int $lineTotalCents,
        public readonly ?string $deliveryInfo,
        public readonly ?string $supplier,
        public readonly bool $isDiscount,
        /**
         * Kolko kusov ma zakaznik realne pripravenych/rezervovanych skladom
         * (readyItems z feedu). 0 = tovar este nemame, cakame nan.
         * null = feed hodnotu neobsahoval (stav nepoznáme).
         */
        public readonly ?int $readyItems = null,
    ) {
    }
}
