<?php

declare(strict_types=1);

namespace Noelo\Monitor\Matching;

final class PaymentMatchCandidate
{
    public function __construct(
        public readonly int $fioTransactionId,
        public readonly int $orderId,
        public readonly PaymentMatchType $matchType,
    ) {
    }
}
