<?php

declare(strict_types=1);

namespace Noelo\Monitor\Reporting;

use Noelo\Monitor\Rules\ItemAvailability;
use Noelo\Monitor\Support\Money;

/**
 * Zostavi cisto textovy denny report (bez HTML - e-mail nemusi renderovat HTML,
 * a plain text sa neda pouzit na XSS). Nikdy nevypisuje meno/e-mail zakaznika.
 *
 * E-mail ma byt citatelny za par sekund, nie stovky riadkov - preto hore je
 * vzdy suhrnny prehlad poctov a detailne zoznamy su orezane na rozumny pocet
 * s odkazom na dashboard pre kompletny zoznam.
 */
final class ReportTextRenderer
{
    private const MAX_DETAILED_ORDERS = 10;
    private const MAX_LIST_ITEMS = 25;

    public function __construct(private readonly ?string $dashboardUrl = null)
    {
    }

    public function render(DailyReport $report): string
    {
        $lines = [];
        $lines[] = 'Denny report - Noelo Monitor';
        $lines[] = 'Datum: ' . $report->runDate->format('Y-m-d');
        $lines[] = '';
        $lines[] = $this->renderSummary($report);
        $lines[] = '';

        $lines[] = $this->renderShippable($report);
        $lines[] = '';
        $lines[] = $this->renderPartiallyShippable($report);
        $lines[] = '';
        $lines[] = $this->renderUnavailable($report);
        $lines[] = '';
        $lines[] = $this->renderReservationConflicts($report);
        $lines[] = '';
        $lines[] = $this->renderUnmatchedPayments($report);
        $lines[] = '';
        $lines[] = $this->renderPaymentReminders($report);
        $lines[] = '';
        $lines[] = $this->renderCancellationSuggestions($report);
        $lines[] = '';
        $lines[] = $this->renderTechnicalErrors($report);

        if ($this->dashboardUrl !== null) {
            $lines[] = '';
            $lines[] = '--';
            $lines[] = "Kompletny prehlad a detaily: {$this->dashboardUrl}";
        }

        return implode("\n", $lines);
    }

    private function renderSummary(DailyReport $report): string
    {
        $lines = ['== Rychly prehlad =='];
        $lines[] = 'Pripravene na odoslanie: ' . count($report->shippableOrderIds);
        $lines[] = 'Mozno ciastocne odoslat: ' . count($report->partiallyShippable);
        $lines[] = 'Tovar nie je dostupny: ' . count($report->unavailable);
        $lines[] = 'Pravdepodobne konflikty rezervacii: ' . count($report->reservationConflicts);
        $lines[] = 'Mozne platby bez spravneho VS: ' . count($report->unmatchedPayments);
        $lines[] = 'Pripomenutie uhrady (5+ dni): ' . count($report->paymentReminders);
        $lines[] = 'Navrh na storno (7+ dni): ' . count($report->cancellationSuggestions);
        $lines[] = 'Technicke chyby: ' . count($report->technicalErrors);

        return implode("\n", $lines);
    }

    private function renderShippable(DailyReport $report): string
    {
        $lines = ['== Pripravene na odoslanie (' . count($report->shippableOrderIds) . ') =='];
        if ($report->shippableOrderIds === []) {
            $lines[] = '(ziadne)';
        } else {
            $lines[] = $this->formatOrderIdList($report->shippableOrderIds, $report);
        }
        return implode("\n", $lines);
    }

    private function renderPartiallyShippable(DailyReport $report): string
    {
        $total = count($report->partiallyShippable);
        $lines = ["== Mozno ciastocne odoslat ({$total}) =="];
        if ($report->partiallyShippable === []) {
            $lines[] = '(ziadne)';
        } else {
            $shown = 0;
            foreach ($report->partiallyShippable as $orderId => $result) {
                if ($shown >= self::MAX_DETAILED_ORDERS) {
                    break;
                }
                $lines[] = $report->datePrefix($orderId) . "Objednavka #{$orderId}:";
                $lines[] = '  Dostupne:';
                foreach ($result->availableItems as $item) {
                    $lines[] = '    ' . $this->formatItemLine($item);
                }
                $lines[] = '  Nedostupne:';
                foreach ($result->unavailableItems as $item) {
                    $lines[] = '    ' . $this->formatItemLine($item);
                }
                $shown++;
            }
            $this->appendMoreNote($lines, $total, $shown);
        }
        return implode("\n", $lines);
    }

    private function renderUnavailable(DailyReport $report): string
    {
        $total = count($report->unavailable);
        $lines = ["== Tovar nie je dostupny ({$total}) =="];
        if ($report->unavailable === []) {
            $lines[] = '(ziadne)';
        } else {
            $shown = 0;
            foreach ($report->unavailable as $orderId => $result) {
                if ($shown >= self::MAX_DETAILED_ORDERS) {
                    break;
                }
                $lines[] = $report->datePrefix($orderId) . "Objednavka #{$orderId}:";
                foreach ($result->unavailableItems as $item) {
                    $lines[] = '  ' . $this->formatItemLine($item);
                }
                $shown++;
            }
            $this->appendMoreNote($lines, $total, $shown);
        }
        return implode("\n", $lines);
    }

