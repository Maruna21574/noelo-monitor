<?php

declare(strict_types=1);

namespace Noelo\Monitor\Rules;

final class ItemAvailability
{
    public function __construct(
        public readonly int $orderItemId,
        public readonly ?string $productCode,
        public readonly string $title,
        public readonly int $requestedCount,
        public readonly bool $available,
        public readonly ?string $reason,
        /** Kusy realne pripravene/rezervovane pre tohto zakaznika (readyItems), null = nezname. */
        public readonly ?int $readyCount = null,
        /** Povodny textovy udaj z feedu (napr. "skladom", "do 7 dni", konkretny datum) - pre zobrazenie v reporte. */
        public readonly ?string $deliveryInfo = null,
    ) {
    }
}
