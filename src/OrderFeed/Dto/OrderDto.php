<?php

declare(strict_types=1);

namespace Noelo\Monitor\OrderFeed\Dto;

final class OrderDto
{
    /** @param OrderedItemDto[] $items */
    public function __construct(
        public readonly int $id,
        public readonly \DateTimeImmutable $createdAt,
        public readonly string $status,
        public readonly string $deliveryName,
        public readonly string $email,
        public readonly int $totalPriceCents,
        public readonly string $deliveryType,
        public readonly int $deliveryPriceCents,
        public readonly ?int $payedCents,
        public readonly bool $isCashOnDelivery,
        public readonly string $rawHash,
        public readonly array $items,
    ) {
    }

    /**
     * Jediny zdroj pravdy pre "je objednavka zaplatena" naprieč celou appkou.
     * `payedCents === 0` sa povazuje za nezaplatene (nie kazde neprazdne `payed`
     * automaticky znamena zaplatene) - v realnych datach sa to zatial nestalo,
     * ale je to bezpecnejsia/spravnejsia interpretacia ako "cokolvek neprazdne = zaplatene".
     */
    public function isPaid(): bool
    {
        return $this->payedCents !== null && $this->payedCents > 0;
    }

    /**
     * Zaplatena ALEBO dobierka - podmienka pre pravidla 1-4 (shippabilita,
     * konflikt rezervacii). Objednavky, ktore toto nesplnaju, riesia ine
     * pravidla (pripomenutie uhrady / navrh na storno).
     */
    public function isEligibleForShipping(): bool
    {
        return $this->isPaid() || $this->isCashOnDelivery;
    }
}
