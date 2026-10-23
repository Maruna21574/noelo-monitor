<?php

declare(strict_types=1);

namespace Noelo\Monitor\Tests\Unit;

use Noelo\Monitor\Fio\FioTransactionRepository;
use Noelo\Monitor\Matching\PaymentMatcher;
use Noelo\Monitor\Reporting\OrderRepository;
use Noelo\Monitor\Reporting\PaymentMatchingService;
use Noelo\Monitor\Reporting\PaymentMatchRepository;
use Noelo\Monitor\Tests\Support\DatabaseTestCase;

final class PaymentMatchingServiceTest extends DatabaseTestCase
{
    private function insertOrder(int $id, int $totalPriceCents, ?int $payedCents, bool $isCod = false): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO orders (id, created_at, status, delivery_name, email, total_price_cents, delivery_type, delivery_price_cents, payed_cents, is_cash_on_delivery, raw_hash, imported_at, updated_at)
             VALUES (:id, :created_at, "spracováva sa", "Test", "test@example.com", :total, "DPD point - vopred", 0, :payed, :cod, :hash, :imported_at, :updated_at)'
        );
        // Fixny (nie "now") spolocny cas pre vsetky testovacie objednavky v ramci
        // jedneho testu - findCurrentlyOpenOrders() vybera podla `updated_at = MAX(updated_at)`,
        // takze vsetky musia mat presne rovnaku hodnotu (rovnako ako pri realnom
        // importe, kde jeden beh feedu da vsetkym objednavkam jeden spolocny cas).
        $now = '2026-09-17 09:00:00';
        $stmt->execute([
            'id' => $id,
            'created_at' => '2026-09-17 10:00:00',
            'total' => $totalPriceCents,
            'payed' => $payedCents,
            'cod' => (int) $isCod,
            'hash' => hash('sha256', (string) $id),
            'imported_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertFioTransaction(int $id, int $amountCents, ?string $vs, string $matchStatus = 'unmatched'): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO fio_transactions (id, transaction_date, amount_cents, currency, variable_symbol, match_status, imported_at)
             VALUES (:id, :date, :amount, "EUR", :vs, :status, :now)'
        );
        $stmt->execute([
            'id' => $id,
            'date' => '2026-09-17',
            'amount' => $amountCents,
            'vs' => $vs,
            'status' => $matchStatus,
            'now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    private function service(): PaymentMatchingService
    {
        return new PaymentMatchingService(
            new OrderRepository($this->pdo),
            new FioTransactionRepository($this->pdo),
            new PaymentMatcher(),
            new PaymentMatchRepository($this->pdo),
        );
    }

    public function testExactMatchCreatesPendingReviewRowAndUpdatesTransactionStatus(): void
    {
        $this->insertOrder(3014234346, 4425, payedCents: null);
        $this->insertFioTransaction(1, 4425, '3014234346');

        $found = $this->service()->run();

        self::assertSame(1, $found);

        $match = $this->pdo->query('SELECT match_type, status FROM payment_matches WHERE order_id = 3014234346')->fetch();
        self::assertSame('exact_vs', $match['match_type']);
        self::assertSame('pending_review', $match['status']);

        $txStatus = $this->pdo->query('SELECT match_status FROM fio_transactions WHERE id = 1')->fetch()['match_status'];
        self::assertSame('matched_exact', $txStatus);
    }

    public function testPaidOrdersAndCodOrdersAreNeverConsideredForMatching(): void
    {
        $this->insertOrder(1, 1000, payedCents: 1000);
        $this->insertOrder(2, 2000, payedCents: null, isCod: true);
        $this->insertFioTransaction(1, 1000, null);
        $this->insertFioTransaction(2, 2000, null);

        $found = $this->service()->run();

        self::assertSame(0, $found);
        $matchCount = (int) $this->pdo->query('SELECT COUNT(*) AS c FROM payment_matches')->fetch()['c'];
        self::assertSame(0, $matchCount);
    }

    public function testManuallyRejectedTransactionIsNeverReconsidered(): void
    {
        $this->insertOrder(3014234346, 4425, payedCents: null);
        $this->insertFioTransaction(1, 4425, '3014234346', matchStatus: 'manual_rejected');

        $found = $this->service()->run();

        self::assertSame(0, $found);
        $status = $this->pdo->query('SELECT match_status FROM fio_transactions WHERE id = 1')->fetch()['match_status'];
        self::assertSame('manual_rejected', $status);
    }

    public function testTwoUnpaidOrdersWithSameAmountBothGetFlaggedAgainstSameTransaction(): void
    {
        $this->insertOrder(1001, 5000, payedCents: null);
        $this->insertOrder(1002, 5000, payedCents: null);
        $this->insertFioTransaction(9, 5000, null);

        $found = $this->service()->run();

        self::assertSame(2, $found);
        $orderIds = $this->pdo->query('SELECT order_id FROM payment_matches WHERE fio_transaction_id = 9 ORDER BY order_id')
            ->fetchAll(\PDO::FETCH_COLUMN);
        self::assertSame(['1001', '1002'], array_map('strval', $orderIds));
    }
}
