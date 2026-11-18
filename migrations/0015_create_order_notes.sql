CREATE TABLE order_notes (
    order_id    BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    note        VARCHAR(500) NOT NULL,
    updated_by  VARCHAR(64) NOT NULL,
    updated_at  DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
