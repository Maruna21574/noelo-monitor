<?php

declare(strict_types=1);

namespace Noelo\Monitor\Mail;

final class Mailer
{
    public function __construct(
        private readonly MailTransportInterface $transport,
        private readonly string $fromAddress,
        private readonly string $fromName,
    ) {
    }

    /**
     * @param string[] $recipients
     * @throws MailerException
     */
    public function send(array $recipients, string $subject, string $plainTextBody): void
    {
        if ($recipients === []) {
            throw new MailerException('Nie je nastaveny ziadny prijemca reportu.');
        }

        $this->transport->send($recipients, $this->fromAddress, $this->fromName, $subject, $plainTextBody);
    }
}
