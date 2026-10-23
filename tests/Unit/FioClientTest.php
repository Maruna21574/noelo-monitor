<?php

declare(strict_types=1);

namespace Noelo\Monitor\Tests\Unit;

use Noelo\Monitor\Fio\FioApiException;
use Noelo\Monitor\Fio\FioClient;
use Noelo\Monitor\Http\HttpResponse;
use Noelo\Monitor\Http\HttpTransportException;
use Noelo\Monitor\Tests\Unit\Fakes\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class FioClientTest extends TestCase
{
    private function sampleJson(): string
    {
        return json_encode([
            'accountStatement' => [
                'info' => ['accountId' => '1234567890', 'currency' => 'EUR'],
                'transactionList' => [
                    'transaction' => [
                        [
                            'column22' => ['value' => 100000001, 'name' => 'ID pohybu', 'id' => 22],
                            'column0' => ['value' => '2026-09-10+0200', 'name' => 'Datum', 'id' => 0],
                            'column1' => ['value' => 44.25, 'name' => 'Objem', 'id' => 1],
                            'column14' => ['value' => 'EUR', 'name' => 'Měna', 'id' => 14],
                            'column5' => ['value' => '3014234346', 'name' => 'VS', 'id' => 5],
                            'column10' => ['value' => 'Test Zakaznik', 'name' => 'Název protiúčtu', 'id' => 10],
                            'column16' => null,
                            'column7' => null,
                            'column25' => null,
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    public function testMapsFioColumnsToTransactionDto(): void
    {
        $client = new FioClient(new FakeHttpClient([new HttpResponse(200, $this->sampleJson())]), 'FAKE_TOKEN', retryDelayMs: 0);

        $transactions = $client->fetchTransactions(new \DateTimeImmutable('2026-09-01'), new \DateTimeImmutable('2026-09-17'));

        self::assertCount(1, $transactions);
        $t = $transactions[0];
        self::assertSame(100000001, $t->id);
        self::assertSame('2026-09-10', $t->date->format('Y-m-d'));
        self::assertSame(4425, $t->amountCents);
        self::assertSame('EUR', $t->currency);
        self::assertSame('3014234346', $t->variableSymbol);
        self::assertSame('Test Zakaznik', $t->counterAccountName);
        self::assertNull($t->messageForRecipient);
    }

    public function testRetriesOnTransportFailureThenSucceeds(): void
    {
        $client = new FioClient(
            new FakeHttpClient([new HttpTransportException('curl errno 28'), new HttpResponse(200, $this->sampleJson())]),
            'FAKE_TOKEN',
            retryDelayMs: 0
        );

        $transactions = $client->fetchTransactions(new \DateTimeImmutable('2026-09-01'), new \DateTimeImmutable('2026-09-17'));
        self::assertCount(1, $transactions);
    }

    public function testThrowsFioApiExceptionAfterExhaustingRetriesWithoutLeakingToken(): void
    {
        $client = new FioClient(
            new FakeHttpClient([
                new HttpTransportException('curl errno 28'),
                new HttpTransportException('curl errno 28'),
                new HttpTransportException('curl errno 28'),
            ]),
            'SUPER_SECRET_TOKEN',
            maxAttempts: 3,
            retryDelayMs: 0
        );

        try {
            $client->fetchTransactions(new \DateTimeImmutable('2026-09-01'), new \DateTimeImmutable('2026-09-17'));
            self::fail('Ocakavana FioApiException nebola vyhodena.');
        } catch (FioApiException $e) {
            self::assertStringNotContainsString('SUPER_SECRET_TOKEN', $e->getMessage());
        }
    }

    public function testThrowsOnInvalidJsonResponse(): void
    {
        $client = new FioClient(new FakeHttpClient([new HttpResponse(200, 'not-json')]), 'FAKE_TOKEN', retryDelayMs: 0);

        $this->expectException(FioApiException::class);
        $client->fetchTransactions(new \DateTimeImmutable('2026-09-01'), new \DateTimeImmutable('2026-09-17'));
    }
}
