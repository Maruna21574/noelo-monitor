<?php

declare(strict_types=1);

namespace Noelo\Monitor\Rules;

use Noelo\Monitor\OrderFeed\Dto\OrderDto;

/**
 * Pravidlo 7: "Navrh na storno" - objednavka s platbou vopred nezaplatena
 * viac nez N dni. Aplikacia NIKDY objednavku automaticky nestornuje - iba
 * navrhuje administratorovi na rucne posudenie.
 */
final class CancellationSuggestionRule
{
    public function __construct(private readonly int $cancellationThresholdDays = 7)
    {
    }

    public function matches(OrderDto $order, \DateTimeImmutable $now): bool
    {
        if ($order->isEligibleForShipping()) {
            return false;
        }

        $ageDays = $order->createdAt->diff($now)->days;

        return $ageDays > $this->cancellationThresholdDays;
    }
}
