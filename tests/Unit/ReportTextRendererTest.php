<?php

declare(strict_types=1);

namespace Noelo\Monitor\Tests\Unit;

use Noelo\Monitor\Reporting\DailyReport;
use Noelo\Monitor\Reporting\ReportTextRenderer;
use Noelo\Monitor\Rules\ItemAvailability;
use Noelo\Monitor\Rules\OrderAvailabilityResult;
use Noelo\Monitor\Rules\OrderAvailabilityStatus;
use Noelo\Monitor\Rules\ReservationConflict;
use Noelo\Monitor\Tests\Unit\Support\OrderTestFactory;
use PHPUnit\Framework\TestCase;

final class ReportTextRendererTest extends TestCase
{
    public function testEmptyReportRendersAllSectionsAsEmpty(): void
    {
        $report = new DailyReport(
            runDate: new \DateTimeImmutable('2026-09-17'),
            shippableOrderIds: [],
            partiallyShippable: [],
            unavailable: [],
            reservationConflicts: [],
            unmatchedPayments: [],
            paymentReminders: [],
            cancellationSuggestions: [],
            technicalErrors: [],
        );

        $text = (new ReportTextRenderer())->render($report);

        self::assertStringContainsString('Pripravene na odoslanie', $text);
        self::assertStringContainsString('Mozno ciastocne odoslat', $text);
        self::assertStringContainsString('Tovar nie je dostupny', $text);
        self::assertSame(8, substr_count($text, '(ziadne)'));
    }

    public function testRendersOrderNumbersAndItemDetailsWithoutCustomerPii(): void
    {
        $unavailableItem = new ItemAvailability(
            orderItemId: 1,
            productCode: 'P1',
            title: 'Testovaci produkt',
            requestedCount: 3,
            available: false,
            reason: 'pripravené 2 z 3 ks',
            readyCount: 2,
        );

        $report = new DailyReport(
            runDate: new \DateTimeImmutable('2026-09-17'),
            shippableOrderIds: [3014234346],
            partiallyShippable: [],
            unavailable: [
                2001 => new OrderAvailabilityResult(OrderAvailabilityStatus::UNAVAILABLE, [], [$unavailableItem]),
            ],
            reservationConflicts: [new ReservationConflict('P1', 2001, [1500])],
            unmatchedPayments: [
                ['order_id' => '3005', 'match_type' => 'amount_date_candidate', 'amount_cents' => '4425', 'transaction_date' => '2026-09-16'],
            ],
            paymentReminders: [OrderTestFactory::order(4001, [OrderTestFactory::item(1, 'P1')], payedCents: null)],
            cancellationSuggestions: [OrderTestFactory::order(4002, [OrderTestFactory::item(1, 'P1')], payedCents: null)],
            technicalErrors: [
                ['feed_type' => 'order_feed', 'message' => 'XML parse error na riadku 12', 'occurred_at' => '2026-09-17 03:00:05'],
            ],
        );

        $text = (new ReportTextRenderer())->render($report);

        self::assertStringContainsString('#3014234346', $text);
        self::assertStringContainsString('P1', $text);
        self::assertStringContainsString('poziadane 3 ks', $text);
        self::assertStringContainsString('pripravene 2', $text);
        self::assertStringContainsString('pripravené 2 z 3 ks', $text);
        self::assertStringContainsString('#2001', $text);
        self::assertStringContainsString('#1500', $text);
        self::assertStringContainsString('44.25 EUR', $text);
        self::assertStringContainsString('#4001', $text);
        self::assertStringContainsString('#4002', $text);
        self::assertStringContainsString('XML parse error', $text);

        // ziadne osobne udaje (meno/e-mail) sa nikdy nesmu objavit
        self::assertStringNotContainsString('Test Zakaznik', $text);
        self::assertStringNotContainsString('example.com', $text);
    }

    public function testTopSummaryShowsCorrectCountsRegardlessOfTruncation(): void
    {
        $unavailable = [];
        for ($i = 1; $i <= 15; $i++) {
            $item = new ItemAvailability(1, 'P1', 'Produkt', 1, false, 'čaká sa na tovar (0 pripravených)', readyCount: 0);
            $unavailable[$i] = new OrderAvailabilityResult(OrderAvailabilityStatus::UNAVAILABLE, [], [$item]);
        }

        $report = new DailyReport(
            runDate: new \DateTimeImmutable('2026-09-17'),
            shippableOrderIds: [],
            partiallyShippable: [],
            unavailable: $unavailable,
            reservationConflicts: [],
            unmatchedPayments: [],
            paymentReminders: [],
            cancellationSuggestions: [],
            technicalErrors: [],
        );

        $text = (new ReportTextRenderer())->render($report);

        self::assertStringContainsString('Tovar nie je dostupny: 15', $text);
    }

    public function testDetailedOrderListingIsTruncatedWithRemainingCountNote(): void
    {
        $unavailable = [];
        for ($i = 1; $i <= 15; $i++) {
            $item = new ItemAvailability(1, 'P1', 'Produkt', 1, false, 'čaká sa na tovar (0 pripravených)', readyCount: 0);
            $unavailable[$i] = new OrderAvailabilityResult(OrderAvailabilityStatus::UNAVAILABLE, [], [$item]);
        }

        $report = new DailyReport(
            runDate: new \DateTimeImmutable('2026-09-17'),
            shippableOrderIds: [],
            partiallyShippable: [],
            unavailable: $unavailable,
            reservationConflicts: [],
            unmatchedPayments: [],
            paymentReminders: [],
            cancellationSuggestions: [],
            technicalErrors: [],
        );

        $text = (new ReportTextRenderer())->render($report);

        self::assertStringContainsString('#10:', $text);
        self::assertStringNotContainsString('#11:', $text);
        self::assertStringContainsString('a este 5 dalsich', $text);
    }

    public function testCompactOrderIdListIsTruncatedAfterMaxItems(): void
    {
        $reminders = array_map(
            static fn (int $id) => OrderTestFactory::order($id, [OrderTestFactory::item(1, 'P1')], payedCents: null),
            range(1, 30)
        );

        $report = new DailyReport(
            runDate: new \DateTimeImmutable('2026-09-17'),
            shippableOrderIds: [],
            partiallyShippable: [],
            unavailable: [],
            reservationConflicts: [],
            unmatchedPayments: [],
            paymentReminders: $reminders,
            cancellationSuggestions: [],
            technicalErrors: [],
        );

        $text = (new ReportTextRenderer())->render($report);

        self::assertStringContainsString('#25', $text);
        self::assertStringNotContainsString('#26', $text);
        self::assertStringContainsString('a este 5 dalsich', $text);
    }

    public function testDashboardUrlIsAppendedAsFooterWhenConfigured(): void
    {
        $report = new DailyReport(
            runDate: new \DateTimeImmutable('2026-09-17'),
            shippableOrderIds: [],
            partiallyShippable: [],
            unavailable: [],
            reservationConflicts: [],
            unmatchedPayments: [],
            paymentReminders: [],
            cancellationSuggestions: [],
            technicalErrors: [],
        );

        $text = (new ReportTextRenderer('https://monitor.kliky.sk'))->render($report);

        self::assertStringContainsString('https://monitor.kliky.sk', $text);
    }
}
