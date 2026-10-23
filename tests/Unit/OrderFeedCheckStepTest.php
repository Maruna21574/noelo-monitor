<?php

declare(strict_types=1);

namespace Noelo\Monitor\Tests\Unit;

use Noelo\Monitor\Http\HttpResponse;
use Noelo\Monitor\Http\HttpTransportException;
use Noelo\Monitor\OrderFeed\OrderFeedCheckStep;
use Noelo\Monitor\OrderFeed\OrderFeedFetchException;
use Noelo\Monitor\OrderFeed\OrderFeedFetcher;
use Noelo\Monitor\OrderFeed\OrderFeedImporter;
use Noelo\Monitor\OrderFeed\OrderFeedParseException;
use Noelo\Monitor\OrderFeed\OrderFeedParser;
use Noelo\Monitor\Reporting\CheckRunRepository;
use Noelo\Monitor\Reporting\FeedErrorRepository;
use Noelo\Monitor\Tests\Support\DatabaseTestCase;
use Noelo\Monitor\Tests\Unit\Fakes\FakeHttpClient;

final class OrderFeedCheckStepTest extends DatabaseTestCase
{
    private function fixture(string $name): string
    {
        return file_get_contents(__DIR__ . '/../Fixtures/' . $name);
    }

    private function buildStep(FakeHttpClient $client): array
    {
        $checkRuns = new CheckRunRepository($this->pdo);
        $feedErrors = new FeedErrorRepository($this->pdo);
        $fetcher = new OrderFeedFetcher($client, 'https://example.test/feed.xml', retryDelayMs: 0);

        $step = new OrderFeedCheckStep(
            fetcher: $fetcher,
            parser: new OrderFeedParser(),
            importer: new OrderFeedImporter($this->pdo),
            checkRuns: $checkRuns,
            feedErrors: $feedErrors,
        );

        return [$step, $checkRuns];
    }

    public function testSuccessfulRunMarksOrderFeedStatusOk(): void
    {
        $client = new FakeHttpClient([new HttpResponse(200, $this->fixture('orders_valid.xml'))]);
        [$step, $checkRuns] = $this->buildStep($client);

        $checkRunId = $checkRuns->start('manual');
        $summary = $step->run($checkRunId);

        self::assertSame(2, $summary->ordersProcessed);

        $status = $this->pdo->query("SELECT order_feed_status FROM check_runs WHERE id = {$checkRunId}")->fetch()['order_feed_status'];
        self::assertSame('ok', $status);

        $errorCount = (int) $this->pdo->query('SELECT COUNT(*) AS c FROM feed_errors')->fetch()['c'];
        self::assertSame(0, $errorCount);
    }

    public function testUnreachableFeedMarksStatusErrorAndRecordsSanitizedFeedError(): void
    {
        $client = new FakeHttpClient([
            new HttpTransportException('curl errno 28'),
            new HttpTransportException('curl errno 28'),
            new HttpTransportException('curl errno 28'),
        ]);
        [$step, $checkRuns] = $this->buildStep($client);

        $checkRunId = $checkRuns->start('manual');

        $this->expectException(OrderFeedFetchException::class);
        try {
            $step->run($checkRunId);
        } finally {
            $status = $this->pdo->query("SELECT order_feed_status FROM check_runs WHERE id = {$checkRunId}")->fetch()['order_feed_status'];
            self::assertSame('error', $status);

            $error = $this->pdo->query('SELECT message FROM feed_errors WHERE feed_type = "order_feed"')->fetch();
            self::assertNotFalse($error);
            self::assertStringNotContainsString('example.test', $error['message']);
        }
    }

    public function testMalformedFeedMarksStatusErrorWithoutImportingAnything(): void
    {
        $client = new FakeHttpClient([new HttpResponse(200, $this->fixture('orders_malformed.xml'))]);
        [$step, $checkRuns] = $this->buildStep($client);

        $checkRunId = $checkRuns->start('manual');

        try {
            $step->run($checkRunId);
            self::fail('Ocakavana OrderFeedParseException nebola vyhodena.');
        } catch (OrderFeedParseException) {
            // ocakavane
        }

        $status = $this->pdo->query("SELECT order_feed_status FROM check_runs WHERE id = {$checkRunId}")->fetch()['order_feed_status'];
        self::assertSame('error', $status);

        $orderCount = (int) $this->pdo->query('SELECT COUNT(*) AS c FROM orders')->fetch()['c'];
        self::assertSame(0, $orderCount);
    }

    public function testOrderWithMissingFieldIsLoggedButRestOfFeedImports(): void
    {
        $client = new FakeHttpClient([new HttpResponse(200, $this->fixture('orders_missing_field.xml'))]);
        [$step, $checkRuns] = $this->buildStep($client);

        $checkRunId = $checkRuns->start('manual');
        $summary = $step->run($checkRunId);

        self::assertSame(1, $summary->ordersProcessed);

        $status = $this->pdo->query("SELECT order_feed_status FROM check_runs WHERE id = {$checkRunId}")->fetch()['order_feed_status'];
        self::assertSame('ok', $status);

        $errorCount = (int) $this->pdo->query('SELECT COUNT(*) AS c FROM feed_errors')->fetch()['c'];
        self::assertSame(1, $errorCount);
    }
}
