ALTER TABLE admin_users
    ADD COLUMN failed_login_count INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN locked_until DATETIME NULL;
