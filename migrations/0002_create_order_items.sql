CREATE TABLE order_items (
    id                BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    order_id          BIGINT UNSIGNED NOT NULL,
    product_code      VARCHAR(64) NULL,
    title             VARCHAR(255) NOT NULL,
    count             INT NOT NULL,
    unit_price_cents  INT NOT NULL,
    line_total_cents  INT NOT NULL,
    delivery_info     VARCHAR(128) NULL,
    supplier          VARCHAR(128) NULL,
    is_discount       TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id),
    INDEX idx_order_items_order_id (order_id),
    INDEX idx_order_items_product_code (product_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
