CREATE TABLE fio_transactions (
    id                      BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    transaction_date        DATE NOT NULL,
    amount_cents            INT NOT NULL,
    currency                CHAR(3) NOT NULL,
    variable_symbol         VARCHAR(20) NULL,
    counter_account_name    VARCHAR(255) NULL,
    message_for_recipient   VARCHAR(255) NULL,
    user_identification     VARCHAR(255) NULL,
    comment                 VARCHAR(255) NULL,
    match_status            ENUM('unmatched', 'matched_exact', 'matched_candidate', 'manual_confirmed', 'manual_rejected')
                                NOT NULL DEFAULT 'unmatched',
    matched_order_id        BIGINT UNSIGNED NULL,
    imported_at             DATETIME NOT NULL,
    CONSTRAINT fk_fio_transactions_order FOREIGN KEY (matched_order_id) REFERENCES orders(id),
    INDEX idx_fio_transactions_variable_symbol (variable_symbol),
    INDEX idx_fio_transactions_amount_date (amount_cents, transaction_date),
    INDEX idx_fio_transactions_match_status (match_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
