<?php

declare(strict_types=1);

namespace Noelo\Monitor\Reporting;

use Noelo\Monitor\Matching\PaymentMatchCandidate;
use Noelo\Monitor\Matching\PaymentMatchType;
use PDO;

final class PaymentMatchRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param PaymentMatchCandidate[] $candidates */
    public function saveCandidates(array $candidates): void
    {
        foreach ($candidates as $candidate) {
            $this->saveCandidate($candidate);
        }
    }

    private function saveCandidate(PaymentMatchCandidate $candidate): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO payment_matches (fio_transaction_id, order_id, match_type, status, created_at)
             VALUES (:fio_transaction_id, :order_id, :match_type, :status, :created_at)
             ON DUPLICATE KEY UPDATE match_type = VALUES(match_type)'
        );
        $stmt->execute([
            'fio_transaction_id' => $candidate->fioTransactionId,
            'order_id' => $candidate->orderId,
            'match_type' => $candidate->matchType->value,
            'status' => 'pending_review',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        // Fio match_status sa aktualizuje len ak je stale 'unmatched' - nikdy neprepiseme
        // rucne potvrdeny/zamietnuty zaznam z admin rozhrania.
        $newStatus = $candidate->matchType === PaymentMatchType::EXACT_VS ? 'matched_exact' : 'matched_candidate';
        $stmt = $this->pdo->prepare(
            "UPDATE fio_transactions SET match_status = :new_status
             WHERE id = :id AND match_status = 'unmatched'"
        );
        $stmt->execute(['new_status' => $newStatus, 'id' => $candidate->fioTransactionId]);
    }

    /**
     * Kandidati na rucne overenie (nikdy automaticky potvrdene) pre denny report.
     *
     * @return array<int, array{order_id: string, order_created_at: string, match_type: string, amount_cents: string, transaction_date: string}>
     */
    public function findPendingReview(): array
    {
        $stmt = $this->pdo->query(
            "SELECT pm.order_id, o.created_at AS order_created_at, pm.match_type, ft.amount_cents, ft.transaction_date
             FROM payment_matches pm
             JOIN fio_transactions ft ON ft.id = pm.fio_transaction_id
             JOIN orders o ON o.id = pm.order_id
             WHERE pm.status = 'pending_review' AND o.is_cash_on_delivery = 0
             ORDER BY o.created_at, pm.order_id"
        );

        return $stmt->fetchAll();
    }

    /**
     * Rovnake ako findPendingReview(), ale aj s ID zaznamu - potrebne pre
     * tlacidla potvrdit/zamietnut v administracii.
     *
     * @return array<int, array{id: string, order_id: string, order_created_at: string, match_type: string, amount_cents: string, transaction_date: string}>
     */
    public function findPendingReviewForAdmin(): array
    {
        $stmt = $this->pdo->query(
            "SELECT pm.id, pm.order_id, o.created_at AS order_created_at, o.status AS order_status, pm.match_type, ft.amount_cents, ft.transaction_date
             FROM payment_matches pm
             JOIN fio_transactions ft ON ft.id = pm.fio_transaction_id
             JOIN orders o ON o.id = pm.order_id
             WHERE pm.status = 'pending_review' AND o.is_cash_on_delivery = 0
             ORDER BY o.created_at, pm.order_id"
        );

        return $stmt->fetchAll();
    }

    /**
     * Objednavky, ku ktorym je v banke najdena platba cakajuca na overenie alebo
     * uz rucne potvrdena - takym sa nesmie posielat pripomenutie nezaplatenia.
     *
     * @return int[]
     */
    public function findOrderIdsWithPendingOrConfirmedMatch(): array
    {
        $stmt = $this->pdo->query(
            "SELECT DISTINCT order_id FROM payment_matches WHERE status IN ('pending_review', 'confirmed')"
        );

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Rucne potvrdenie administratorom - jedina cesta, ako sa kandidat moze
     * stat "potvrdenym". Aplikacia sama nikdy nic automaticky nepotvrdzuje.
     */
    public function confirm(int $matchId, string $reviewedBy): void
    {
        $this->setReviewStatus($matchId, 'confirmed', 'manual_confirmed', $reviewedBy);
    }

    public function reject(int $matchId, string $reviewedBy): void
    {
        $this->setReviewStatus($matchId, 'rejected', 'manual_rejected', $reviewedBy);
    }

    private function setReviewStatus(int $matchId, string $matchStatus, string $fioMatchStatus, string $reviewedBy): void
    {
        $stmt = $this->pdo->prepare('SELECT fio_transaction_id FROM payment_matches WHERE id = :id');
        $stmt->execute(['id' => $matchId]);
        $fioTransactionId = $stmt->fetchColumn();

        if ($fioTransactionId === false) {
            throw new \RuntimeException('Zaznam parovania platby nebol najdeny.');
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE payment_matches SET status = :status, reviewed_by = :reviewed_by, reviewed_at = :reviewed_at
                 WHERE id = :id'
            );
            $stmt->execute([
                'status' => $matchStatus,
                'reviewed_by' => $reviewedBy,
                'reviewed_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'id' => $matchId,
            ]);

            $stmt = $this->pdo->prepare('UPDATE fio_transactions SET match_status = :status WHERE id = :id');
            $stmt->execute(['status' => $fioMatchStatus, 'id' => $fioTransactionId]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
