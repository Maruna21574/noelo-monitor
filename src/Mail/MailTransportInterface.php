<?php

declare(strict_types=1);

namespace Noelo\Monitor\Mail;

interface MailTransportInterface
{
    /**
     * @param string[] $recipients
     * @throws MailerException ked odoslanie zlyha (SMTP nedostupne, zle prihlasenie a pod.)
     */
    public function send(
        array $recipients,
        string $fromAddress,
        string $fromName,
        string $subject,
        string $plainTextBody,
    ): void;
}
