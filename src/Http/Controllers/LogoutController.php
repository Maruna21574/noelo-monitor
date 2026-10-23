<?php

declare(strict_types=1);

namespace Noelo\Monitor\Http\Controllers;

use Noelo\Monitor\Http\Auth\SessionAuth;

final class LogoutController
{
    public function __construct(private readonly SessionAuth $auth)
    {
    }

    /** @param array<string, mixed> $session */
    public function handle(array &$session): void
    {
        $this->auth->logout($session);
        header('Location: ?page=login');
    }
}
