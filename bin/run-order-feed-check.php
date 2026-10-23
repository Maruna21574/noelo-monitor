<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Noelo\Monitor\Config\Env;
use Noelo\Monitor\Database\Connection;
use Noelo\Monitor\Http\CurlHttpClient;
use Noelo\Monitor\OrderFeed\OrderFeedCheckStep;
use Noelo\Monitor\OrderFeed\OrderFeedFetchException;
use Noelo\Monitor\OrderFeed\OrderFeedFetcher;
use Noelo\Monitor\OrderFeed\OrderFeedImporter;
use Noelo\Monitor\OrderFeed\OrderFeedParseException;
use Noelo\Monitor\OrderFeed\OrderFeedParser;
use Noelo\Monitor\Reporting\CheckRunRepository;
use Noelo\Monitor\Reporting\FeedErrorRepository;

$env = new Env(__DIR__ . '/../.env');
$pdo = Connection::fromEnv($env);

$checkRuns = new CheckRunRepository($pdo);
$feedErrors = new FeedErrorRepository($pdo);

$fetcher = new OrderFeedFetcher(
    httpClient: new CurlHttpClient(),
    url: $env->require('ORDER_FEED_URL'),
    timeoutSeconds: $env->getInt('ORDER_FEED_TIMEOUT_SECONDS', 15),
    maxAttempts: $env->getInt('ORDER_FEED_MAX_ATTEMPTS', 3),
    retryDelayMs: 1000,
    basicAuthUsername: $env->get('ORDER_FEED_USERNAME'),
    basicAuthPassword: $env->get('ORDER_FEED_PASSWORD'),
);

$step = new OrderFeedCheckStep(
    fetcher: $fetcher,
    parser: new OrderFeedParser(),
    importer: new OrderFeedImporter($pdo),
    checkRuns: $checkRuns,
    feedErrors: $feedErrors,
);

$checkRunId = $checkRuns->start(triggeredBy: 'manual');

try {
    $summary = $step->run($checkRunId);
    echo "Import OK: {$summary->ordersProcessed} objednavok, {$summary->itemsProcessed} poloziek.\n";
    if ($summary->skippedOrderErrors !== []) {
        echo "Preskocene objednavky: " . count($summary->skippedOrderErrors) . " (viac v tabulke feed_errors)\n";
    }
} catch (OrderFeedFetchException | OrderFeedParseException $e) {
    fwrite(STDERR, "Kontrola objednavkoveho feedu zlyhala: {$e->getMessage()}\n");
    exit(1);
} finally {
    $checkRuns->finish($checkRunId);
}
