CREATE TABLE reports (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    run_date        DATE NOT NULL,
    check_run_id    INT UNSIGNED NOT NULL,
    generated_at    DATETIME NOT NULL,
    sent_at         DATETIME NULL,
    status          ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
    recipients      VARCHAR(500) NOT NULL,
    CONSTRAINT fk_reports_check_run FOREIGN KEY (check_run_id) REFERENCES check_runs(id),
    UNIQUE KEY uq_reports_run_date (run_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
