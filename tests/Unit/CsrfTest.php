<?php

declare(strict_types=1);

namespace Noelo\Monitor\Tests\Unit;

use Noelo\Monitor\Http\Auth\Csrf;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    public function testTokenIsGeneratedAndReused(): void
    {
        $session = [];
        $token1 = Csrf::token($session);
        $token2 = Csrf::token($session);

        self::assertSame($token1, $token2);
        self::assertNotSame('', $token1);
    }

    public function testValidTokenPassesValidation(): void
    {
        $session = [];
        $token = Csrf::token($session);

        self::assertTrue(Csrf::isValid($session, $token));
    }

    public function testWrongTokenFailsValidation(): void
    {
        $session = [];
        Csrf::token($session);

        self::assertFalse(Csrf::isValid($session, 'wrong-token'));
    }

    public function testMissingTokenFailsValidation(): void
    {
        $session = [];
        Csrf::token($session);

        self::assertFalse(Csrf::isValid($session, null));
    }

    public function testNoSessionTokenAtAllFailsValidation(): void
    {
        self::assertFalse(Csrf::isValid([], 'anything'));
    }
}
