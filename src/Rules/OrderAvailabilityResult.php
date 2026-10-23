<?php

declare(strict_types=1);

namespace Noelo\Monitor\Rules;

final class OrderAvailabilityResult
{
    /**
     * @param ItemAvailability[] $availableItems
     * @param ItemAvailability[] $unavailableItems
     */
    public function __construct(
        public readonly OrderAvailabilityStatus $status,
        public readonly array $availableItems,
        public readonly array $unavailableItems,
    ) {
    }
}
