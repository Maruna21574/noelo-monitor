<?php

declare(strict_types=1);

namespace Noelo\Monitor\Bootstrap;

use Noelo\Monitor\Config\Env;
use Noelo\Monitor\Http\CurlHttpClient;
use Noelo\Monitor\Reporting\FeedErrorRepository;
use Noelo\Monitor\Reporting\OrderRepository;
use Noelo\Monitor\Reporting\PaymentMatchRepository;
use Noelo\Monitor\Reporting\PaymentReminderLogRepository;
use Noelo\Monitor\Reporting\PaymentReminderStep;
use Noelo\Monitor\Rules\PaymentReminderRule;
use PDO;

/**
 * Zdielane zapojenie PaymentReminderStep - pouziva ho denna kontrola (automaticky
 * rezim) aj tlacidla "Poslat mail" / "Neposielat" v dashboarde.
 */
final class PaymentReminderStepFactory
{
    /** null = PAYMENT_REMINDER_URL nie je v .env nastavene */
    public static function create(Env $env, PDO $pdo, bool $dryRun): ?PaymentReminderStep
    {
        $url = $env->get('PAYMENT_REMINDER_URL');
        if ($url === null) {
            return null;
        }

        return new PaymentReminderStep(
            httpClient: new CurlHttpClient(),
            orderRepository: new OrderRepository($pdo),
            paymentMatchRepository: new PaymentMatchRepository($pdo),
            log: new PaymentReminderLogRepository($pdo),
            rule: new PaymentReminderRule(),
            feedErrors: new FeedErrorRepository($pdo),
            urlTemplate: $url,
            dryRun: $dryRun,
            shopIdPrefix: (string) $env->get('PAYMENT_REMINDER_ID_PREFIX', '3014'),
            maxPerRun: (int) $env->getInt('PAYMENT_REMINDER_MAX_PER_RUN', 20),
            timeoutSeconds: (int) $env->getInt('PAYMENT_REMINDER_TIMEOUT_SECONDS', 20),
            basicAuthUsername: $env->get('PAYMENT_REMINDER_BASIC_USERNAME'),
            basicAuthPassword: $env->get('PAYMENT_REMINDER_BASIC_PASSWORD'),
        );
    }
}
