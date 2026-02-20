-- ===================================================
-- Table user : comptes utilisateurs
-- ===================================================
CREATE TABLE user (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_super_admin TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ===================================================
-- Table user_broker_account : liaison user ↔ broker_account
-- ===================================================
CREATE TABLE user_broker_account (
    user_id INT NOT NULL,
    broker_account_id INT NOT NULL,
    PRIMARY KEY (user_id, broker_account_id),
    CONSTRAINT fk_uba_user
        FOREIGN KEY (user_id) REFERENCES user(id)
        ON DELETE CASCADE,
    
    CONSTRAINT fk_uba_broker
        FOREIGN KEY (broker_account_id) REFERENCES broker_account(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ===================================================
-- Table user_api_token : tokens pour API / CLI
-- ===================================================
CREATE TABLE user_api_token (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    
    name VARCHAR(150) NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    
    expires_at DATETIME NULL,
    last_used_at DATETIME NULL,
    is_revoked TINYINT(1) NOT NULL DEFAULT 0,
    
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_token_user
        FOREIGN KEY (user_id) REFERENCES user(id)
        ON DELETE CASCADE,
    
    INDEX idx_token_user (user_id),
    INDEX idx_token_active (is_revoked, expires_at)
) ENGINE=InnoDB;