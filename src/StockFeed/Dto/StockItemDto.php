<?php

declare(strict_types=1);

namespace Noelo\Monitor\StockFeed\Dto;

final class StockItemDto
{
    public function __construct(
        public readonly string $productCode,
        public readonly int $quantityInStock,
        public readonly int $quantityReserved,
        public readonly bool $soldOut,
        public readonly ?\DateTimeImmutable $expectedDeliveryDate = null,
    ) {
    }

    public function availableToPromise(): int
    {
        return max(0, $this->quantityInStock - $this->quantityReserved);
    }
}
