<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Noelo\Monitor\Bootstrap\ReportBuilderFactory;
use Noelo\Monitor\Config\Env;
use Noelo\Monitor\Database\Connection;
use Noelo\Monitor\Mail\Mailer;
use Noelo\Monitor\Mail\MailerException;
use Noelo\Monitor\Mail\PhpMailerTransport;
use Noelo\Monitor\Reporting\CheckRunRepository;
use Noelo\Monitor\Reporting\DailyReportSender;
use Noelo\Monitor\Reporting\ReportRepository;
use Noelo\Monitor\Reporting\ReportSendResult;
use Noelo\Monitor\Reporting\ReportTextRenderer;

$env = new Env(__DIR__ . '/../.env');
$pdo = Connection::fromEnv($env);

$checkRuns = new CheckRunRepository($pdo);
$checkRunId = $checkRuns->start(triggeredBy: 'manual');

$reportBuilder = ReportBuilderFactory::create($pdo);

$recipients = array_filter(array_map('trim', explode(',', $env->require('REPORT_EMAIL_RECIPIENTS'))));

$mailer = new Mailer(
    transport: new PhpMailerTransport(
        smtpHost: $env->require('SMTP_HOST'),
        smtpPort: $env->getInt('SMTP_PORT', 587),
        smtpUsername: $env->require('SMTP_USERNAME'),
        smtpPassword: $env->require('SMTP_PASSWORD'),
        smtpEncryption: $env->get('SMTP_ENCRYPTION', 'tls'),
    ),
    fromAddress: $env->require('MAIL_FROM_ADDRESS'),
    fromName: $env->get('MAIL_FROM_NAME', 'Noelo Monitor'),
);

$sender = new DailyReportSender(
    reportBuilder: $reportBuilder,
    reportRepository: new ReportRepository($pdo),
    renderer: new ReportTextRenderer(),
    mailer: $mailer,
    recipients: $recipients,
);

try {
    $result = $sender->send(new DateTimeImmutable('today'), $checkRunId);
    echo $result === ReportSendResult::SENT
        ? "Denny report bol odoslany.\n"
        : "Denny report za dnesok uz bol odoslany skor, znova sa neposiela.\n";
} catch (MailerException $e) {
    fwrite(STDERR, "Odoslanie reportu zlyhalo: {$e->getMessage()}\n");
    exit(1);
} finally {
    $checkRuns->finish($checkRunId);
}
