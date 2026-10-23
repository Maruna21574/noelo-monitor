<?php

declare(strict_types=1);

namespace Noelo\Monitor\Tests\Unit;

use Noelo\Monitor\Http\HttpClientInterface;
use Noelo\Monitor\Http\HttpResponse;
use Noelo\Monitor\Http\HttpTransportException;
use Noelo\Monitor\Reporting\CheckRunRepository;
use Noelo\Monitor\Reporting\FeedErrorRepository;
use Noelo\Monitor\Reporting\OrderRepository;
use Noelo\Monitor\Reporting\PaymentMatchRepository;
use Noelo\Monitor\Reporting\PaymentReminderLogRepository;
use Noelo\Monitor\Reporting\PaymentReminderStep;
use Noelo\Monitor\Rules\PaymentReminderRule;
use Noelo\Monitor\Tests\Support\DatabaseTestCase;

final class PaymentReminderStepTest extends DatabaseTestCase
{
    private const NOW = '2026-10-23 03:00:00';

    /** @var string[] */
    private array $calledUrls = [];

    private function insertOrder(int $id, string $createdAt, string $deliveryType = 'DPD point - vopred', bool $cod = false, ?int $payed = null): void
    {
        $ts = '2026-10-23 02:00:00';
        $this->pdo->prepare(
            'INSERT INTO orders (id, created_at, status, delivery_name, email, total_price_cents, delivery_type, delivery_price_cents, payed_cents, is_cash_on_delivery, raw_hash, imported_at, updated_at)
             VALUES (:id, :created_at, "spracováva sa", "Test", "test@example.com", 1000, :delivery_type, 0, :payed, :cod, :hash, :ts1, :ts2)'
        )->execute([
            'id' => $id, 'created_at' => $createdAt, 'delivery_type' => $deliveryType,
            'payed' => $payed, 'cod' => (int) $cod, 'hash' => hash('sha256', (string) $id), 'ts1' => $ts, 'ts2' => $ts,
        ]);
    }

    private function step(HttpResponse|\Throwable $response, bool $dryRun = false, int $max = 20): PaymentReminderStep
    {
        $http = new class($response, $this->calledUrls) implements HttpClientInterface {
            public function __construct(private readonly HttpResponse|\Throwable $response, private array &$urls)
            {
            }

            public function get(string $url, int $timeoutSeconds, ?string $basicAuthUsername = null, ?string $basicAuthPassword = null): HttpResponse
            {
                $this->urls[] = $url;
                if ($this->response instanceof \Throwable) {
                    throw $this->response;
                }
                // 'ok' simuluje spravnu odpoved shopu: text mailu s cislom objednavky.
                if ($this->response->body === 'ok') {
                    preg_match('/id=(\d+)/', $url, $m);
                    return new HttpResponse($this->response->statusCode, 'v.s.: 3014' . $m[1]);
                }
                return $this->response;
            }
        };

        return new PaymentReminderStep(
            httpClient: $http,
            orderRepository: new OrderRepository($this->pdo),
            paymentMatchRepository: new PaymentMatchRepository($this->pdo),
            log: new PaymentReminderLogRepository($this->pdo),
            rule: new PaymentReminderRule(),
            feedErrors: new FeedErrorRepository($this->pdo),
            urlTemplate: 'https://shop.test/service.php?action=orderActions&id={id}&type=9',
            dryRun: $dryRun,
            maxPerRun: $max,
        );
    }

