<?php

declare(strict_types=1);

namespace Noelo\Monitor\Bootstrap;

use Noelo\Monitor\Config\Env;
use Noelo\Monitor\Mail\Mailer;
use Noelo\Monitor\Mail\PhpMailerTransport;
use Noelo\Monitor\Reporting\DailyReportSender;
use Noelo\Monitor\Reporting\ReportRepository;
use Noelo\Monitor\Reporting\ReportTextRenderer;
use PDO;

/**
 * Zdielane zapojenie DailyReportSender - pouziva ho cron beh aj tlacidlo
 * "Poslat e-mail znova" v administracii. Vracia null, kym SMTP/prijemcovia
 * nie su nakonfigurovani (nie je to chyba, len appka e-maily zatial neposiela).
 */
final class ReportSenderFactory
{
    public static function create(Env $env, PDO $pdo): ?DailyReportSender
    {
        $smtpHost = $env->get('SMTP_HOST');
        $recipientsRaw = $env->get('REPORT_EMAIL_RECIPIENTS');
        $recipients = $recipientsRaw === null
            ? []
            : array_values(array_filter(array_map('trim', explode(',', $recipientsRaw))));

        if ($smtpHost === null || $recipients === []) {
            return null;
        }

        return new DailyReportSender(
            reportBuilder: ReportBuilderFactory::create($pdo),
            reportRepository: new ReportRepository($pdo),
            renderer: new ReportTextRenderer($env->get('APP_URL')),
            mailer: new Mailer(
                transport: new PhpMailerTransport(
                    smtpHost: $smtpHost,
                    smtpPort: $env->getInt('SMTP_PORT', 587),
                    smtpUsername: $env->require('SMTP_USERNAME'),
                    smtpPassword: $env->require('SMTP_PASSWORD'),
                    smtpEncryption: $env->get('SMTP_ENCRYPTION', 'tls'),
                ),
                fromAddress: $env->require('MAIL_FROM_ADDRESS'),
                fromName: $env->get('MAIL_FROM_NAME', 'Noelo Monitor'),
            ),
            recipients: $recipients,
        );
    }
}
