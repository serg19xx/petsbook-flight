CREATE TABLE password_reset_tokens (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    login_id BIGINT NOT NULL,
    token VARCHAR(64) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    UNIQUE KEY unique_token (token)
);