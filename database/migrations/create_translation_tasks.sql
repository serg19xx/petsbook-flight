CREATE TABLE IF NOT EXISTS i18n_translation_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    locale VARCHAR(10) NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    total_strings INT DEFAULT 0,
    processed_strings INT DEFAULT 0,
    skipped_strings INT DEFAULT 0,
    errors JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    INDEX idx_locale (locale),
    INDEX idx_status (status)
); 