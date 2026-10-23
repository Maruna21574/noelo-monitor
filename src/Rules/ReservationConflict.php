<?php

declare(strict_types=1);

namespace Noelo\Monitor\Rules;

/**
 * "Pravdepodobny konflikt rezervacii" - feed neposkytuje vazbu rezervacie na
 * konkretnu objednavku, takze toto NIKDY netvrdi s istotou, ktora rezervacia
 * konkretne bracia vybaveniu - len ze stara nezaplatena objednavka na rovnaky
 * produkt existuje sucasne s tym, ze zaplatena/dobierkova objednavka nema
 * dost skladu.
 */
final class ReservationConflict
{
    /** @param int[] $possiblyBlockingOrderIds */
    public function __construct(
        public readonly string $productCode,
        public readonly int $blockedOrderId,
        public readonly array $possiblyBlockingOrderIds,
    ) {
    }
}
