<?php

declare(strict_types=1);

namespace Noelo\Monitor\Reporting;

use Noelo\Monitor\Fio\FioTransactionRepository;
use Noelo\Monitor\Matching\PaymentMatcher;

/**
 * Prejde vsetky nezaplatene (a nie dobierkove) objednavky a skusi ich napárovat
 * na doteraz neparovane Fio transakcie. Vysledne kandidatske zhody sa len
 * zaznamenaju na rucne overenie - nic sa automaticky nepotvrdzuje.
 */
final class PaymentMatchingService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly FioTransactionRepository $fioTransactionRepository,
        private readonly PaymentMatcher $matcher,
        private readonly PaymentMatchRepository $matchRepository,
    ) {
    }

    public function run(): int
    {
        $unpaidOrders = $this->orderRepository->findUnpaidNonCashOnDeliveryOrders();
        if ($unpaidOrders === []) {
            return 0;
        }

        $unmatchedTransactions = $this->fioTransactionRepository->findUnmatched();
        if ($unmatchedTransactions === []) {
            return 0;
        }

        $candidatesFound = 0;
        foreach ($unpaidOrders as $order) {
            $candidates = $this->matcher->findCandidates($order, $unmatchedTransactions);
            if ($candidates !== []) {
                $this->matchRepository->saveCandidates($candidates);
                $candidatesFound += count($candidates);
            }
        }

        return $candidatesFound;
    }
}
