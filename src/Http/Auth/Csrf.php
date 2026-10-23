<?php

declare(strict_types=1);

namespace Noelo\Monitor\Http\Auth;

final class Csrf
{
    /** @param array<string, mixed> $session */
    public static function token(array &$session): string
    {
        if (!isset($session['csrf_token']) || !is_string($session['csrf_token'])) {
            $session['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $session['csrf_token'];
    }

    /** @param array<string, mixed> $session */
    public static function isValid(array $session, mixed $submittedToken): bool
    {
        return isset($session['csrf_token'])
            && is_string($submittedToken)
            && $submittedToken !== ''
            && hash_equals($session['csrf_token'], $submittedToken);
    }
}