    private function checkRunId(): int
    {
        return (new CheckRunRepository($this->pdo))->start('cron', null);
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::NOW);
    }

    public function testSendsReminderOnceUsingShopIdWithoutPrefix(): void
    {
        $this->insertOrder(3014238270, '2026-10-17 10:00:00'); // 5+ dni, nezaplatena

        $step = $this->step(new HttpResponse(200, 'ok'));
        $first = $step->run($this->checkRunId(), $this->now());
        $second = $step->run($this->checkRunId(), $this->now());

        self::assertSame([3014238270], $first->sentOrderIds);
        self::assertSame([], $second->sentOrderIds);
        self::assertSame(['https://shop.test/service.php?action=orderActions&id=238270&type=9'], $this->calledUrls);
    }

    public function testSkipsTooNewCodPaidAndCancellationRangeOrders(): void
    {
        $this->insertOrder(3014000001, '2026-10-21 10:00:00');                         // len 2 dni
        $this->insertOrder(3014000002, '2026-10-15 10:00:00', 'Kuriér - dobierka', true); // dobierka
        $this->insertOrder(3014000003, '2026-10-15 10:00:00', payed: 1000);            // zaplatena
        $this->insertOrder(3014000004, '2026-10-10 10:00:00');                         // 13 dni - uz storno

        $summary = $this->step(new HttpResponse(200, 'ok'))->run($this->checkRunId(), $this->now());

        self::assertSame([], $summary->sentOrderIds);
        self::assertSame([], $this->calledUrls);
    }

    public function testSkipsOrderWithPaymentWaitingForReview(): void
    {
        $this->insertOrder(3014000005, '2026-10-17 10:00:00');
        $this->pdo->exec('INSERT INTO fio_transactions (id, transaction_date, amount_cents, currency, match_status, imported_at) VALUES (1, "2026-10-18", 1000, "EUR", "matched_candidate", "2026-10-23 02:00:00")');
        $this->pdo->exec('INSERT INTO payment_matches (fio_transaction_id, order_id, match_type, status, created_at) VALUES (1, 3014000005, "amount_date_candidate", "pending_review", "2026-10-23 02:00:00")');

        $summary = $this->step(new HttpResponse(200, 'ok'))->run($this->checkRunId(), $this->now());

        self::assertSame([], $summary->sentOrderIds);
        self::assertSame([], $this->calledUrls);
    }

    public function testDryRunSendsNothingAndWritesNothing(): void
    {
        $this->insertOrder(3014238270, '2026-10-17 10:00:00');

        $summary = $this->step(new HttpResponse(200, 'ok'), dryRun: true)->run($this->checkRunId(), $this->now());

        self::assertTrue($summary->dryRun);
        self::assertSame([3014238270], $summary->wouldSendOrderIds);
        self::assertSame([], $this->calledUrls);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM payment_reminders_sent')->fetchColumn());
    }

    public function testHttpErrorIsRetriedNextRunButTransportErrorIsNot(): void
    {
        $this->insertOrder(3014000006, '2026-10-17 10:00:00');
        $this->insertOrder(3014000007, '2026-10-17 11:00:00');

        // 1. beh: 500 pre prvu, spadnute spojenie pre druhu
        $http500 = $this->step(new HttpResponse(500, ''));
        $http500->run($this->checkRunId(), $this->now());
        self::assertSame('failed', $this->statusOf(3014000006));

        $this->pdo->exec("UPDATE payment_reminders_sent SET status = 'unknown' WHERE order_id = 3014000007");

        // 2. beh: 'failed' sa skusi znova, 'unknown' nikdy
        $this->calledUrls = [];
        $summary = $this->step(new HttpResponse(200, 'ok'))->run($this->checkRunId(), $this->now());

        self::assertSame([3014000006], $summary->sentOrderIds);
        self::assertSame('sent', $this->statusOf(3014000006));
        self::assertSame('unknown', $this->statusOf(3014000007));
    }

    public function testTransportErrorMarksUnknownAndDoesNotRetry(): void
    {
        $this->insertOrder(3014000008, '2026-10-17 10:00:00');

        $this->step(new HttpTransportException('timeout'))->run($this->checkRunId(), $this->now());
        $this->calledUrls = [];
        $this->step(new HttpResponse(200, 'ok'))->run($this->checkRunId(), $this->now());

        self::assertSame('unknown', $this->statusOf(3014000008));
        self::assertSame([], $this->calledUrls);
    }

    public function testOkStatusWithoutOrderNumberInBodyIsNotTreatedAsSent(): void
    {
        $this->insertOrder(3014000011, '2026-10-17 10:00:00');

        $summary = $this->step(new HttpResponse(200, '<html>Prihlaste sa</html>'))->run($this->checkRunId(), $this->now());

        self::assertSame([], $summary->sentOrderIds);
        self::assertSame('unknown', $this->statusOf(3014000011));
    }

    public function testMaxPerRunLimitsSentMails(): void
    {
        $this->insertOrder(3014000009, '2026-10-17 10:00:00');
        $this->insertOrder(3014000010, '2026-10-17 11:00:00');

        $summary = $this->step(new HttpResponse(200, 'ok'), max: 1)->run($this->checkRunId(), $this->now());

        self::assertCount(1, $summary->sentOrderIds);
        self::assertSame(1, $summary->skippedOverLimit);
    }

    private function statusOf(int $orderId): string
    {
        return (string) $this->pdo->query("SELECT status FROM payment_reminders_sent WHERE order_id = {$orderId}")->fetchColumn();
    }
}
