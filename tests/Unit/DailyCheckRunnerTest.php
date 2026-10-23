<?php

declare(strict_types=1);

namespace Noelo\Monitor\Tests\Unit;

use Noelo\Monitor\Fio\FioCheckStep;
use Noelo\Monitor\Fio\FioClient;
use Noelo\Monitor\Fio\FioTransactionImporter;
use Noelo\Monitor\Fio\FioTransactionRepository;
use Noelo\Monitor\Http\HttpResponse;
use Noelo\Monitor\Http\HttpTransportException;
use Noelo\Monitor\Mail\Mailer;
use Noelo\Monitor\Matching\PaymentMatcher;
use Noelo\Monitor\OrderFeed\OrderFeedCheckStep;
use Noelo\Monitor\OrderFeed\OrderFeedFetcher;
use Noelo\Monitor\OrderFeed\OrderFeedImporter;
use Noelo\Monitor\OrderFeed\OrderFeedParser;
use Noelo\Monitor\Reporting\CheckRunRepository;
use Noelo\Monitor\Reporting\DailyCheckRunner;
use Noelo\Monitor\Reporting\DailyReportSender;
use Noelo\Monitor\Reporting\FeedErrorRepository;
use Noelo\Monitor\Reporting\OrderRepository;
use Noelo\Monitor\Reporting\PaymentMatchingService;
use Noelo\Monitor\Reporting\PaymentMatchRepository;
use Noelo\Monitor\Reporting\ReportBuilder;
use Noelo\Monitor\Reporting\ReportRepository;
use Noelo\Monitor\Reporting\ReportSendResult;
use Noelo\Monitor\Reporting\ReportTextRenderer;
use Noelo\Monitor\Rules\CancellationSuggestionRule;
use Noelo\Monitor\Rules\OrderAvailabilityEvaluator;
use Noelo\Monitor\Rules\OrderShippabilityEvaluator;
use Noelo\Monitor\Rules\PaymentReminderRule;
use Noelo\Monitor\Rules\ReservationConflictDetector;
use Noelo\Monitor\Tests\Support\DatabaseTestCase;
use Noelo\Monitor\Tests\Unit\Fakes\FakeHttpClient;
use Noelo\Monitor\Tests\Unit\Fakes\FakeMailTransport;

final class DailyCheckRunnerTest extends DatabaseTestCase
{
    private function validOrdersXml(): string
    {
        return file_get_contents(__DIR__ . '/../Fixtures/orders_valid.xml');
    }

    private function emptyFioJson(): string
    {
        return json_encode(['accountStatement' => ['transactionList' => ['transaction' => []]]], JSON_THROW_ON_ERROR);
    }

    private function buildRunner(
        FakeHttpClient $orderFeedHttp,
        FakeHttpClient $fioHttp,
        FakeMailTransport $mailTransport,
    ): DailyCheckRunner {
        $checkRuns = new CheckRunRepository($this->pdo);
        $feedErrors = new FeedErrorRepository($this->pdo);

        $orderFeedStep = new OrderFeedCheckStep(
            fetcher: new OrderFeedFetcher($orderFeedHttp, 'https://example.test/orders.xml', retryDelayMs: 0),
            parser: new OrderFeedParser(),
            importer: new OrderFeedImporter($this->pdo),
            checkRuns: $checkRuns,
            feedErrors: $feedErrors,
        );

        $fioStep = new FioCheckStep(
            client: new FioClient($fioHttp, 'FAKE_TOKEN', retryDelayMs: 0),
            importer: new FioTransactionImporter($this->pdo),
            checkRuns: $checkRuns,
            feedErrors: $feedErrors,
        );

        $paymentMatchingService = new PaymentMatchingService(
            new OrderRepository($this->pdo),
            new FioTransactionRepository($this->pdo),
            new PaymentMatcher(),
            new PaymentMatchRepository($this->pdo),
        );

        $availability = new OrderAvailabilityEvaluator();
        $reportBuilder = new ReportBuilder(
            orderRepository: new OrderRepository($this->pdo),
            shippabilityEvaluator: new OrderShippabilityEvaluator($availability),
            reservationConflictDetector: new ReservationConflictDetector(),
            paymentMatchRepository: new PaymentMatchRepository($this->pdo),
            feedErrorRepository: $feedErrors,
            paymentReminderRule: new PaymentReminderRule(),
            cancellationSuggestionRule: new CancellationSuggestionRule(),
        );

        $reportSender = new DailyReportSender(
            reportBuilder: $reportBuilder,
            reportRepository: new ReportRepository($this->pdo),
            renderer: new ReportTextRenderer(),
            mailer: new Mailer($mailTransport, 'noreply@noelo.sk', 'Noelo Monitor'),
            recipients: ['admin@example.com'],
        );

        return new DailyCheckRunner($checkRuns, $orderFeedStep, $fioStep, $paymentMatchingService, $reportSender);
    }

