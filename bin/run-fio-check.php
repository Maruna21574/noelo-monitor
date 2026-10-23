<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Noelo\Monitor\Config\Env;
use Noelo\Monitor\Database\Connection;
use Noelo\Monitor\Fio\FioApiException;
use Noelo\Monitor\Fio\FioCheckStep;
use Noelo\Monitor\Fio\FioClient;
use Noelo\Monitor\Fio\FioTransactionImporter;
use Noelo\Monitor\Fio\FioTransactionRepository;
use Noelo\Monitor\Http\CurlHttpClient;
use Noelo\Monitor\Matching\PaymentMatcher;
use Noelo\Monitor\Reporting\CheckRunRepository;
use Noelo\Monitor\Reporting\FeedErrorRepository;
use Noelo\Monitor\Reporting\OrderRepository;
use Noelo\Monitor\Reporting\PaymentMatchingService;
use Noelo\Monitor\Reporting\PaymentMatchRepository;

$env = new Env(__DIR__ . '/../.env');
$pdo = Connection::fromEnv($env);

$checkRuns = new CheckRunRepository($pdo);
$feedErrors = new FeedErrorRepository($pdo);

$client = new FioClient(
    httpClient: new CurlHttpClient(),
    apiToken: $env->require('FIO_API_TOKEN'),
    timeoutSeconds: $env->getInt('FIO_TIMEOUT_SECONDS', 20),
    maxAttempts: $env->getInt('FIO_MAX_ATTEMPTS', 3),
);

$step = new FioCheckStep(
    client: $client,
    importer: new FioTransactionImporter($pdo),
    checkRuns: $checkRuns,
    feedErrors: $feedErrors,
    lookbackDays: $env->getInt('FIO_LOOKBACK_DAYS', 10),
);

$checkRunId = $checkRuns->start(triggeredBy: 'manual');

try {
    $imported = $step->run($checkRunId);
    echo "Fio import OK: {$imported} pohybov.\n";

    $matchingService = new PaymentMatchingService(
        new OrderRepository($pdo),
        new FioTransactionRepository($pdo),
        new PaymentMatcher(),
        new PaymentMatchRepository($pdo),
    );
    $candidatesFound = $matchingService->run();
    echo "Najdene kandidatske zhody na rucne overenie: {$candidatesFound}\n";
} catch (FioApiException $e) {
    fwrite(STDERR, "Kontrola Fio API zlyhala: {$e->getMessage()}\n");
    exit(1);
} finally {
    $checkRuns->finish($checkRunId);
}
