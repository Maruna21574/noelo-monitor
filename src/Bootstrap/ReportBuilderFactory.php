<?php

declare(strict_types=1);

namespace Noelo\Monitor\Bootstrap;

use Noelo\Monitor\Reporting\FeedErrorRepository;
use Noelo\Monitor\Reporting\OrderRepository;
use Noelo\Monitor\Reporting\PaymentMatchRepository;
use Noelo\Monitor\Reporting\ReportBuilder;
use Noelo\Monitor\Reporting\ReservationConflictDismissalRepository;
use Noelo\Monitor\Rules\CancellationSuggestionRule;
use Noelo\Monitor\Rules\OrderAvailabilityEvaluator;
use Noelo\Monitor\Rules\OrderShippabilityEvaluator;
use Noelo\Monitor\Rules\PaymentReminderRule;
use Noelo\Monitor\Rules\ReservationConflictDetector;
use PDO;

/**
 * Zdielane zapojenie ReportBuilder - pouziva ho cron beh aj admin dashboard,
 * aby oba pocitali "aktualne upozornenia" rovnako.
 */
final class ReportBuilderFactory
{
    public static function create(PDO $pdo): ReportBuilder
    {
        return new ReportBuilder(
            orderRepository: new OrderRepository($pdo),
            shippabilityEvaluator: new OrderShippabilityEvaluator(new OrderAvailabilityEvaluator()),
            reservationConflictDetector: new ReservationConflictDetector(),
            paymentMatchRepository: new PaymentMatchRepository($pdo),
            feedErrorRepository: new FeedErrorRepository($pdo),
            paymentReminderRule: new PaymentReminderRule(),
            cancellationSuggestionRule: new CancellationSuggestionRule(),
            conflictDismissals: new ReservationConflictDismissalRepository($pdo),
        );
    }
}