    public function testFullSuccessfulRunImportsOrdersAndSendsReport(): void
    {
        $runner = $this->buildRunner(
            new FakeHttpClient([new HttpResponse(200, $this->validOrdersXml())]),
            new FakeHttpClient([new HttpResponse(200, $this->emptyFioJson())]),
            new FakeMailTransport(),
        );

        $result = $runner->run('cron', null, new \DateTimeImmutable('2026-09-17'));

        self::assertTrue($result->orderFeedOk);
        self::assertTrue($result->fioOk);
        self::assertSame(ReportSendResult::SENT, $result->reportResult);

        $orderCount = (int) $this->pdo->query('SELECT COUNT(*) AS c FROM orders')->fetch()['c'];
        self::assertSame(2, $orderCount);

        $finishedAt = $this->pdo->query("SELECT finished_at FROM check_runs WHERE id = {$result->checkRunId}")->fetch()['finished_at'];
        self::assertNotNull($finishedAt);
    }

    public function testOrderFeedFailureDoesNotPreventFioCheckOrReportFromRunning(): void
    {
        $runner = $this->buildRunner(
            new FakeHttpClient([
                new HttpTransportException('curl errno 28'),
                new HttpTransportException('curl errno 28'),
                new HttpTransportException('curl errno 28'),
            ]),
            new FakeHttpClient([new HttpResponse(200, $this->emptyFioJson())]),
            new FakeMailTransport(),
        );

        $result = $runner->run('cron', null, new \DateTimeImmutable('2026-09-17'));

        self::assertFalse($result->orderFeedOk);
        self::assertTrue($result->fioOk);
        self::assertSame(ReportSendResult::SENT, $result->reportResult);

        $errorCount = (int) $this->pdo->query("SELECT COUNT(*) AS c FROM feed_errors WHERE feed_type = 'order_feed'")->fetch()['c'];
        self::assertSame(1, $errorCount);
    }

    public function testFioFailureSkipsPaymentMatchingButStillSendsReport(): void
    {
        $runner = $this->buildRunner(
            new FakeHttpClient([new HttpResponse(200, $this->validOrdersXml())]),
            new FakeHttpClient([
                new HttpTransportException('curl errno 28'),
                new HttpTransportException('curl errno 28'),
                new HttpTransportException('curl errno 28'),
            ]),
            new FakeMailTransport(),
        );

        $result = $runner->run('cron', null, new \DateTimeImmutable('2026-09-17'));

        self::assertTrue($result->orderFeedOk);
        self::assertFalse($result->fioOk);
        self::assertSame(ReportSendResult::SENT, $result->reportResult);
    }

    public function testSmtpFailureStillFinishesCheckRunWithoutThrowing(): void
    {
        $runner = $this->buildRunner(
            new FakeHttpClient([new HttpResponse(200, $this->validOrdersXml())]),
            new FakeHttpClient([new HttpResponse(200, $this->emptyFioJson())]),
            new FakeMailTransport(shouldFail: true),
        );

        $result = $runner->run('cron', null, new \DateTimeImmutable('2026-09-17'));

        self::assertTrue($result->orderFeedOk);
        self::assertTrue($result->fioOk);
        self::assertNull($result->reportResult);

        $reportStatus = $this->pdo->query("SELECT status FROM reports WHERE run_date = '2026-09-17'")->fetch()['status'];
        self::assertSame('failed', $reportStatus);

        $finishedAt = $this->pdo->query("SELECT finished_at FROM check_runs WHERE id = {$result->checkRunId}")->fetch()['finished_at'];
        self::assertNotNull($finishedAt);
    }

