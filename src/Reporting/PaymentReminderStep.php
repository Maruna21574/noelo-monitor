<?php

declare(strict_types=1);

namespace Noelo\Monitor\Reporting;

use Noelo\Monitor\Http\HttpClientInterface;
use Noelo\Monitor\Http\HttpTransportException;
use Noelo\Monitor\Rules\PaymentReminderRule;

/**
 * Automaticky posle zakaznikovi pripomienku nezaplatenia: zavola adresu shopu
 * (rovnaku, ako tlacidlo "odoslat info o nezaplateni" v jeho administracii),
 * ktora mail odosle.
 *
 * Bezpecnostne poistky (posiela sa realnym zakaznikom):
 *   - kazdej objednavke najviac raz (viz PaymentReminderLogRepository),
 *   - nikdy ak je k objednavke platba na overenie/potvrdena z banky,
 *   - maximalny pocet mailov za jeden beh,
 *   - rezim dry-run len vypise, komu by sa poslalo.
 */
final class PaymentReminderStep
{
    /**
     * @param string $urlTemplate adresa shopu, `{id}` sa nahradi ID objednavky pre shop
     * @param string $shopIdPrefix prefix, ktory sa z cisla objednavky odstrani, aby vzniklo ID pre shop
     *                             (3014238270 -> 238270); prazdny = cislo sa pouzije cele
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly OrderRepository $orderRepository,
        private readonly PaymentMatchRepository $paymentMatchRepository,
        private readonly PaymentReminderLogRepository $log,
        private readonly PaymentReminderRule $rule,
        private readonly FeedErrorRepository $feedErrors,
        private readonly string $urlTemplate,
        private readonly bool $dryRun,
        private readonly string $shopIdPrefix = '3014',
        private readonly int $maxPerRun = 20,
        private readonly int $timeoutSeconds = 20,
        private readonly ?string $basicAuthUsername = null,
        private readonly ?string $basicAuthPassword = null,
    ) {
    }

    public function run(int $checkRunId, \DateTimeImmutable $now): PaymentReminderSummary
    {
        $blocked = array_flip(array_merge(
            $this->log->findHandledOrderIds(),
            $this->paymentMatchRepository->findOrderIdsWithPendingOrConfirmedMatch(),
        ));

        $due = [];
        foreach ($this->orderRepository->findUnpaidNonCashOnDeliveryOrders() as $order) {
            if (isset($blocked[$order->id]) || !$this->rule->matches($order, $now)) {
                continue;
            }
            $due[] = $order->id;
        }

        $skippedOverLimit = max(0, count($due) - $this->maxPerRun);
        $due = array_slice($due, 0, $this->maxPerRun);

        if ($this->dryRun) {
            return new PaymentReminderSummary(true, wouldSendOrderIds: $due, skippedOverLimit: $skippedOverLimit);
        }

        $sent = [];
        $failed = [];
        foreach ($due as $orderId) {
            if ($this->send($checkRunId, $orderId, $now)) {
                $sent[] = $orderId;
            } else {
                $failed[] = $orderId;
            }
        }

        return new PaymentReminderSummary(false, sentOrderIds: $sent, failedOrderIds: $failed, skippedOverLimit: $skippedOverLimit);
    }

    private function send(int $checkRunId, int $orderId, \DateTimeImmutable $now): bool
    {
        $shopId = $this->shopIdFor($orderId);
        if ($shopId === null) {
            $this->feedErrors->record($checkRunId, 'payment_reminder', "Pripomienka objednavky #{$orderId}: cislo nezodpoveda ocakavanemu formatu, neposlana.");
            return false;
        }

        if (!$this->log->claim($orderId, $now)) {
            return false;
        }

        try {
            $response = $this->httpClient->get(
                str_replace('{id}', $shopId, $this->urlTemplate),
                $this->timeoutSeconds,
                $this->basicAuthUsername,
                $this->basicAuthPassword,
            );
        } catch (HttpTransportException) {
            // Pozadovanie mohlo dojst na server aj ked odpoved neprisla - radsej neposielame znova.
            $this->log->markUnknown($orderId);
            $this->feedErrors->record($checkRunId, 'payment_reminder', "Pripomienka objednavky #{$orderId}: spojenie so shopom zlyhalo, nezname ci mail odisiel (neopakuje sa automaticky).");
            return false;
        }

        if ($response->statusCode >= 200 && $response->statusCode < 300) {
            // Shop vrati text odoslaneho mailu, v ktorom je cislo objednavky (variabilny symbol).
            // Samotne HTTP 200 nestaci - aj prihlasovacia/chybova stranka moze vratit 200.
            if (!str_contains($response->body, (string) $orderId)) {
                $this->log->markUnknown($orderId);
                $this->feedErrors->record($checkRunId, 'payment_reminder', "Pripomienka objednavky #{$orderId}: odpoved shopu neobsahuje cislo objednavky, nezname ci mail odisiel (skontroluj prihlasenie/adresu).");
                return false;
            }

            $this->log->markSent($orderId, $response->statusCode, $now);
            return true;
        }

        $this->log->markFailed($orderId, $response->statusCode);
        $this->feedErrors->record($checkRunId, 'payment_reminder', "Pripomienka objednavky #{$orderId}: shop odpovedal HTTP {$response->statusCode}.");
        return false;
    }

    private function shopIdFor(int $orderId): ?string
    {
        $id = (string) $orderId;
        if ($this->shopIdPrefix === '') {
            return $id;
        }
        if (!str_starts_with($id, $this->shopIdPrefix) || strlen($id) <= strlen($this->shopIdPrefix)) {
            return null;
        }

        return substr($id, strlen($this->shopIdPrefix));
    }
}
