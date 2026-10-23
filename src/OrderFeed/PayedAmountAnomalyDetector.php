<?php

declare(strict_types=1);

namespace Noelo\Monitor\OrderFeed;

use Noelo\Monitor\OrderFeed\Dto\OrderDto;
use Noelo\Monitor\Support\Money;

/**
 * V realnom feede sa objavili objednavky, kde `payed` nesedi so `totalPrice`
 * (napr. objednavka za 14,88 EUR s payed=6708,00 EUR) - zjavna anomalia na
 * strane zdrojovych dat, nie chyba parsovania. Appka `payed` naďalej berie
 * ako jediny zdroj pravdy pre "zaplatene/nezaplatene" (nic nevymysla), ale
 * takuto nezrovnalost oznaci administratorovi na rucne overenie.
 *
 * @param OrderDto[] $orders
 * @return string[] sanitizovane spravy (len cislo objednavky a sumy, bez PII)
 */
final class PayedAmountAnomalyDetector
{
    /** @return string[] */
    public function detect(array $orders): array
    {
        $warnings = [];

        foreach ($orders as $order) {
            if ($order->payedCents === null || $order->payedCents === $order->totalPriceCents) {
                continue;
            }

            $payed = Money::toDecimalString($order->payedCents);
            $total = Money::toDecimalString($order->totalPriceCents);
            $warnings[] = "Objednavka #{$order->id}: suma 'payed' ({$payed} EUR) nesedi s 'totalPrice' "
                . "({$total} EUR) - over rucne, ci je objednavka naozaj zaplatena spravne.";
        }

        return $warnings;
    }
}
