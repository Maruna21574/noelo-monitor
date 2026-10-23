<?php

declare(strict_types=1);

namespace Noelo\Monitor\Tests\Unit;

use Noelo\Monitor\Rules\ReservationConflictDetector;
use Noelo\Monitor\Tests\Unit\Support\OrderTestFactory;
use PHPUnit\Framework\TestCase;

final class ReservationConflictDetectorTest extends TestCase
{
    private const NOW = '2026-09-17 12:00:00';

    private function detect(array $orders): array
    {
        return (new ReservationConflictDetector(unpaidAgeThresholdDays: 3))
            ->detect($orders, new \DateTimeImmutable(self::NOW));
    }

    private function createdDaysAgo(int $days): string
    {
        return (new \DateTimeImmutable(self::NOW))->modify("-{$days} days")->format('Y-m-d H:i:s');
    }

    public function testOldUnpaidOrderHoldingReservedPiecesBlocksWaitingPaidOrder(): void
    {
        $oldUnpaid = OrderTestFactory::order(
            1,
            [OrderTestFactory::item(1, 'P1', readyItems: 1)],
            payedCents: null,
            createdAt: $this->createdDaysAgo(4),
        );
        $blockedPaid = OrderTestFactory::order(2, [OrderTestFactory::item(2, 'P1', readyItems: 0)], payedCents: 1000);

        $conflicts = $this->detect([$oldUnpaid, $blockedPaid]);

        self::assertCount(1, $conflicts);
        self::assertSame('P1', $conflicts[0]->productCode);
        self::assertSame(2, $conflicts[0]->blockedOrderId);
        self::assertSame([1], $conflicts[0]->possiblyBlockingOrderIds);
    }

    public function testUnpaidOrderYoungerThanThresholdDoesNotCountAsHolder(): void
    {
        $recentUnpaid = OrderTestFactory::order(
            1,
            [OrderTestFactory::item(1, 'P1', readyItems: 1)],
            payedCents: null,
            createdAt: $this->createdDaysAgo(1),
        );
        $blockedPaid = OrderTestFactory::order(2, [OrderTestFactory::item(2, 'P1', readyItems: 0)], payedCents: 1000);

        self::assertSame([], $this->detect([$recentUnpaid, $blockedPaid]));
    }

    public function testUnpaidOrderWithNothingReservedDoesNotBlockAnyone(): void
    {
        $oldUnpaid = OrderTestFactory::order(
            1,
            [OrderTestFactory::item(1, 'P1', readyItems: 0)],
            payedCents: null,
            createdAt: $this->createdDaysAgo(10),
        );
        $blockedPaid = OrderTestFactory::order(2, [OrderTestFactory::item(2, 'P1', readyItems: 0)], payedCents: 1000);

        self::assertSame([], $this->detect([$oldUnpaid, $blockedPaid]));
    }

    public function testNoConflictWhenPaidOrderIsAlreadyFullyReady(): void
    {
        $oldUnpaid = OrderTestFactory::order(
            1,
            [OrderTestFactory::item(1, 'P1', readyItems: 1)],
            payedCents: null,
            createdAt: $this->createdDaysAgo(10),
        );
        $paid = OrderTestFactory::order(2, [OrderTestFactory::item(2, 'P1', readyItems: 1)], payedCents: 1000);

        self::assertSame([], $this->detect([$oldUnpaid, $paid]));
    }
}
