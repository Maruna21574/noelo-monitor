<?php

declare(strict_types=1);

namespace Noelo\Monitor\Tests\Unit;

use Noelo\Monitor\Reporting\CheckRunRepository;
use Noelo\Monitor\Reporting\FeedErrorRepository;
use Noelo\Monitor\Reporting\OrderRepository;
use Noelo\Monitor\Reporting\PaymentMatchRepository;
use Noelo\Monitor\Reporting\ReportBuilder;
use Noelo\Monitor\Rules\CancellationSuggestionRule;
use Noelo\Monitor\Rules\OrderAvailabilityEvaluator;
use Noelo\Monitor\Rules\OrderAvailabilityStatus;
use Noelo\Monitor\Rules\OrderShippabilityEvaluator;
use Noelo\Monitor\Rules\PaymentReminderRule;
use Noelo\Monitor\Rules\ReservationConflictDetector;
use Noelo\Monitor\Tests\Support\DatabaseTestCase;

final class ReportBuilderTest extends DatabaseTestCase
{
    private function insertOrder(int $id, int $totalPriceCents, ?int $payedCents, string $createdAt, bool $isCod = false): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO orders (id, created_at, status, delivery_name, email, total_price_cents, delivery_type, delivery_price_cents, payed_cents, is_cash_on_delivery, raw_hash, imported_at, updated_at)
             VALUES (:id, :created_at, "spracováva sa", "Test", "test@example.com", :total, "DPD point - vopred", 0, :payed, :cod, :hash, :imported_at, :updated_at)'
        );
        // Fixny (nie "now") spolocny cas pre vsetky testovacie objednavky v ramci
        // jedneho testu - findCurrentlyOpenOrders() vybera podla `updated_at = MAX(updated_at)`,
        // takze vsetky musia mat presne rovnaku hodnotu (rovnako ako pri realnom
        // importe, kde jeden beh feedu da vsetkym objednavkam jeden spolocny cas).
        $ts = '2026-09-17 09:00:00';
        $stmt->execute([
            'id' => $id,
            'created_at' => $createdAt,
            'total' => $totalPriceCents,
            'payed' => $payedCents,
            'cod' => (int) $isCod,
            'hash' => hash('sha256', (string) $id),
            'imported_at' => $ts,
            'updated_at' => $ts,
        ]);
    }

    private function insertItem(int $itemId, int $orderId, string $productCode, int $count, int $unitPriceCents, ?int $readyItems = null): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO order_items (id, order_id, product_code, title, count, ready_items, unit_price_cents, line_total_cents, is_discount)
             VALUES (:id, :order_id, :code, "Test produkt", :count, :ready_items, :price, :total, 0)'
        );
        $stmt->execute([
            'id' => $itemId,
            'order_id' => $orderId,
            'code' => $productCode,
            'count' => $count,
            'ready_items' => $readyItems,
            'price' => $unitPriceCents,
            'total' => $unitPriceCents * $count,
        ]);
    }

    private function builder(): ReportBuilder
    {
        return new ReportBuilder(
            orderRepository: new OrderRepository($this->pdo),
            shippabilityEvaluator: new OrderShippabilityEvaluator(new OrderAvailabilityEvaluator()),
            reservationConflictDetector: new ReservationConflictDetector(),
            paymentMatchRepository: new PaymentMatchRepository($this->pdo),
            feedErrorRepository: new FeedErrorRepository($this->pdo),
            paymentReminderRule: new PaymentReminderRule(),
            cancellationSuggestionRule: new CancellationSuggestionRule(),
        );
    }

    public function testBuildsShippableAndUnavailableSectionsFromRealOrders(): void
    {
        $this->insertOrder(1, 1000, payedCents: 1000, createdAt: '2026-09-17 10:00:00');
        $this->insertItem(1, 1, 'P1', 1, 1000, readyItems: 1);

        $this->insertOrder(2, 2000, payedCents: 2000, createdAt: '2026-09-17 10:00:00');
        $this->insertItem(2, 2, 'P2', 1, 2000, readyItems: 0);

        $checkRunId = (new CheckRunRepository($this->pdo))->start('manual');

        $report = $this->builder()->build(new \DateTimeImmutable('2026-09-17'), $checkRunId);

        self::assertSame([1], $report->shippableOrderIds);
        self::assertArrayHasKey(2, $report->unavailable);
        self::assertSame(OrderAvailabilityStatus::UNAVAILABLE, $report->unavailable[2]->status);
    }

    public function testIncludesFeedErrorsForGivenCheckRun(): void
    {
        $checkRuns = new CheckRunRepository($this->pdo);
        $checkRunId = $checkRuns->start('manual');
        (new FeedErrorRepository($this->pdo))->record($checkRunId, 'order_feed', 'XML parse error na riadku 5');

        $report = $this->builder()->build(new \DateTimeImmutable('2026-09-17'), $checkRunId);

        self::assertCount(1, $report->technicalErrors);
        self::assertSame('order_feed', $report->technicalErrors[0]['feed_type']);
    }

    public function testIncludesReminderAndCancellationSuggestionsBasedOnOrderAge(): void
    {
        $this->insertOrder(10, 1000, payedCents: null, createdAt: '2026-09-12 10:00:00'); // 5 dni
        $this->insertItem(10, 10, 'P1', 1, 1000);
        $this->insertOrder(11, 1000, payedCents: null, createdAt: '2026-09-08 10:00:00'); // 9 dni
        $this->insertItem(11, 11, 'P1', 1, 1000);

        $checkRunId = (new CheckRunRepository($this->pdo))->start('manual');
        $report = $this->builder()
            ->build(new \DateTimeImmutable('2026-09-17 10:00:00'), $checkRunId);

        $reminderIds = array_map(static fn ($o) => $o->id, $report->paymentReminders);
        $cancelIds = array_map(static fn ($o) => $o->id, $report->cancellationSuggestions);

        self::assertSame([10], $reminderIds);
        self::assertSame([11], $cancelIds);
    }
}
