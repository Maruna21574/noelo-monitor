<?php

declare(strict_types=1);

namespace Noelo\Monitor\Matching;

use Noelo\Monitor\Fio\Dto\FioTransactionDto;
use Noelo\Monitor\OrderFeed\Dto\OrderDto;
use Noelo\Monitor\Support\TextNormalizer;

/**
 * Paruje NEZAPLATENU objednavku (podla XML feedu) s pravdepodobnymi platbami vo Fio,
 * v poradi z zadania:
 *   1. presny VS = cislo objednavky AND suma sedi -> EXACT_VS
 *   2. bez VS: rovnaka suma v primeranom datumovom okne -> AMOUNT_DATE_CANDIDATE
 *   3. doplnkovo meno platitela / sprava pre prijemcu (rovnaka suma, bez ohladu na datum) -> NAME_MESSAGE_CANDIDATE
 *
 * Ziadny vysledok sa nikdy automaticky nepotvrdzuje (ani presna VS zhoda) - vzdy je
 * to len kandidat na rucne overenie administratorom (pozri Reporting/PaymentMatchRepository).
 */
final class PaymentMatcher
{
    /**
     * E-shop pocita ceny vylucne v EUR (OrderDto nema vlastne pole meny) -
     * platba v inej mene sa nikdy nesmie napárovat len preto, ze cislo sumy
     * numericky sedi (napr. 44.25 USD vs. 44.25 EUR).
     */
    private const EXPECTED_CURRENCY = 'EUR';

    /**
     * @param FioTransactionDto[] $unmatchedTransactions transakcie s match_status = 'unmatched'
     * @return PaymentMatchCandidate[]
     */
    public function findCandidates(OrderDto $order, array $unmatchedTransactions, int $dateToleranceDays = 5): array
    {
        $expectedAmountCents = $order->totalPriceCents;
        $orderIdAsVs = (string) $order->id;

        $exact = array_values(array_filter(
            $unmatchedTransactions,
            static fn (FioTransactionDto $t) => $t->variableSymbol === $orderIdAsVs
                && $t->amountCents === $expectedAmountCents
                && $t->currency === self::EXPECTED_CURRENCY
        ));
        if ($exact !== []) {
            return $this->toCandidates($order->id, $exact, PaymentMatchType::EXACT_VS);
        }

        $sameAmount = array_values(array_filter(
            $unmatchedTransactions,
            static fn (FioTransactionDto $t) => $t->amountCents === $expectedAmountCents
                && $t->currency === self::EXPECTED_CURRENCY
        ));

        $withinDateWindow = array_values(array_filter(
            $sameAmount,
            fn (FioTransactionDto $t) => $this->withinDateTolerance($t->date, $order->createdAt, $dateToleranceDays)
        ));
        if ($withinDateWindow !== []) {
            return $this->toCandidates($order->id, $withinDateWindow, PaymentMatchType::AMOUNT_DATE_CANDIDATE);
        }

        $nameOrMessageMatch = array_values(array_filter(
            $sameAmount,
            fn (FioTransactionDto $t) => TextNormalizer::containsNormalized($t->counterAccountName, $order->deliveryName)
                || TextNormalizer::containsNormalized($t->messageForRecipient, $orderIdAsVs)
                || TextNormalizer::containsNormalized($t->comment, $orderIdAsVs)
        ));
        if ($nameOrMessageMatch !== []) {
            return $this->toCandidates($order->id, $nameOrMessageMatch, PaymentMatchType::NAME_MESSAGE_CANDIDATE);
        }

        return [];
    }

    private function withinDateTolerance(\DateTimeImmutable $fioDate, \DateTimeImmutable $orderCreatedAt, int $toleranceDays): bool
    {
        $diffDays = (int) $orderCreatedAt->diff($fioDate)->days;
        return $diffDays <= $toleranceDays;
    }

    /**
     * @param FioTransactionDto[] $transactions
     * @return PaymentMatchCandidate[]
     */
    private function toCandidates(int $orderId, array $transactions, PaymentMatchType $type): array
    {
        return array_map(
            static fn (FioTransactionDto $t) => new PaymentMatchCandidate($t->id, $orderId, $type),
            $transactions
        );
    }
}
