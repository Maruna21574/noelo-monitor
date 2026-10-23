<?php

declare(strict_types=1);

namespace Noelo\Monitor\Tests\Unit;

use Noelo\Monitor\Fio\Dto\FioTransactionDto;
use Noelo\Monitor\Matching\PaymentMatcher;
use Noelo\Monitor\Matching\PaymentMatchType;
use Noelo\Monitor\Tests\Unit\Support\OrderTestFactory;
use PHPUnit\Framework\TestCase;

final class PaymentMatcherTest extends TestCase
{
    private function transaction(
        int $id,
        int $amountCents,
        ?string $vs = null,
        ?string $counterAccountName = null,
        ?string $messageForRecipient = null,
        ?string $comment = null,
        string $date = '2026-09-17',
        string $currency = 'EUR',
    ): FioTransactionDto {
        return new FioTransactionDto(
            id: $id,
            date: new \DateTimeImmutable($date),
            amountCents: $amountCents,
            currency: $currency,
            variableSymbol: $vs,
            counterAccountName: $counterAccountName,
            messageForRecipient: $messageForRecipient,
            userIdentification: null,
            comment: $comment,
        );
    }

    private function unpaidOrder(int $id, int $totalPriceCents, string $createdAt = '2026-09-17 10:00:00', string $deliveryName = 'Jana Nováková'): \Noelo\Monitor\OrderFeed\Dto\OrderDto
    {
        $item = OrderTestFactory::item(1, 'P1', unitPriceCents: $totalPriceCents);
        $order = OrderTestFactory::order($id, [$item], payedCents: null, isCashOnDelivery: false);

        return new \Noelo\Monitor\OrderFeed\Dto\OrderDto(
            id: $order->id,
            createdAt: new \DateTimeImmutable($createdAt),
            status: $order->status,
            deliveryName: $deliveryName,
            email: $order->email,
            totalPriceCents: $totalPriceCents,
            deliveryType: $order->deliveryType,
            deliveryPriceCents: 0,
            payedCents: null,
            isCashOnDelivery: false,
            rawHash: $order->rawHash,
            items: $order->items,
        );
    }

    public function testExactVariableSymbolAndAmountMatchWinsOverEverythingElse(): void
    {
        $order = $this->unpaidOrder(3014234346, 4425);
        $transactions = [
            $this->transaction(1, 4425, vs: '3014234346'),
        ];

        $candidates = (new PaymentMatcher())->findCandidates($order, $transactions);

        self::assertCount(1, $candidates);
        self::assertSame(PaymentMatchType::EXACT_VS, $candidates[0]->matchType);
        self::assertSame(1, $candidates[0]->fioTransactionId);
    }

    public function testMissingVariableSymbolFallsBackToAmountAndDateCandidate(): void
    {
        $order = $this->unpaidOrder(3014234346, 4425, createdAt: '2026-09-17 10:00:00');
        $transactions = [
            $this->transaction(2, 4425, vs: null, date: '2026-09-18'),
        ];

        $candidates = (new PaymentMatcher())->findCandidates($order, $transactions);

        self::assertCount(1, $candidates);
        self::assertSame(PaymentMatchType::AMOUNT_DATE_CANDIDATE, $candidates[0]->matchType);
    }

    public function testTwoOrdersWithSameAmountBothGetTheSameTransactionAsAmbiguousCandidate(): void
    {
        $transaction = $this->transaction(3, 5000, vs: null, date: '2026-09-17');

        $orderA = $this->unpaidOrder(1001, 5000);
        $orderB = $this->unpaidOrder(1002, 5000);

        $matcher = new PaymentMatcher();
        $candidatesA = $matcher->findCandidates($orderA, [$transaction]);
        $candidatesB = $matcher->findCandidates($orderB, [$transaction]);

        // ziadna strana sa nesmie automaticky "vyhrat" - obe objednavky dostanu
        // rovnaku transakciu ako kandidata, nech to admin rozhodne rucne.
        self::assertCount(1, $candidatesA);
        self::assertCount(1, $candidatesB);
        self::assertSame(3, $candidatesA[0]->fioTransactionId);
        self::assertSame(3, $candidatesB[0]->fioTransactionId);
    }

    public function testOutsideDateToleranceFallsBackToNameOrMessageHeuristic(): void
    {
        $order = $this->unpaidOrder(3014234346, 4425, createdAt: '2026-01-01 10:00:00', deliveryName: 'Jana Nováková');
        $transactions = [
            $this->transaction(4, 4425, vs: null, counterAccountName: 'JANA NOVAKOVA', date: '2026-09-17'),
        ];

        $candidates = (new PaymentMatcher())->findCandidates($order, $transactions);

        self::assertCount(1, $candidates);
        self::assertSame(PaymentMatchType::NAME_MESSAGE_CANDIDATE, $candidates[0]->matchType);
    }

    public function testNoCandidateWhenAmountDoesNotMatchAnything(): void
    {
        $order = $this->unpaidOrder(3014234346, 4425);
        $transactions = [$this->transaction(5, 999)];

        $candidates = (new PaymentMatcher())->findCandidates($order, $transactions);

        self::assertSame([], $candidates);
    }

    public function testMatchingAmountInForeignCurrencyIsNeverTreatedAsExactVsMatch(): void
    {
        $order = $this->unpaidOrder(3014234346, 4425);
        $transactions = [
            $this->transaction(6, 4425, vs: '3014234346', currency: 'USD'),
        ];

        $candidates = (new PaymentMatcher())->findCandidates($order, $transactions);

        self::assertSame([], $candidates);
    }

    public function testMatchingAmountInForeignCurrencyIsNeverTreatedAsAmountDateCandidate(): void
    {
        $order = $this->unpaidOrder(3014234346, 4425, createdAt: '2026-09-17 10:00:00');
        $transactions = [
            $this->transaction(7, 4425, vs: null, date: '2026-09-17', currency: 'CZK'),
        ];

        $candidates = (new PaymentMatcher())->findCandidates($order, $transactions);

        self::assertSame([], $candidates);
    }
}
