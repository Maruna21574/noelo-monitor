<?php

declare(strict_types=1);

namespace Noelo\Monitor\Reporting;

use Noelo\Monitor\OrderFeed\Dto\OrderDto;
use Noelo\Monitor\Rules\OrderAvailabilityResult;
use Noelo\Monitor\Rules\ReservationConflict;

final class DailyReport
{
    /**
     * @param int[] $shippableOrderIds
     * @param array<int, OrderAvailabilityResult> $partiallyShippable order id => vysledok
     * @param array<int, OrderAvailabilityResult> $unavailable order id => vysledok
     * @param ReservationConflict[] $reservationConflicts
     * @param array<int, array{order_id: string, order_created_at: string, match_type: string, amount_cents: string, transaction_date: string}> $unmatchedPayments
     * @param OrderDto[] $paymentReminders
     * @param OrderDto[] $cancellationSuggestions
     * @param array<int, \DateTimeImmutable> $orderCreatedAt order id => datum vytvorenia objednavky
     * @param array<int, array{feed_type: string, message: string, occurred_at: string}> $technicalErrors
     */
    public function __construct(
        public readonly \DateTimeImmutable $runDate,
        public readonly array $shippableOrderIds,
        public readonly array $partiallyShippable,
        public readonly array $unavailable,
        public readonly array $reservationConflicts,
        public readonly array $unmatchedPayments,
        public readonly array $paymentReminders,
        public readonly array $cancellationSuggestions,
        public readonly array $technicalErrors,
        public readonly array $orderCreatedAt = [],
    ) {
    }

    /** Datum vytvorenia objednavky ako "Y-m-d " (s koncovou medzerou, na predradenie pred "#id"), alebo prazdny retazec. */
    public function datePrefix(int $orderId): string
    {
        return isset($this->orderCreatedAt[$orderId]) ? $this->orderCreatedAt[$orderId]->format('Y-m-d') . ' ' : '';
    }

    public function isEmpty(): bool
    {
        return $this->shippableOrderIds === []
            && $this->partiallyShippable === []
            && $this->unavailable === []
            && $this->reservationConflicts === []
            && $this->unmatchedPayments === []
            && $this->paymentReminders === []
            && $this->cancellationSuggestions === []
            && $this->technicalErrors === [];
    }
}