    public function testMissingOrderFeedConfigurationSkipsThatStepButOthersStillRun(): void
    {
        $checkRuns = new CheckRunRepository($this->pdo);
        $feedErrors = new FeedErrorRepository($this->pdo);

        $fioStep = new FioCheckStep(
            client: new FioClient(new FakeHttpClient([new HttpResponse(200, $this->emptyFioJson())]), 'FAKE_TOKEN', retryDelayMs: 0),
            importer: new FioTransactionImporter($this->pdo),
            checkRuns: $checkRuns,
            feedErrors: $feedErrors,
        );

        $availability = new OrderAvailabilityEvaluator();
        $reportSender = new DailyReportSender(
            reportBuilder: new ReportBuilder(
                orderRepository: new OrderRepository($this->pdo),
                shippabilityEvaluator: new OrderShippabilityEvaluator($availability),
                reservationConflictDetector: new ReservationConflictDetector(),
                paymentMatchRepository: new PaymentMatchRepository($this->pdo),
                feedErrorRepository: $feedErrors,
                paymentReminderRule: new PaymentReminderRule(),
                cancellationSuggestionRule: new CancellationSuggestionRule(),
            ),
            reportRepository: new ReportRepository($this->pdo),
            renderer: new ReportTextRenderer(),
            mailer: new Mailer(new FakeMailTransport(), 'noreply@noelo.sk', 'Noelo Monitor'),
            recipients: ['admin@example.com'],
        );

        $runner = new DailyCheckRunner(
            $checkRuns,
            orderFeedStep: null,
            fioStep: $fioStep,
            paymentMatchingService: new PaymentMatchingService(
                new OrderRepository($this->pdo),
                new FioTransactionRepository($this->pdo),
                new PaymentMatcher(),
                new PaymentMatchRepository($this->pdo),
            ),
            reportSender: $reportSender,
        );

        $result = $runner->run('cron', null, new \DateTimeImmutable('2026-09-17'));

        self::assertNull($result->orderFeedOk);
        self::assertTrue($result->fioOk);
        self::assertSame(ReportSendResult::SENT, $result->reportResult);

        $errorCount = (int) $this->pdo->query("SELECT COUNT(*) AS c FROM feed_errors WHERE feed_type = 'order_feed'")->fetch()['c'];
        self::assertSame(0, $errorCount, 'Chybajuca konfiguracia sa nema zaznamenavat ako technicka chyba feedu.');
    }

    public function testMissingReportSenderConfigurationSkipsEmailWithoutFailing(): void
    {
        $runner = $this->buildRunner(
            new FakeHttpClient([new HttpResponse(200, $this->validOrdersXml())]),
            new FakeHttpClient([new HttpResponse(200, $this->emptyFioJson())]),
            new FakeMailTransport(),
        );

        $checkRuns = new CheckRunRepository($this->pdo);
        $feedErrors = new FeedErrorRepository($this->pdo);
        $runnerWithoutMail = new DailyCheckRunner(
            $checkRuns,
            orderFeedStep: new OrderFeedCheckStep(
                fetcher: new OrderFeedFetcher(new FakeHttpClient([new HttpResponse(200, $this->validOrdersXml())]), 'https://example.test', retryDelayMs: 0),
                parser: new OrderFeedParser(),
                importer: new OrderFeedImporter($this->pdo),
                checkRuns: $checkRuns,
                feedErrors: $feedErrors,
            ),
            fioStep: null,
            paymentMatchingService: new PaymentMatchingService(
                new OrderRepository($this->pdo),
                new FioTransactionRepository($this->pdo),
                new PaymentMatcher(),
                new PaymentMatchRepository($this->pdo),
            ),
            reportSender: null,
        );

        $result = $runnerWithoutMail->run('cron', null, new \DateTimeImmutable('2026-09-17'));

        self::assertTrue($result->orderFeedOk);
        self::assertNull($result->fioOk);
        self::assertNull($result->reportResult);
    }
}
