CREATE TABLE feed_errors (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    check_run_id  INT UNSIGNED NOT NULL,
    feed_type     ENUM('order_feed', 'stock_feed', 'fio_api') NOT NULL,
    message       VARCHAR(500) NOT NULL,
    occurred_at   DATETIME NOT NULL,
    CONSTRAINT fk_feed_errors_check_run FOREIGN KEY (check_run_id) REFERENCES check_runs(id),
    INDEX idx_feed_errors_check_run_id (check_run_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
