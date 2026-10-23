<?php

declare(strict_types=1);

namespace Noelo\Monitor\Mail;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

final class PhpMailerTransport implements MailTransportInterface
{
    public function __construct(
        private readonly string $smtpHost,
        private readonly int $smtpPort,
        private readonly string $smtpUsername,
        private readonly string $smtpPassword,
        private readonly string $smtpEncryption,
    ) {
    }

    public function send(
        array $recipients,
        string $fromAddress,
        string $fromName,
        string $subject,
        string $plainTextBody,
    ): void {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $this->smtpHost;
            $mail->Port = $this->smtpPort;
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtpUsername;
            $mail->Password = $this->smtpPassword;
            $mail->SMTPSecure = $this->smtpEncryption;
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($fromAddress, $fromName);
            foreach ($recipients as $recipient) {
                $mail->addAddress($recipient);
            }

            $mail->isHTML(false);
            $mail->Subject = $subject;
            $mail->Body = $plainTextBody;

            $mail->send();
        } catch (PHPMailerException $e) {
            throw new MailerException('Odoslanie e-mailu zlyhalo: ' . $this->sanitize($e->getMessage()));
        }
    }

    private function sanitize(string $message): string
    {
        // Heslo sa v spravach PHPMailer bezne neobjavuje, ale pre istotu ho odstranime.
        return $this->smtpPassword !== '' ? str_replace($this->smtpPassword, '***', $message) : $message;
    }
}
