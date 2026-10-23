<?php

declare(strict_types=1);

namespace Noelo\Monitor\Tests\Unit;

use Noelo\Monitor\Http\Auth\SessionAuth;
use Noelo\Monitor\Tests\Support\DatabaseTestCase;

final class SessionAuthTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $this->pdo->exec('TRUNCATE TABLE admin_users');
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $stmt = $this->pdo->prepare(
            'INSERT INTO admin_users (username, password_hash, created_at) VALUES (:u, :h, :c)'
        );
        $stmt->execute([
            'u' => 'admin',
            'h' => password_hash('correct-password-123', PASSWORD_DEFAULT),
            'c' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    public function testCorrectCredentialsLogInAndPopulateSession(): void
    {
        $session = [];
        $auth = new SessionAuth($this->pdo);

        $result = $auth->attempt($session, 'admin', 'correct-password-123');

        self::assertTrue($result);
        self::assertTrue($auth->isAuthenticated($session));
        self::assertSame('admin', $auth->currentUsername($session));
    }

    public function testWrongPasswordFailsAndDoesNotAuthenticate(): void
    {
        $session = [];
        $auth = new SessionAuth($this->pdo);

        $result = $auth->attempt($session, 'admin', 'wrong-password');

        self::assertFalse($result);
        self::assertFalse($auth->isAuthenticated($session));
    }

    public function testUnknownUsernameFails(): void
    {
        $session = [];
        $auth = new SessionAuth($this->pdo);

        self::assertFalse($auth->attempt($session, 'nobody', 'correct-password-123'));
    }

    public function testLogoutClearsSession(): void
    {
        $session = [];
        $auth = new SessionAuth($this->pdo);
        $auth->attempt($session, 'admin', 'correct-password-123');

        $auth->logout($session);

        self::assertFalse($auth->isAuthenticated($session));
        self::assertNull($auth->currentUsername($session));
    }

    public function testAccountLocksAfterFiveFailedAttempts(): void
    {
        $auth = new SessionAuth($this->pdo);

        for ($i = 0; $i < 5; $i++) {
            $session = [];
            $auth->attempt($session, 'admin', 'wrong-password');
        }

        self::assertNotNull($auth->lockoutRemainingSeconds('admin'));
    }

    public function testLockedAccountRejectsEvenTheCorrectPassword(): void
    {
        $auth = new SessionAuth($this->pdo);

        for ($i = 0; $i < 5; $i++) {
            $session = [];
            $auth->attempt($session, 'admin', 'wrong-password');
        }

        $session = [];
        $result = $auth->attempt($session, 'admin', 'correct-password-123');

        self::assertFalse($result);
        self::assertFalse($auth->isAuthenticated($session));
    }

    public function testFourFailedAttemptsDoNotLockTheAccountYet(): void
    {
        $auth = new SessionAuth($this->pdo);

        for ($i = 0; $i < 4; $i++) {
            $session = [];
            $auth->attempt($session, 'admin', 'wrong-password');
        }

        self::assertNull($auth->lockoutRemainingSeconds('admin'));

        $session = [];
        self::assertTrue($auth->attempt($session, 'admin', 'correct-password-123'));
    }

    public function testSuccessfulLoginResetsFailedAttemptCounter(): void
    {
        $auth = new SessionAuth($this->pdo);

        for ($i = 0; $i < 3; $i++) {
            $session = [];
            $auth->attempt($session, 'admin', 'wrong-password');
        }

        $session = [];
        self::assertTrue($auth->attempt($session, 'admin', 'correct-password-123'));

        // po uspesnom prihlaseni sa pocitadlo vynuluje - dalsie 4 zle pokusy este nezamknu ucet
        for ($i = 0; $i < 4; $i++) {
            $session = [];
            $auth->attempt($session, 'admin', 'wrong-password');
        }
        self::assertNull($auth->lockoutRemainingSeconds('admin'));
    }

    public function testUnknownUsernameNeverReportsAsLocked(): void
    {
        $auth = new SessionAuth($this->pdo);

        self::assertNull($auth->lockoutRemainingSeconds('nobody'));
    }
}
