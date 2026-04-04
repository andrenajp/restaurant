CREATE TABLE IF NOT EXISTS rate_limit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(255) NOT NULL,
    attempts INT NOT NULL DEFAULT 1,
    blocked_until DATETIME NULL,
    last_attempt DATETIME NOT NULL,
    UNIQUE KEY idx_key (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
