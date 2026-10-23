<?php

declare(strict_types=1);

namespace Noelo\Monitor\Http\Controllers;

final class HelpController
{
    /** @param array<string, mixed> $session */
    public function handle(array $session): void
    {
        $username = $session['admin_username'] ?? null;

        require __DIR__ . '/../Views/help.php';
    }
}
