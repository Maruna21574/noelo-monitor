<?php

declare(strict_types=1);

namespace Noelo\Monitor\Reporting;

use Noelo\Monitor\Rules\CancellationSuggestionRule;
use Noelo\Monitor\Rules\OrderAvailabilityStatus;
use Noelo\Monitor\Rules\OrderShippabilityEvaluator;
use Noelo\Monitor\Rules\PaymentReminderRule;
use Noelo\Monitor\Rules\ReservationConflictDetector;

final class ReportBuilder
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly OrderShippabilityEvaluator $shippabilityEvaluator,
        private readonly ReservationConflictDetector $reservationConflictDetector,
        private readonly PaymentMatchRepository $paymentMatchRepository,
        private readonly FeedErrorRepository $feedErrorRepository,
        private readonly PaymentReminderRule $paymentReminderRule,
        private readonly CancellationSuggestionRule $cancellationSuggestionRule,
        private readonly ?ReservationConflictDismissalRepository $conflictDismissals = null,
    ) {
    }

    public function build(\DateTimeImmutable $now, int $checkRunId): DailyReport
    {
        // Len objednavky z POSLEDNEHO importu feedu (skutocne "aktualne otvorene"),
        // nie vsetko, co appka kedy videla - inak by report donekonecna pripocitaval
        // aj davno vybavene/zrusene objednavky, ktore uz z feedu zmizli.
        $orders = $this->orderRepository->findCurrentlyOpenOrders();

        $shippableOrderIds = [];
        $partiallyShippable = [];
        $unavailable = [];
        $paymentReminders = [];
        $cancellationSuggestions = [];

        foreach ($orders as $order) {
            $result = $this->shippabilityEvaluator->evaluate($order);
            if ($result !== null) {
                match ($result->status) {
                    OrderAvailabilityStatus::SHIPPABLE => $shippableOrderIds[] = $order->id,
                    OrderAvailabilityStatus::PARTIALLY_SHIPPABLE => $partiallyShippable[$order->id] = $result,
                    OrderAvailabilityStatus::UNAVAILABLE => $unavailable[$order->id] = $result,
                };
            }

            if ($this->paymentReminderRule->matches($order, $now)) {
                $paymentReminders[] = $order;
            }
            if ($this->cancellationSuggestionRule->matches($order, $now)) {
                $cancellationSuggestions[] = $order;
            }
        }

        $reservationConflicts = $this->reservationConflictDetector->detect($orders, $now);
        if ($this->conflictDismissals !== null) {
            $reservationConflicts = $this->conflictDismissals->withoutDismissed($reservationConflicts);
        }
        $orderCreatedAt = [];
        foreach ($orders as $order) {
            $orderCreatedAt[$order->id] = $order->createdAt;
        }
        // Od najstarsej objednavky (najdlhsie cakajucej) po najnovsiu.
        usort($reservationConflicts, static fn ($a, $b) => ($orderCreatedAt[$a->blockedOrderId] ?? $now) <=> ($orderCreatedAt[$b->blockedOrderId] ?? $now));

        return new DailyReport(
            runDate: $now,
            shippableOrderIds: $shippableOrderIds,
            partiallyShippable: $partiallyShippable,
            unavailable: $unavailable,
            reservationConflicts: $reservationConflicts,
            unmatchedPayments: $this->paymentMatchRepository->findPendingReview(),
            paymentReminders: $paymentReminders,
            cancellationSuggestions: $cancellationSuggestions,
            technicalErrors: $this->feedErrorRepository->findByCheckRun($checkRunId),
            orderCreatedAt: $orderCreatedAt,
        );
    }
}
