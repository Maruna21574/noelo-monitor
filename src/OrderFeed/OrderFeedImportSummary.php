<?php

declare(strict_types=1);

namespace Noelo\Monitor\OrderFeed;

final class OrderFeedImportSummary
{
    public function __construct(
        public readonly int $ordersProcessed,
        public readonly int $itemsProcessed,
        /** @var string[] */
        public readonly array $skippedOrderErrors,
    ) {
    }
}
