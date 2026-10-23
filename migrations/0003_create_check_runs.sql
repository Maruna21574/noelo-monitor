CREATE TABLE check_runs (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    started_at          DATETIME NOT NULL,
    finished_at         DATETIME NULL,
    order_feed_status   ENUM('ok', 'error') NULL,
    stock_feed_status   ENUM('ok', 'error') NULL,
    fio_status          ENUM('ok', 'error') NULL,
    triggered_by        ENUM('cron', 'manual') NOT NULL,
    triggered_by_user    VARCHAR(64) NULL,
    INDEX idx_check_runs_started_at (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
