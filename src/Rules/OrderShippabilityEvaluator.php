<?php

declare(strict_types=1);

namespace Noelo\Monitor\Rules;

use Noelo\Monitor\OrderFeed\Dto\OrderDto;

/**
 * Kombinuje stav platby (zaplatene alebo dobierka) s dostupnostou tovaru,
 * aby urcila jednu z troch kategorii zo zadania:
 *   1. "Objednavku mozno odoslat"
 *   2. "Objednavku mozno ciastocne odoslat"
 *   3. "Tovar nie je dostupny"
 *
 * Ak objednavka este nie je platobne opravnena na odoslanie (nezaplatena a
 * nie je dobierka), vrati null - taketo objednavky riesia ine pravidla
 * (pripomenutie uhrady / navrh na storno), nie tento evaluator.
 */
final class OrderShippabilityEvaluator
{
    public function __construct(private readonly OrderAvailabilityEvaluator $availabilityEvaluator)
    {
    }

    public function evaluate(OrderDto $order): ?OrderAvailabilityResult
    {
        if (!$order->isEligibleForShipping()) {
            return null;
        }

        return $this->availabilityEvaluator->evaluate($order);
    }
}
