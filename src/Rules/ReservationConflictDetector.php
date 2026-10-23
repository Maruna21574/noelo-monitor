<?php

declare(strict_types=1);

namespace Noelo\Monitor\Rules;

use Noelo\Monitor\OrderFeed\Dto\OrderDto;

/**
 * Pravidlo 4 zo zadania: "Nezaplatena objednavka blokuje zaplatenu".
 *
 * Vdaka `readyItems` vo feede vieme, ktora objednavka ma tovar realne
 * rezervovany. Konflikt sa hlasi, ked:
 *   - existuje nezaplatena (nie dobierkova) objednavka stara aspon N dni,
 *     ktora ma dany produkt realne rezervovany (readyItems > 0),
 *   - a sucasne ina, platobne opravnena objednavka na ten isty produkt
 *     nema pripravene vsetky kusy (readyItems < count).
 *
 * Aplikacia nic nepresuva - len upozorni administratora, ze uvolnenie
 * rezervacie zo starej nezaplatenej objednavky by mohlo pomoct.
 */
final class ReservationConflictDetector
{
    public function __construct(private readonly int $unpaidAgeThresholdDays = 3)
    {
    }

    /**
     * @param OrderDto[] $orders vsetky relevantne (nedavno otvorene) objednavky
     * @return ReservationConflict[]
     */
    public function detect(array $orders, \DateTimeImmutable $now): array
    {
        $oldUnpaidHoldersByProduct = $this->collectOldUnpaidHolders($orders, $now);
        if ($oldUnpaidHoldersByProduct === []) {
            return [];
        }

        $conflicts = [];
        foreach ($orders as $order) {
            if (!$order->isEligibleForShipping()) {
                continue;
            }

            foreach ($order->items as $item) {
                if ($item->isDiscount || $item->productCode === null || $item->readyItems === null) {
                    continue;
                }
                if ($item->readyItems >= $item->count) {
                    continue;
                }

                $blockingOrderIds = array_values(array_filter(
                    $oldUnpaidHoldersByProduct[$item->productCode] ?? [],
                    static fn (int $id) => $id !== $order->id
                ));
                if ($blockingOrderIds === []) {
                    continue;
                }

                $conflicts[] = new ReservationConflict($item->productCode, $order->id, $blockingOrderIds);
            }
        }

        return $conflicts;
    }

    /**
     * @param OrderDto[] $orders
     * @return array<string, int[]>
     */
    private function collectOldUnpaidHolders(array $orders, \DateTimeImmutable $now): array
    {
        $result = [];

        foreach ($orders as $order) {
            if ($order->isEligibleForShipping()) {
                continue;
            }

            $ageDays = $order->createdAt->diff($now)->days;
            if ($ageDays < $this->unpaidAgeThresholdDays) {
                continue;
            }

            foreach ($order->items as $item) {
                if ($item->isDiscount || $item->productCode === null || ($item->readyItems ?? 0) <= 0) {
                    continue;
                }
                $result[$item->productCode][] = $order->id;
            }
        }

        return $result;
    }
}
