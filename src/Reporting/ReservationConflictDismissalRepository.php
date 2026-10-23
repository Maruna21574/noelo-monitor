<?php

declare(strict_types=1);

namespace Noelo\Monitor\Reporting;

use Noelo\Monitor\Rules\ReservationConflict;
use PDO;

/**
 * Konflikty rezervacii, ktore administrator "odklikol" (uz ich nechce vidiet
 * v dashboarde ani v e-maile). Kluc je dvojica blokovana objednavka + produkt.
 */
final class ReservationConflictDismissalRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function dismiss(int $blockedOrderId, string $productCode, string $dismissedBy): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO reservation_conflict_dismissals (blocked_order_id, product_code, dismissed_by, dismissed_at)
             VALUES (:order_id, :product_code, :dismissed_by, :dismissed_at)'
        );
        $stmt->execute([
            'order_id' => $blockedOrderId,
            'product_code' => $productCode,
            'dismissed_by' => $dismissedBy,
            'dismissed_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param ReservationConflict[] $conflicts
     * @return ReservationConflict[]
     */
    public function withoutDismissed(array $conflicts): array
    {
        if ($conflicts === []) {
            return [];
        }

        $dismissed = [];
        foreach ($this->pdo->query('SELECT blocked_order_id, product_code FROM reservation_conflict_dismissals')->fetchAll() as $row) {
            $dismissed[$row['blocked_order_id'] . '|' . $row['product_code']] = true;
        }

        return array_values(array_filter(
            $conflicts,
            static fn (ReservationConflict $c) => !isset($dismissed[$c->blockedOrderId . '|' . $c->productCode])
        ));
    }
}
