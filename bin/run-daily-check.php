<?php

declare(strict_types=1);

/**
 * Hlavny cron entrypoint - ma sa spustat raz denne o 03:00, napr.:
 *   0 3 * * * /usr/bin/php /cesta/k/projektu/bin/run-daily-check.php >> /cesta/k/projektu/var/logs/cron.log 2>&1
 *
 * Spusti: stiahnutie a import objednavkoveho feedu, stiahnutie a import Fio
 * pohybov, parovanie nezaplatenych objednavok s Fio, a odoslanie denneho
 * reportu (idempotentne - najviac raz za den). Zlyhanie jedneho kroku
 * nezastavi ostatne (viz DailyCheckRunner).
 *
 * Rovnaku logiku (cez DailyCheckRunnerFactory) spusta aj tlacidlo
 * "Spustit kontrolu" v administracii.
 */

require __DIR__ . '/../vendor/autoload.php';

use Noelo\Monitor\Bootstrap\DailyCheckRunnerFactory;
use Noelo\Monitor\Config\Env;
use Noelo\Monitor\Database\Connection;
use Noelo\Monitor\Reporting\ReportSendResult;

$env = new Env(__DIR__ . '/../.env');
$pdo = Connection::fromEnv($env);

$runner = DailyCheckRunnerFactory::create($env, $pdo);

$triggeredBy = ($argv[1] ?? 'cron') === 'manual' ? 'manual' : 'cron';
$result = $runner->run($triggeredBy, triggeredByUser: null, now: new DateTimeImmutable('today'));

function formatStatus(?bool $ok): string
{
    return match ($ok) {
        true => 'OK',
        false => 'CHYBA',
        null => 'nenakonfigurovane',
    };
}

echo "Kontrola dokoncena (check_run #{$result->checkRunId}).\n";
echo 'Objednavkovy feed: ' . formatStatus($result->orderFeedOk) . "\n";
echo 'Fio API: ' . formatStatus($result->fioOk) . "\n";
echo 'Report: ' . match ($result->reportResult) {
    ReportSendResult::SENT => 'odoslany',
    ReportSendResult::ALREADY_SENT_TODAY => 'uz odoslany skor dnes',
    null => 'neodoslany (nenakonfigurovany SMTP, alebo odoslanie zlyhalo - viz tabulka reports)',
} . "\n";

if ($result->reminderSummary === null) {
    echo "Automaticke pripomienky: vypnute alebo preskocene\n";
} elseif ($result->reminderSummary->dryRun) {
    echo 'Automaticke pripomienky (DRY-RUN, nic sa neposlalo): poslali by sa objednavkam ['
        . implode(', ', $result->reminderSummary->wouldSendOrderIds) . "]\n";
} else {
    echo 'Automaticke pripomienky: odoslane ' . count($result->reminderSummary->sentOrderIds)
        . ', zlyhalo ' . count($result->reminderSummary->failedOrderIds) . "\n";
}

exit($result->orderFeedOk === false || $result->fioOk === false ? 1 : 0);
