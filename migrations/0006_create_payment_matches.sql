CREATE TABLE payment_matches (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    fio_transaction_id  BIGINT UNSIGNED NOT NULL,
    order_id            BIGINT UNSIGNED NOT NULL,
    match_type          ENUM('exact_vs', 'amount_date_candidate', 'name_message_candidate') NOT NULL,
    status              ENUM('pending_review', 'confirmed', 'rejected') NOT NULL DEFAULT 'pending_review',
    reviewed_by         VARCHAR(64) NULL,
    reviewed_at         DATETIME NULL,
    created_at          DATETIME NOT NULL,
    CONSTRAINT fk_payment_matches_fio_transaction FOREIGN KEY (fio_transaction_id) REFERENCES fio_transactions(id),
    CONSTRAINT fk_payment_matches_order FOREIGN KEY (order_id) REFERENCES orders(id),
    UNIQUE KEY uq_payment_matches_transaction_order (fio_transaction_id, order_id),
    INDEX idx_payment_matches_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
