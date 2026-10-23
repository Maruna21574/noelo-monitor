<?php

declare(strict_types=1);

namespace Noelo\Monitor\Http\Controllers;

use Noelo\Monitor\Bootstrap\ReportSenderFactory;
use Noelo\Monitor\Config\Env;
use Noelo\Monitor\Http\Auth\Csrf;
use Noelo\Monitor\Mail\MailerException;
use Noelo\Monitor\Reporting\CheckRunRepository;
use PDO;

/**
 * Rucne (pre)poslanie dnesneho denneho reportu e-mailom, mimo automatickej
 * "najviac raz za den" logiky - admin si moze vyziadat opatovne odoslanie.
 */
final class SendReportController
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly Env $env,
    ) {
    }

    /** @param array<string, mixed> $post */
    public function handle(array $post, array $session): void
    {
        if (!Csrf::isValid($session, $post['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Neplatny CSRF token.';
            return;
        }

        $sender = ReportSenderFactory::create($this->env, $this->pdo);
        if ($sender === null) {
            header('Location: ?page=dashboard&mail=not-configured');
            return;
        }

        $latestRun = (new CheckRunRepository($this->pdo))->findLatest();
        if ($latestRun === null) {
            header('Location: ?page=dashboard&mail=no-run-yet');
            return;
        }

        try {
            $sender->send(new \DateTimeImmutable('today'), (int) $latestRun['id'], force: true);
        } catch (MailerException) {
            header('Location: ?page=dashboard&mail=failed');
            return;
        }

        header('Location: ?page=dashboard&mail=sent');
    }
}
