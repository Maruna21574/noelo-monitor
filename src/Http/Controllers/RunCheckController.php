<?php

declare(strict_types=1);

namespace Noelo\Monitor\Http\Controllers;

use Noelo\Monitor\Bootstrap\DailyCheckRunnerFactory;
use Noelo\Monitor\Config\Env;
use Noelo\Monitor\Http\Auth\Csrf;
use Noelo\Monitor\Http\Auth\SessionAuth;
use PDO;

final class RunCheckController
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly Env $env,
    ) {
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $session
     */
    public function handle(array $post, array $session, SessionAuth $auth): void
    {
        if (!Csrf::isValid($session, $post['csrf_token'] ?? null)) {
            http_response_code(400);
            echo 'Neplatny CSRF token.';
            return;
        }

        try {
            $runner = DailyCheckRunnerFactory::create($this->env, $this->pdo);
            $runner->run('manual', $auth->currentUsername($session), new \DateTimeImmutable('today'));
        } catch (\Throwable) {
            // Konfiguracna/technicka chyba (napr. chybajuci ORDER_FEED_URL v .env) sa
            // nikdy nema zobrazit uzivatelovi ako surovy stack trace so systemovymi cestami.
            http_response_code(500);
            echo 'Kontrolu sa nepodarilo spustit - skontroluj konfiguraciu (.env) a logy servera.';
            return;
        }

        header('Location: ?page=dashboard&ran=1');
    }
}
