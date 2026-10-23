<?php

declare(strict_types=1);

namespace Noelo\Monitor\Tests\Unit\Fakes;

use Noelo\Monitor\Mail\MailerException;
use Noelo\Monitor\Mail\MailTransportInterface;

final class FakeMailTransport implements MailTransportInterface
{
    private int $callCount = 0;

    public function __construct(private readonly bool $shouldFail = false)
    {
    }

    public function send(
        array $recipients,
        string $fromAddress,
        string $fromName,
        string $subject,
        string $plainTextBody,
    ): void {
        $this->callCount++;

        if ($this->shouldFail) {
            throw new MailerException('SMTP spojenie zlyhalo (simulovane).');
        }
    }

    public function callCount(): int
    {
        return $this->callCount;
    }
}
