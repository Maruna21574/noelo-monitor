<?php

declare(strict_types=1);

namespace Noelo\Monitor\Bootstrap;

use Noelo\Monitor\Config\Env;
use Noelo\Monitor\Fio\FioCheckStep;
use Noelo\Monitor\Fio\FioClient;
use Noelo\Monitor\Fio\FioTransactionImporter;
use Noelo\Monitor\Fio\FioTransactionRepository;
use Noelo\Monitor\Http\CurlHttpClient;
use Noelo\Monitor\Matching\PaymentMatcher;
use Noelo\Monitor\OrderFeed\OrderFeedCheckStep;
use Noelo\Monitor\OrderFeed\OrderFeedFetcher;
use Noelo\Monitor\OrderFeed\OrderFeedImporter;
use Noelo\Monitor\OrderFeed\OrderFeedParser;
use Noelo\Monitor\Reporting\CheckRunRepository;
use Noelo\Monitor\Reporting\DailyCheckRunner;
use Noelo\Monitor\Reporting\FeedErrorRepository;
use Noelo\Monitor\Reporting\OrderRepository;
use Noelo\Monitor\Reporting\PaymentMatchingService;
use Noelo\Monitor\Reporting\PaymentMatchRepository;
use Noelo\Monitor\Reporting\PaymentReminderLogRepository;
use Noelo\Monitor\Reporting\PaymentReminderStep;
use Noelo\Monitor\Rules\PaymentReminderRule;
use PDO;

/**
 * Zdielane zapojenie DailyCheckRunner - pouziva ho cron entrypoint
 * (bin/run-daily-check.php) aj tlacidlo "Spustit kontrolu" v administracii,
 * aby oba spustali presne tu istu logiku.
 */
final class DailyCheckRunnerFactory
{
    public static function create(Env $env, PDO $pdo): DailyCheckRunner
    {
        $checkRuns = new CheckRunRepository($pdo);
        $feedErrors = new FeedErrorRepository($pdo);

        // Kazda integracia je nezavisle volitelna - chybajuca URL/token v .env
        // znamena "tento zdroj este nie je nakonfigurovany", nie fatalnu chybu
        // ktora by mala zhodit cely beh (viz DailyCheckRunner).
        $orderFeedUrl = $env->get('ORDER_FEED_URL');
        $orderFeedStep = $orderFeedUrl === null ? null : new OrderFeedCheckStep(
            fetcher: new OrderFeedFetcher(
                httpClient: new CurlHttpClient(),
                url: $orderFeedUrl,
                timeoutSeconds: $env->getInt('ORDER_FEED_TIMEOUT_SECONDS', 15),
                maxAttempts: $env->getInt('ORDER_FEED_MAX_ATTEMPTS', 3),
                basicAuthUsername: $env->get('ORDER_FEED_USERNAME'),
                basicAuthPassword: $env->get('ORDER_FEED_PASSWORD'),
            ),
            parser: new OrderFeedParser(),
            importer: new OrderFeedImporter($pdo),
            checkRuns: $checkRuns,
            feedErrors: $feedErrors,
        );

        $fioApiToken = $env->get('FIO_API_TOKEN');
        $fioStep = $fioApiToken === null ? null : new FioCheckStep(
            client: new FioClient(
                httpClient: new CurlHttpClient(),
                apiToken: $fioApiToken,
                timeoutSeconds: $env->getInt('FIO_TIMEOUT_SECONDS', 20),
                maxAttempts: $env->getInt('FIO_MAX_ATTEMPTS', 3),
            ),
            importer: new FioTransactionImporter($pdo),
            checkRuns: $checkRuns,
            feedErrors: $feedErrors,
            lookbackDays: $env->getInt('FIO_LOOKBACK_DAYS', 10),
        );

        $paymentMatchingService = new PaymentMatchingService(
            new OrderRepository($pdo),
            new FioTransactionRepository($pdo),
            new PaymentMatcher(),
            new PaymentMatchRepository($pdo),
        );

        // E-mailovy report je tiez volitelny - kym nemame SMTP udaje a prijemcov,
        // appka sa aj tak da pouzivat (dashboard uz report ukazuje naziv), len sa
        // nic neposiela.
        $reportSender = ReportSenderFactory::create($env, $pdo);

        // Automaticke pripomienky nezaplatenia posielaju maily realnym zakaznikom, preto su
        // predvolene vypnute: PAYMENT_REMINDER_MODE = off (default) | dry-run | live.
        $reminderMode = $env->get('PAYMENT_REMINDER_MODE', 'off');
        $reminderUrl = $env->get('PAYMENT_REMINDER_URL');
        $paymentReminderStep = ($reminderUrl === null || !in_array($reminderMode, ['dry-run', 'live'], true)) ? null : new PaymentReminderStep(
            httpClient: new CurlHttpClient(),
            orderRepository: new OrderRepository($pdo),
            paymentMatchRepository: new PaymentMatchRepository($pdo),
            log: new PaymentReminderLogRepository($pdo),
            rule: new PaymentReminderRule(),
            feedErrors: $feedErrors,
            urlTemplate: $reminderUrl,
            dryRun: $reminderMode !== 'live',
            shopIdPrefix: $env->get('PAYMENT_REMINDER_ID_PREFIX', '3014'),
            maxPerRun: $env->getInt('PAYMENT_REMINDER_MAX_PER_RUN', 20),
            timeoutSeconds: $env->getInt('PAYMENT_REMINDER_TIMEOUT_SECONDS', 20),
            basicAuthUsername: $env->get('PAYMENT_REMINDER_BASIC_USERNAME'),
            basicAuthPassword: $env->get('PAYMENT_REMINDER_BASIC_PASSWORD'),
        );

        return new DailyCheckRunner($checkRuns, $orderFeedStep, $fioStep, $paymentMatchingService, $reportSender, $paymentReminderStep);
    }
}
