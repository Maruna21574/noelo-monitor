<?php

declare(strict_types=1);

namespace Noelo\Monitor\Http\Controllers;

use Noelo\Monitor\Http\Auth\Csrf;
use Noelo\Monitor\Http\Auth\SessionAuth;

final class LoginController
{
    public function __construct(private readonly SessionAuth $auth)
    {
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $session
     */
    public function handle(string $method, array $post, array &$session): void
    {
        if ($this->auth->isAuthenticated($session)) {
            header('Location: ?page=dashboard');
            return;
        }

        $error = null;

        if ($method === 'POST') {
            $username = (string) ($post['username'] ?? '');

            if (!Csrf::isValid($session, $post['csrf_token'] ?? null)) {
                $error = 'Formular vypršal, skús to znova.';
            } elseif ($this->auth->attempt($session, $username, (string) ($post['password'] ?? ''))) {
                header('Location: ?page=dashboard');
                return;
            } else {
                $remainingSeconds = $this->auth->lockoutRemainingSeconds($username);
                $error = $remainingSeconds !== null
                    ? 'Príliš veľa neúspešných pokusov. Skús to znova o ' . (int) ceil($remainingSeconds / 60) . ' min.'
                    : 'Nesprávne prihlasovacie meno alebo heslo.';
            }
        }

        $csrfToken = Csrf::token($session);
        require __DIR__ . '/../Views/login.php';
    }
}
