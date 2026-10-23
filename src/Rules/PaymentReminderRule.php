<?php

declare(strict_types=1);

namespace Noelo\Monitor\Rules;

use Noelo\Monitor\OrderFeed\Dto\OrderDto;

/**
 * Pravidlo 6: "Pripomenutie uhrady" - objednavka s platbou vopred nezaplatena
 * aspon N dni. Iba informuje administratora, zakaznikovi sa nic neposiela.
 */
final class PaymentReminderRule
{
    public function __construct(
        private readonly int $reminderThresholdDays = 5,
        private readonly int $cancellationThresholdDays = 7,
    ) {
    }

    public function matches(OrderDto $order, \DateTimeImmutable $now): bool
    {
        if ($order->isEligibleForShipping()) {
            return false;
        }

        $ageDays = $order->createdAt->diff($now)->days;

        // Nad hranicou zrusenia uz objednavku hlasime len v CancellationSuggestionRule.
        return $ageDays >= $this->reminderThresholdDays && $ageDays <= $this->cancellationThresholdDays;
    }
}
