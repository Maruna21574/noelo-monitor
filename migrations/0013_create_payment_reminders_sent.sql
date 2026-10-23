CREATE TABLE payment_reminders_sent (
    order_id      BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    status        ENUM('pending', 'sent', 'failed', 'unknown') NOT NULL,
    http_status   SMALLINT UNSIGNED NULL,
    attempted_at  DATETIME NOT NULL,
    sent_at       DATETIME NULL,
    INDEX idx_payment_reminders_sent_attempted_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE feed_errors
    MODIFY feed_type ENUM('order_feed', 'stock_feed', 'fio_api', 'payment_reminder') NOT NULL;
