CREATE TABLE orders (
    id                    BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    created_at            DATETIME NOT NULL,
    status                VARCHAR(64) NOT NULL,
    delivery_name         VARCHAR(255) NOT NULL,
    email                 VARCHAR(255) NOT NULL,
    total_price_cents     INT NOT NULL,
    delivery_type         VARCHAR(255) NOT NULL,
    delivery_price_cents  INT NOT NULL,
    payed_cents           INT NULL,
    is_cash_on_delivery   TINYINT(1) NOT NULL DEFAULT 0,
    raw_hash              CHAR(64) NOT NULL,
    imported_at           DATETIME NOT NULL,
    updated_at            DATETIME NOT NULL,
    INDEX idx_orders_status (status),
    INDEX idx_orders_created_at (created_at),
    INDEX idx_orders_payed_cents (payed_cents)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
