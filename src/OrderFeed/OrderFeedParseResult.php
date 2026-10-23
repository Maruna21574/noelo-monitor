<?php

declare(strict_types=1);

namespace Noelo\Monitor\OrderFeed;

use Noelo\Monitor\OrderFeed\Dto\OrderDto;

final class OrderFeedParseResult
{
    /**
     * @param OrderDto[] $orders
     * @param string[] $skippedOrderErrors sanitizovane spravy (bez PII) o preskocenych objednavkach/polozkach
     */
    public function __construct(
        public readonly array $orders,
        public readonly array $skippedOrderErrors,
    ) {
    }
}
