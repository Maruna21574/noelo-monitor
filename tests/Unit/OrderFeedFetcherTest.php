<?php

declare(strict_types=1);

namespace Noelo\Monitor\Tests\Unit;

use Noelo\Monitor\Http\HttpResponse;
use Noelo\Monitor\Http\HttpTransportException;
use Noelo\Monitor\OrderFeed\OrderFeedFetchException;
use Noelo\Monitor\OrderFeed\OrderFeedFetcher;
use Noelo\Monitor\Tests\Unit\Fakes\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class OrderFeedFetcherTest extends TestCase
{
    public function testReturnsBodyOnFirstSuccessfulAttempt(): void
    {
        $client = new FakeHttpClient([new HttpResponse(200, '<orders></orders>')]);
        $fetcher = new OrderFeedFetcher($client, 'https://example.test/feed.xml', retryDelayMs: 0);

        self::assertSame('<orders></orders>', $fetcher->fetch());
        self::assertSame(1, $client->callCount());
    }

    public function testRetriesOnTransportFailureThenSucceeds(): void
    {
        $client = new FakeHttpClient([
            new HttpTransportException('curl errno 28'),
            new HttpResponse(200, '<orders></orders>'),
        ]);
        $fetcher = new OrderFeedFetcher($client, 'https://example.test/feed.xml', retryDelayMs: 0);

        self::assertSame('<orders></orders>', $fetcher->fetch());
        self::assertSame(2, $client->callCount());
    }

    public function testRetriesOnNon200StatusThenSucceeds(): void
    {
        $client = new FakeHttpClient([
            new HttpResponse(503, 'Service Unavailable'),
            new HttpResponse(200, '<orders></orders>'),
        ]);
        $fetcher = new OrderFeedFetcher($client, 'https://example.test/feed.xml', retryDelayMs: 0);

        self::assertSame('<orders></orders>', $fetcher->fetch());
        self::assertSame(2, $client->callCount());
    }

    public function testThrowsAfterExhaustingAllAttempts(): void
    {
        $client = new FakeHttpClient([
            new HttpTransportException('curl errno 28'),
            new HttpTransportException('curl errno 28'),
            new HttpTransportException('curl errno 28'),
        ]);
        $fetcher = new OrderFeedFetcher($client, 'https://example.test/feed.xml', maxAttempts: 3, retryDelayMs: 0);

        $this->expectException(OrderFeedFetchException::class);
        $fetcher->fetch();
    }

    public function testFetchExceptionDoesNotLeakUrl(): void
    {
        $client = new FakeHttpClient([new HttpTransportException('curl errno 28')]);
        $fetcher = new OrderFeedFetcher(
            $client,
            'https://example.test/feed.xml?token=SECRET123',
            maxAttempts: 1,
            retryDelayMs: 0
        );

        try {
            $fetcher->fetch();
            self::fail('Ocakavana vynimka nebola vyhodena.');
        } catch (OrderFeedFetchException $e) {
            self::assertStringNotContainsString('SECRET123', $e->getMessage());
            self::assertStringNotContainsString('example.test', $e->getMessage());
        }
    }

    public function testBasicAuthCredentialsArePassedThroughToHttpClient(): void
    {
        $client = new FakeHttpClient([new HttpResponse(200, '<orders></orders>')]);
        $fetcher = new OrderFeedFetcher(
            $client,
            'https://example.test/feed.xml',
            retryDelayMs: 0,
            basicAuthUsername: 'ecurefeed',
            basicAuthPassword: 'super-secret',
        );

        $fetcher->fetch();

        self::assertSame('ecurefeed', $client->lastBasicAuthUsername());
        self::assertSame('super-secret', $client->lastBasicAuthPassword());
    }

    public function testFetchExceptionNeverLeaksBasicAuthPassword(): void
    {
        $client = new FakeHttpClient([new HttpTransportException('curl errno 28')]);
        $fetcher = new OrderFeedFetcher(
            $client,
            'https://example.test/feed.xml',
            maxAttempts: 1,
            retryDelayMs: 0,
            basicAuthUsername: 'ecurefeed',
            basicAuthPassword: 'super-secret',
        );

        try {
            $fetcher->fetch();
            self::fail('Ocakavana vynimka nebola vyhodena.');
        } catch (OrderFeedFetchException $e) {
            self::assertStringNotContainsString('super-secret', $e->getMessage());
        }
    }
}
