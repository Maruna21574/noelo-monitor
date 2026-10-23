CREATE TABLE reservation_conflict_dismissals (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    blocked_order_id   BIGINT UNSIGNED NOT NULL,
    product_code       VARCHAR(64) NOT NULL,
    dismissed_by       VARCHAR(64) NOT NULL,
    dismissed_at       DATETIME NOT NULL,
    UNIQUE KEY uq_conflict_dismissal (blocked_order_id, product_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
