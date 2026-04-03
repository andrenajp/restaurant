-- Migration 004 : adresses sauvegardées par utilisateur
CREATE TABLE IF NOT EXISTS user_addresses (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    label      VARCHAR(50)  NOT NULL DEFAULT 'Adresse',
    address    VARCHAR(255) NOT NULL,
    is_default TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
