<?php

declare(strict_types=1);

namespace Noelo\Monitor\Http\Auth;

use PDO;

/**
 * Prihlasovanie do administracie. Pracuje s prenajatou session tabulkou
 * (referenciou na $_SESSION) namiesto priameho pristupu k superglobalu,
 * aby sa dala testovat bez skutocnej PHP session.
 *
 * Obsahuje ochranu proti hrubej sile: po niekolkych zlych pokusoch sa ucet
 * docasne zamkne, aj keby neskor niekto zadal spravne heslo.
 */
final class SessionAuth
{
    private const MAX_FAILED_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES = 15;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param array<string, mixed> $session */
    public function attempt(array &$session, string $username, string $password): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, password_hash, failed_login_count, locked_until FROM admin_users WHERE username = :username'
        );
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user === false) {
            return false;
        }

        if ($this->isLockedRow($user)) {
            return false;
        }

        if (!password_verify($password, $user['password_hash'])) {
            $this->registerFailedAttempt((int) $user['id'], (int) $user['failed_login_count']);
            return false;
        }

        $this->resetFailedAttempts((int) $user['id']);

        $session['admin_user_id'] = (int) $user['id'];
        $session['admin_username'] = $username;

        return true;
    }

    /**
     * Kolko sekund este ostava zamknute, alebo null ak ucet nie je zamknuty
     * (vratane neexistujuceho username - to sa navonok nema dat odlisit).
     */
    public function lockoutRemainingSeconds(string $username): ?int
    {
        $stmt = $this->pdo->prepare('SELECT locked_until FROM admin_users WHERE username = :username');
        $stmt->execute(['username' => $username]);
        $row = $stmt->fetch();

        if ($row === false || $row['locked_until'] === null) {
            return null;
        }

        $lockedUntil = new \DateTimeImmutable($row['locked_until']);
        $now = new \DateTimeImmutable();

        if ($lockedUntil <= $now) {
            return null;
        }

        return $lockedUntil->getTimestamp() - $now->getTimestamp();
    }

    /** @param array<string, mixed> $session */
    public function isAuthenticated(array $session): bool
    {
        return isset($session['admin_user_id']);
    }

    /** @param array<string, mixed> $session */
    public function currentUsername(array $session): ?string
    {
        return $session['admin_username'] ?? null;
    }

    /** @param array<string, mixed> $session */
    public function logout(array &$session): void
    {
        unset($session['admin_user_id'], $session['admin_username']);
    }

    /** @param array<string, mixed> $row */
    private function isLockedRow(array $row): bool
    {
        if ($row['locked_until'] === null) {
            return false;
        }

        return new \DateTimeImmutable($row['locked_until']) > new \DateTimeImmutable();
    }

    private function registerFailedAttempt(int $userId, int $currentFailedCount): void
    {
        $newCount = $currentFailedCount + 1;

        if ($newCount >= self::MAX_FAILED_ATTEMPTS) {
            $lockedUntil = (new \DateTimeImmutable())->modify('+' . self::LOCKOUT_MINUTES . ' minutes');
            $stmt = $this->pdo->prepare(
                'UPDATE admin_users SET failed_login_count = 0, locked_until = :locked_until WHERE id = :id'
            );
            $stmt->execute(['locked_until' => $lockedUntil->format('Y-m-d H:i:s'), 'id' => $userId]);
            return;
        }

        $stmt = $this->pdo->prepare('UPDATE admin_users SET failed_login_count = :count WHERE id = :id');
        $stmt->execute(['count' => $newCount, 'id' => $userId]);
    }

    private function resetFailedAttempts(int $userId): void
    {
        $stmt = $this->pdo->prepare('UPDATE admin_users SET failed_login_count = 0, locked_until = NULL WHERE id = :id');
        $stmt->execute(['id' => $userId]);
    }
}