    private function renderReservationConflicts(DailyReport $report): string
    {
        $total = count($report->reservationConflicts);
        $lines = ["== Pravdepodobne konflikty sklad/rezervacie ({$total}) =="];
        if ($report->reservationConflicts === []) {
            $lines[] = '(ziadne)';
        } else {
            $shown = 0;
            foreach ($report->reservationConflicts as $conflict) {
                if ($shown >= self::MAX_LIST_ITEMS) {
                    break;
                }
                $blocking = implode(', ', array_map(static fn ($id) => "#{$id}", $conflict->possiblyBlockingOrderIds));
                $lines[] = "Produkt {$conflict->productCode}: " . $report->datePrefix($conflict->blockedOrderId) . "objednavka #{$conflict->blockedOrderId} "
                    . "mozno blokovana nezaplatenymi objednavkami ({$blocking}) drziacimi rezervaciu.";
                $shown++;
            }
            $this->appendMoreNote($lines, $total, $shown);
        }
        return implode("\n", $lines);
    }

    private function renderUnmatchedPayments(DailyReport $report): string
    {
        $total = count($report->unmatchedPayments);
        $lines = ["== Mozne platby bez spravneho VS - na rucne overenie ({$total}) =="];
        if ($report->unmatchedPayments === []) {
            $lines[] = '(ziadne)';
        } else {
            $shown = 0;
            foreach ($report->unmatchedPayments as $row) {
                if ($shown >= self::MAX_LIST_ITEMS) {
                    break;
                }
                $amount = Money::toDecimalString((int) $row['amount_cents']);
                $lines[] = ($row['order_created_at'] ?? false ? substr((string) $row['order_created_at'], 0, 10) . ' ' : '')
                    . "Objednavka #{$row['order_id']}: mozna platba {$amount} EUR dna {$row['transaction_date']} "
                    . '(typ zhody: ' . \Noelo\Monitor\Matching\PaymentMatchType::labelFor((string) $row['match_type']) . ')';
                $shown++;
            }
            $this->appendMoreNote($lines, $total, $shown);
        }
        return implode("\n", $lines);
    }

    private function renderPaymentReminders(DailyReport $report): string
    {
        $lines = ['== Pripomenutie uhrady - nezaplatene 5+ dni (' . count($report->paymentReminders) . ') =='];
        if ($report->paymentReminders === []) {
            $lines[] = '(ziadne)';
        } else {
            $lines[] = $this->formatOrderIdList(array_map(static fn ($o) => $o->id, $report->paymentReminders), $report);
        }
        return implode("\n", $lines);
    }

    private function renderCancellationSuggestions(DailyReport $report): string
    {
        $lines = ['== Navrh na storno - nezaplatene viac nez 7 dni (' . count($report->cancellationSuggestions) . ') =='];
        if ($report->cancellationSuggestions === []) {
            $lines[] = '(ziadne)';
        } else {
            $lines[] = $this->formatOrderIdList(array_map(static fn ($o) => $o->id, $report->cancellationSuggestions), $report);
        }
        return implode("\n", $lines);
    }

    private function renderTechnicalErrors(DailyReport $report): string
    {
        $total = count($report->technicalErrors);
        $lines = ["== Technicke chyby feedov/API ({$total}) =="];
        if ($report->technicalErrors === []) {
            $lines[] = '(ziadne)';
        } else {
            $shown = 0;
            foreach ($report->technicalErrors as $error) {
                if ($shown >= self::MAX_LIST_ITEMS) {
                    break;
                }
                $lines[] = "[{$error['feed_type']}] {$error['occurred_at']}: {$error['message']}";
                $shown++;
            }
            $this->appendMoreNote($lines, $total, $shown);
        }
        return implode("\n", $lines);
    }

    /** @param int[] $orderIds */
    private function formatOrderIdList(array $orderIds, DailyReport $report): string
    {
        $shown = array_slice($orderIds, 0, self::MAX_LIST_ITEMS);
        $text = implode(', ', array_map(static fn ($id) => $report->datePrefix($id) . "#{$id}", $shown));

        $remaining = count($orderIds) - count($shown);
        if ($remaining > 0) {
            $text .= " ... a este {$remaining} dalsich (pozri dashboard)";
        }

        return $text;
    }

    private function appendMoreNote(array &$lines, int $total, int $shown): void
    {
        $remaining = $total - $shown;
        if ($remaining > 0) {
            $lines[] = "... a este {$remaining} dalsich, pozri dashboard pre kompletny zoznam.";
        }
    }

    private function formatItemLine(ItemAvailability $item): string
    {
        return sprintf(
            'produkt %s "%s" - poziadane %d ks, pripravene %s, stav dodania: %s - %s',
            $item->productCode ?? '(neznamy kod)',
            $item->title,
            $item->requestedCount,
            $item->readyCount !== null ? (string) $item->readyCount : '?',
            $item->deliveryInfo ?? 'neuvedeny',
            $item->reason ?? 'dostupne',
        );
    }
}
