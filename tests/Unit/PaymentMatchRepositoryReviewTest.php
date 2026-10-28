<?php

declare(strict_types=1);

namespace Noelo\Monitor\Tests\Unit;

use Noelo\Monitor\Reporting\PaymentMatchRepository;
use Noelo\Monitor\Tests\Support\DatabaseTestCase;

final class PaymentMatchRepositoryReviewTest extends DatabaseTestCase
{
    private function seedOrderAndTransaction(int $orderId, int $fioTransactionId): int
    {
        $ts = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->pdo->prepare(
            'INSERT INTO orders (id, created_at, status, delivery_name, email, total_price_cents, delivery_type, delivery_price_cents, payed_cents, is_cash_on_delivery, raw_hash, imported_at, updated_at)
             VALUES (:id, "2026-09-17 10:00:00", "spracováva sa", "Test", "test@example.com", 1000, "DPD point - vopred", 0, NULL, 0, :hash, :imported_at, :updated_at)'
        )->execute(['id' => $orderId, 'hash' => hash('sha256', (string) $orderId), 'imported_at' => $ts, 'updated_at' => $ts]);

        $this->pdo->prepare(
            'INSERT INTO fio_transactions (id, transaction_date, amount_cents, currency, variable_symbol, match_status, imported_at)
             VALUES (:id, "2026-09-17", 1000, "EUR", NULL, "matched_candidate", :ts)'
        )->execute(['id' => $fioTransactionId, 'ts' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')]);

        $this->pdo->prepare(
            'INSERT INTO payment_matches (fio_transaction_id, order_id, match_type, status, created_at)
             VALUES (:fio_id, :order_id, "amount_date_candidate", "pending_review", :ts)'
        )->execute(['fio_id' => $fioTransactionId, 'order_id' => $orderId, 'ts' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')]);

        return (int) $this->pdo->lastInsertId();
    }

    public function testConfirmMarksMatchAndTransactionAsManuallyConfirmed(): void
    {
        $matchId = $this->seedOrderAndTransaction(1, 100);

        (new PaymentMatchRepository($this->pdo))->confirm($matchId, 'admin');

        $match = $this->pdo->query("SELECT status, reviewed_by FROM payment_matches WHERE id = {$matchId}")->fetch();
        self::assertSame('confirmed', $match['status']);
        self::assertSame('admin', $match['reviewed_by']);

        $txStatus = $this->pdo->query('SELECT match_status FROM fio_transactions WHERE id = 100')->fetch()['match_status'];
        self::assertSame('manual_confirmed', $txStatus);
    }

    public function testRejectMarksMatchAndTransactionAsManuallyRejected(): void
    {
        $matchId = $this->seedOrderAndTransaction(2, 200);

        (new PaymentMatchRepository($this->pdo))->reject($matchId, 'admin');

        $match = $this->pdo->query("SELECT status FROM payment_matches WHERE id = {$matchId}")->fetch();
        self::assertSame('rejected', $match['status']);

        $txStatus = $this->pdo->query('SELECT match_status FROM fio_transactions WHERE id = 200')->fetch()['match_status'];
        self::assertSame('manual_rejected', $txStatus);
    }

    public function testConfirmedMatchNoLongerAppearsInPendingReview(): void
    {
        $matchId = $this->seedOrderAndTransaction(3, 300);
        $repo = new PaymentMatchRepository($this->pdo);

        $repo->confirm($matchId, 'admin');

        self::assertSame([], $repo->findPendingReviewForAdmin());
    }

    public function testCashOnDeliveryOrderMatchIsHiddenFromPendingReview(): void
    {
        $this->seedOrderAndTransaction(4, 400);
        $this->seedOrderAndTransaction(5, 500);
        $this->pdo->exec('UPDATE orders SET is_cash_on_delivery = 1 WHERE id = 4');
        $repo = new PaymentMatchRepository($this->pdo);

        self::assertSame([5], array_map('intval', array_column($repo->findPendingReviewForAdmin(), 'order_id')));
        self::assertSame([5], array_map('intval', array_column($repo->findPendingReview(), 'order_id')));
    }
}
