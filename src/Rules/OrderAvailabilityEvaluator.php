<?php

declare(strict_types=1);

namespace Noelo\Monitor\Rules;

use Noelo\Monitor\OrderFeed\Dto\OrderDto;
use Noelo\Monitor\OrderFeed\Dto\OrderedItemDto;

/**
 * Vyhodnocuje dostupnost poloziek objednavky (bez ohladu na platbu) podla
 * `readyItems` z objednavkoveho feedu - kolko kusov ma zakaznik realne
 * pripravenych/rezervovanych skladom. 0 = tovar este nemame, cakame nan.
 *
 * Stav objednavky:
 *   - vsetky polozky plne pripravene           -> SHIPPABLE
 *   - ziadny kus zo ziadnej polozky pripraveny -> UNAVAILABLE
 *   - inak (nieco pripravene, nieco chyba)     -> PARTIALLY_SHIPPABLE
 *
 * Zlavove polozky (zaporna cena) sa neposudzuju - nie su realny tovar.
 */
final class OrderAvailabilityEvaluator
{
    public const REASON_WAITING = 'čaká sa na tovar (0 pripravených)';
    public const REASON_SOLD_OUT = 'vypredané';
    public const REASON_UNKNOWN = 'neznámy stav (feed neobsahuje readyItems)';

    public function evaluate(OrderDto $order): OrderAvailabilityResult
    {
        $available = [];
        $unavailable = [];
        $anythingReady = false;

        foreach ($order->items as $item) {
            if ($item->isDiscount) {
                continue;
            }

            $availability = $this->evaluateItem($item);
            if ($availability->available) {
                $available[] = $availability;
            } else {
                $unavailable[] = $availability;
            }

            if (($availability->readyCount ?? 0) > 0) {
                $anythingReady = true;
            }
        }

        $status = match (true) {
            $unavailable === [] => OrderAvailabilityStatus::SHIPPABLE,
            !$anythingReady => OrderAvailabilityStatus::UNAVAILABLE,
            default => OrderAvailabilityStatus::PARTIALLY_SHIPPABLE,
        };

        return new OrderAvailabilityResult($status, $available, $unavailable);
    }

    private function isSoldOut(OrderedItemDto $item): bool
    {
        return $item->deliveryInfo !== null
            && mb_strtolower(trim($item->deliveryInfo)) === 'vypredané';
    }

    private function evaluateItem(OrderedItemDto $item): ItemAvailability
    {
        $ready = $item->readyItems;

        $reason = match (true) {
            $ready === null => self::REASON_UNKNOWN,
            $ready >= $item->count => null,
            $ready === 0 && $this->isSoldOut($item) => self::REASON_SOLD_OUT,
            $ready === 0 => self::REASON_WAITING,
            default => "pripravené {$ready} z {$item->count} ks",
        };

        return new ItemAvailability(
            orderItemId: $item->id,
            productCode: $item->productCode,
            title: $item->title,
            requestedCount: $item->count,
            available: $reason === null,
            reason: $reason,
            readyCount: $ready,
            deliveryInfo: $item->deliveryInfo,
        );
    }
}
