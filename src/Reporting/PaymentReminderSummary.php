<?php

declare(strict_types=1);

namespace Noelo\Monitor\Reporting;

final class PaymentReminderSummary
{
    /**
     * @param int[] $sentOrderIds
     * @param int[] $wouldSendOrderIds len v dry-run rezime (nic sa neposlalo)
     * @param int[] $failedOrderIds
     */
    public function __construct(
        public readonly bool $dryRun,
        public readonly array $sentOrderIds = [],
        public readonly array $wouldSendOrderIds = [],
        public readonly array $failedOrderIds = [],
        public readonly int $skippedOverLimit = 0,
    ) {
    }
}
