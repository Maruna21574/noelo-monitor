<?php

declare(strict_types=1);

namespace Noelo\Monitor\Tests\Unit;

use Noelo\Monitor\Fio\FioApiException;
use Noelo\Monitor\Fio\FioCheckStep;
use Noelo\Monitor\Fio\FioClient;
use Noelo\Monitor\Fio\FioTransactionImporter;
use Noelo\Monitor\Http\HttpResponse;
use Noelo\Monitor\Http\HttpTransportException;
use Noelo\Monitor\Reporting\CheckRunRepository;
use Noelo\Monitor\Reporting\FeedErrorRepository;
use Noelo\Monitor\Tests\Support\DatabaseTestCase;
use Noelo\Monitor\Tests\Unit\Fakes\FakeHttpClient;

final class FioCheckStepTest extends DatabaseTestCase
{
    public function testSuccessfulRunMarksFioStatusOkAndImportsTransactions(): void
    {
        $json = json_encode([
            'accountStatement' => [
                'transactionList' => [
                    'transaction' => [
                        [
                            'column22' => ['value' => 555, 'id' => 22],
                            'column0' => ['value' => '2026-09-10+0200', 'id' => 0],
                            'column1' => ['value' => 10.0, 'id' => 1],
                            'column14' => ['value' => 'EUR', 'id' => 14],
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $client = new FioClient(new FakeHttpClient([new HttpResponse(200, $json)]), 'FAKE_TOKEN', retryDelayMs: 0);
        $checkRuns = new CheckRunRepository($this->pdo);
        $step = new FioCheckStep($client, new FioTransactionImporter($this->pdo), $checkRuns, new FeedErrorRepository($this->pdo));

        $checkRunId = $checkRuns->start('manual');
        $imported = $step->run($checkRunId, new \DateTimeImmutable('2026-09-17'));

        self::assertSame(1, $imported);
        $status = $this->pdo->query("SELECT fio_status FROM check_runs WHERE id = {$checkRunId}")->fetch()['fio_status'];
        self::assertSame('ok', $status);
    }

    public function testUnreachableApiMarksFioStatusErrorAndRecordsSanitizedFeedError(): void
    {
        $token = 'SUPER_SECRET_TOKEN_XYZ';
        $client = new FioClient(
            new FakeHttpClient([
                new HttpTransportException('curl errno 28'),
                new HttpTransportException('curl errno 28'),
                new HttpTransportException('curl errno 28'),
            ]),
            $token,
            maxAttempts: 3,
            retryDelayMs: 0
        );
        $checkRuns = new CheckRunRepository($this->pdo);
        $feedErrors = new FeedErrorRepository($this->pdo);
        $step = new FioCheckStep($client, new FioTransactionImporter($this->pdo), $checkRuns, $feedErrors);

        $checkRunId = $checkRuns->start('manual');

        try {
            $step->run($checkRunId, new \DateTimeImmutable('2026-09-17'));
            self::fail('Ocakavana FioApiException nebola vyhodena.');
        } catch (FioApiException) {
            // ocakavane
        }

        $status = $this->pdo->query("SELECT fio_status FROM check_runs WHERE id = {$checkRunId}")->fetch()['fio_status'];
        self::assertSame('error', $status);

        $error = $this->pdo->query("SELECT message FROM feed_errors WHERE feed_type = 'fio_api'")->fetch();
        self::assertNotFalse($error);
        self::assertStringNotContainsString($token, $error['message']);
    }
}
