CREATE TABLE auth_nonces (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nonce CHAR(32) NOT NULL,
    address CHAR(42) NULL,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_nonce (nonce),
    KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
