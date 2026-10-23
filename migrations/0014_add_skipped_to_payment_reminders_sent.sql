ALTER TABLE payment_reminders_sent
    MODIFY status ENUM('pending', 'sent', 'failed', 'unknown', 'skipped') NOT NULL;
