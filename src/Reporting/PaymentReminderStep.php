<?php

declare(strict_types=1);

namespace Noelo\Monitor\Reporting;

use Noelo\Monitor\Http\HttpClientInterface;
use Noelo\Monitor\Http\HttpTransportException;
use Noelo\Monitor\OrderFeed\Dto\OrderDto;
use Noelo\Monitor\Rules\PaymentReminderRule;

/**
 * Posle zakaznikovi pripomienku nezaplatenia: zavola adresu shopu (rovnaku, ako
 * tlacidlo "odoslat info o nezaplateni" v jeho administracii), ktora mail odosle.
 *
 * Pouziva sa dvoma sposobmi:
 *   - automaticky pri dennej kontrole (`run`, rezim live / dry-run),
 *   - rucne tlacidlom v dashboarde (`sendManually`) - mail odide az po kliknuti.
 *
 * Bezpecnostne poistky (posiela sa realnym zakaznikom):
 *   - kazdej objednavke najviac raz (viz PaymentReminderLogRepository),
 *   - nikdy ak je k objednavke platba na overenie/potvrdena z banky,
 *   - maximalny pocet mailov za jeden automaticky beh,
 *   - rezim dry-run len vypise, komu by sa poslalo.
 */
final class PaymentReminderStep
{
    public const OUTCOME_SENT = 'sent';
    public const OUTCOME_FAILED = 'failed';
    public const OUTCOME_UNKNOWN = 'unknown';
    public const OUTCOME_NOT_DUE = 'not-due';

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
        $due = array_map(static fn (OrderDto $o) => $o->id, $this->findDueOrders($now));

        $skippedOverLimit = max(0, count($due) - $this->maxPerRun);
        $due = array_slice($due, 0, $this->maxPerRun);

        if ($this->dryRun) {
            return new PaymentReminderSummary(true, wouldSendOrderIds: $due, skippedOverLimit: $skippedOverLimit);
        }

        $sent = [];
        $failed = [];
        foreach ($due as $orderId) {
            if ($this->send($checkRunId, $orderId, $now) === self::OUTCOME_SENT) {
                $sent[] = $orderId;
            } else {
                $failed[] = $orderId;
            }
        }

        return new PaymentReminderSummary(false, sentOrderIds: $sent, failedOrderIds: $failed, skippedOverLimit: $skippedOverLimit);
    }

    /**
     * Rucne odoslanie jednej pripomienky z dashboardu. Znova overi, ze objednavka
     * este skutocne caka na pripomienku (nezaplatena, nie je platba na overenie,
     * este sa neposielalo) - nedovoli poslat mail komukolvek podvrhnutym ID.
     *
     * @return string jedna z OUTCOME_* konstant
     */
    public function sendManually(int $orderId, \DateTimeImmutable $now): string
    {
        $isDue = false;
        foreach ($this->findDueOrders($now) as $order) {
            if ($order->id === $orderId) {
                $isDue = true;
                break;
            }
        }
        if (!$isDue) {
            return self::OUTCOME_NOT_DUE;
        }

        return $this->send(null, $orderId, $now);
    }

    /** Administrator sa rozhodol pripomienku neposielat - objednavka sa uz nebude ponukat. */
    public function skip(int $orderId, \DateTimeImmutable $now): void
    {
        $this->log->markSkipped($orderId, $now);
    }

    /** @return OrderDto[] */
    private function findDueOrders(\DateTimeImmutable $now): array
    {
        $blocked = array_flip(array_merge(
            $this->log->findHandledOrderIds(),
            $this->paymentMatchRepository->findOrderIdsWithPendingOrConfirmedMatch(),
        ));

        $due = [];
        foreach ($this->orderRepository->findUnpaidNonCashOnDeliveryOrders() as $order) {
            if (!isset($blocked[$order->id]) && $this->rule->matches($order, $now)) {
                $due[] = $order;
            }
        }

        return $due;
    }

    /** @param ?int $checkRunId null = rucne odoslanie (chyba sa ukaze v dashboarde, nie v feed_errors) */
    private function send(?int $checkRunId, int $orderId, \DateTimeImmutable $now): string
    {
        $shopId = $this->shopIdFor($orderId);
        if ($shopId === null) {
            $this->recordError($checkRunId, "Pripomienka objednavky #{$orderId}: cislo nezodpoveda ocakavanemu formatu, neposlana.");
            return self::OUTCOME_FAILED;
        }

        if (!$this->log->claim($orderId, $now)) {
            return self::OUTCOME_NOT_DUE;
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
            $this->recordError($checkRunId, "Pripomienka objednavky #{$orderId}: spojenie so shopom zlyhalo, nezname ci mail odisiel (neopakuje sa automaticky).");
            return self::OUTCOME_UNKNOWN;
        }

        if ($response->statusCode >= 200 && $response->statusCode < 300) {
            // Shop vrati text odoslaneho mailu, v ktorom je cislo objednavky (variabilny symbol).
            // Samotne HTTP 200 nestaci - aj prihlasovacia/chybova stranka moze vratit 200.
            if (!str_contains($response->body, (string) $orderId)) {
                $this->log->markUnknown($orderId);
                $this->recordError($checkRunId, "Pripomienka objednavky #{$orderId}: odpoved shopu neobsahuje cislo objednavky, nezname ci mail odisiel (skontroluj prihlasenie/adresu).");
                return self::OUTCOME_UNKNOWN;
            }

            $this->log->markSent($orderId, $response->statusCode, $now);
            return self::OUTCOME_SENT;
        }

        $this->log->markFailed($orderId, $response->statusCode);
        $this->recordError($checkRunId, "Pripomienka objednavky #{$orderId}: shop odpovedal HTTP {$response->statusCode}.");
        return self::OUTCOME_FAILED;
    }

    private function recordError(?int $checkRunId, string $message): void
    {
        if ($checkRunId !== null) {
            $this->feedErrors->record($checkRunId, 'payment_reminder', $message);
        }
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
