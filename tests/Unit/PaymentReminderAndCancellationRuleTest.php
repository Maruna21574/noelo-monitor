<?php

declare(strict_types=1);

namespace Noelo\Monitor\Tests\Unit;

use Noelo\Monitor\Rules\CancellationSuggestionRule;
use Noelo\Monitor\Rules\PaymentReminderRule;
use Noelo\Monitor\Tests\Unit\Support\OrderTestFactory;
use PHPUnit\Framework\TestCase;

final class PaymentReminderAndCancellationRuleTest extends TestCase
{
    private const NOW = '2026-09-17 12:00:00';

    private function unpaidOrderAgeDays(int $days, bool $isCod = false): \Noelo\Monitor\OrderFeed\Dto\OrderDto
    {
        $createdAt = (new \DateTimeImmutable(self::NOW))->modify("-{$days} days")->format('Y-m-d H:i:s');
        return OrderTestFactory::order(
            1,
            [OrderTestFactory::item(1, 'P1')],
            payedCents: null,
            isCashOnDelivery: $isCod,
            createdAt: $createdAt,
        );
    }

    public function testThreeDayOldUnpaidOrderTriggersNeitherReminderNorCancellation(): void
    {
        $order = $this->unpaidOrderAgeDays(3);
        $now = new \DateTimeImmutable(self::NOW);

        self::assertFalse((new PaymentReminderRule())->matches($order, $now));
        self::assertFalse((new CancellationSuggestionRule())->matches($order, $now));
    }

    public function testFiveDayOldUnpaidOrderTriggersReminderOnly(): void
    {
        $order = $this->unpaidOrderAgeDays(5);
        $now = new \DateTimeImmutable(self::NOW);

        self::assertTrue((new PaymentReminderRule())->matches($order, $now));
        self::assertFalse((new CancellationSuggestionRule())->matches($order, $now));
    }

    public function testEightDayOldUnpaidOrderTriggersCancellationSuggestionOnlyNotReminder(): void
    {
        $order = $this->unpaidOrderAgeDays(8);
        $now = new \DateTimeImmutable(self::NOW);

        self::assertFalse((new PaymentReminderRule())->matches($order, $now));
        self::assertTrue((new CancellationSuggestionRule())->matches($order, $now));
    }

    public function testCashOnDeliveryOrderNeverTriggersEitherRuleRegardlessOfAge(): void
    {
        $order = $this->unpaidOrderAgeDays(30, isCod: true);
        $now = new \DateTimeImmutable(self::NOW);

        self::assertFalse((new PaymentReminderRule())->matches($order, $now));
        self::assertFalse((new CancellationSuggestionRule())->matches($order, $now));
    }

    public function testPaidOrderNeverTriggersEitherRuleRegardlessOfAge(): void
    {
        $createdAt = (new \DateTimeImmutable(self::NOW))->modify('-30 days')->format('Y-m-d H:i:s');
        $order = OrderTestFactory::order(1, [OrderTestFactory::item(1, 'P1')], payedCents: 1000, createdAt: $createdAt);
        $now = new \DateTimeImmutable(self::NOW);

        self::assertFalse((new PaymentReminderRule())->matches($order, $now));
        self::assertFalse((new CancellationSuggestionRule())->matches($order, $now));
    }
}
