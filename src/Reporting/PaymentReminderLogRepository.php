<?php

declare(strict_types=1);

namespace Noelo\Monitor\Reporting;

use PDO;

/**
 * Evidencia automaticky odoslanych pripomienok nezaplatenia - zabezpecuje, ze
 * zakaznik dostane pripomienku k objednavke najviac raz.
 *
 * Stavy: 'pending' (pokus prave prebieha / spadol uprostred), 'sent' (shop odpovedal 2xx),
 * 'failed' (shop odpovedal chybou - da sa bezpecne skusit znova), 'unknown'
 * (spojenie spadlo po odoslani poziadavky - mail mohol aj nemusel odist, preto sa
 * automaticky NEOPAKUJE, aby zakaznik nedostal dva maily).
 */
final class PaymentReminderLogRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return int[] objednavky, ktorym sa uz nesmie posielat (sent/pending/unknown) */
    public function findHandledOrderIds(): array
    {
        $stmt = $this->pdo->query("SELECT order_id FROM payment_reminders_sent WHERE status <> 'failed'");

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Atomicky si "zabere" objednavku pred volanim shopu. Vrati false, ak ju uz
     * spracovava/spracoval niekto iny (napr. dva sucasne behy kontroly).
     */
    public function claim(int $orderId, \DateTimeImmutable $now): bool
    {
        $attemptedAt = $now->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            "INSERT IGNORE INTO payment_reminders_sent (order_id, status, attempted_at) VALUES (:id, 'pending', :at)"
        );
        $stmt->execute(['id' => $orderId, 'at' => $attemptedAt]);
        if ($stmt->rowCount() === 1) {
            return true;
        }

        $stmt = $this->pdo->prepare(
            "UPDATE payment_reminders_sent SET status = 'pending', http_status = NULL, attempted_at = :at
             WHERE order_id = :id AND status = 'failed'"
        );
        $stmt->execute(['id' => $orderId, 'at' => $attemptedAt]);

        return $stmt->rowCount() === 1;
    }

    public function markSent(int $orderId, int $httpStatus, \DateTimeImmutable $now): void
    {
        $this->update($orderId, 'sent', $httpStatus, $now->format('Y-m-d H:i:s'));
    }

    public function markFailed(int $orderId, int $httpStatus): void
    {
        $this->update($orderId, 'failed', $httpStatus, null);
    }

    public function markUnknown(int $orderId): void
    {
        $this->update($orderId, 'unknown', null, null);
    }

    /** @return array<int, array{order_id: string, status: string, attempted_at: string}> */
    public function findRecent(int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT order_id, status, attempted_at FROM payment_reminders_sent ORDER BY attempted_at DESC, order_id DESC LIMIT :n'
        );
        $stmt->bindValue('n', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    private function update(int $orderId, string $status, ?int $httpStatus, ?string $sentAt): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE payment_reminders_sent SET status = :status, http_status = :http_status, sent_at = :sent_at WHERE order_id = :id'
        );
        $stmt->execute(['status' => $status, 'http_status' => $httpStatus, 'sent_at' => $sentAt, 'id' => $orderId]);
    }
}
