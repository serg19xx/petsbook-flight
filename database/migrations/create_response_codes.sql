-- Создание таблицы для кодов ответов API
CREATE TABLE `api_response_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(100) NOT NULL COMMENT 'Код ответа (например: EMAIL_NOT_VERIFIED)',
  `namespace` varchar(50) NOT NULL DEFAULT 'auth' COMMENT 'Пространство имен (auth, user, system)',
  `http_status` int(3) NOT NULL DEFAULT 400 COMMENT 'HTTP статус код',
  `description` text DEFAULT NULL COMMENT 'Описание кода на английском',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_code` (`code`),
  KEY `idx_namespace` (`namespace`),
  KEY `idx_http_status` (`http_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Коды ответов API';

-- Создание таблицы для переводов кодов ответов
CREATE TABLE `api_response_translations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code_id` int(11) NOT NULL,
  `locale` varchar(10) NOT NULL,
  `message` text NOT NULL COMMENT 'Переведенное сообщение',
  `is_auto_translated` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_code_locale` (`code_id`,`locale`),
  KEY `idx_locale` (`locale`),
  CONSTRAINT `fk_response_code` FOREIGN KEY (`code_id`) REFERENCES `api_response_codes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_response_locale` FOREIGN KEY (`locale`) REFERENCES `i18n_locales` (`code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Переводы кодов ответов API';

-- Вставка базовых кодов ответов
INSERT INTO `api_response_codes` (`code`, `namespace`, `http_status`, `description`) VALUES
-- Success codes
('LOGIN_SUCCESS', 'auth', 200, 'User successfully logged in'),
('LOGOUT_SUCCESS', 'auth', 200, 'User successfully logged out'),
('REGISTRATION_SUCCESS', 'auth', 201, 'User successfully registered'),
('EMAIL_VERIFICATION_SUCCESS', 'auth', 200, 'Email successfully verified'),
('PASSWORD_RESET_SUCCESS', 'auth', 200, 'Password reset successful'),

-- Authentication error codes
('MISSING_CREDENTIALS', 'auth', 400, 'Email and password are required'),
('INVALID_CREDENTIALS', 'auth', 400, 'Invalid email or password'),
('INVALID_PASSWORD', 'auth', 400, 'Invalid password'),
('EMAIL_NOT_VERIFIED', 'auth', 400, 'Email is not verified'),
('ACCOUNT_BLOCKED', 'auth', 403, 'Account is blocked'),
('ACCOUNT_INACTIVE', 'auth', 403, 'Account is inactive'),

-- Token error codes
('TOKEN_NOT_PROVIDED', 'auth', 401, 'Authentication token not provided'),
('INVALID_TOKEN', 'auth', 401, 'Invalid authentication token'),
('TOKEN_EXPIRED', 'auth', 401, 'Authentication token expired'),

-- User error codes
('USER_NOT_FOUND', 'user', 404, 'User not found'),
('EMAIL_ALREADY_EXISTS', 'user', 409, 'Email already exists'),
('INVALID_USER_DATA', 'user', 400, 'Invalid user data'),
('INVALID_ROLE', 'user', 400, 'Invalid user role'),

-- System error codes
('SYSTEM_ERROR', 'system', 500, 'Internal server error'),
('DATABASE_ERROR', 'system', 500, 'Database error'),
('EMAIL_SEND_ERROR', 'system', 500, 'Email sending failed'),
('INVALID_REQUEST', 'system', 400, 'Invalid request data');

-- Вставка английских переводов (базовые)
INSERT INTO `api_response_translations` (`code_id`, `locale`, `message`, `is_auto_translated`) 
SELECT 
    arc.id,
    'en',
    CASE arc.code
        WHEN 'LOGIN_SUCCESS' THEN 'Login successful'
        WHEN 'LOGOUT_SUCCESS' THEN 'Logout successful'
        WHEN 'REGISTRATION_SUCCESS' THEN 'Registration successful'
        WHEN 'EMAIL_VERIFICATION_SUCCESS' THEN 'Email verification successful'
        WHEN 'PASSWORD_RESET_SUCCESS' THEN 'Password reset successful'
        WHEN 'MISSING_CREDENTIALS' THEN 'Email and password are required'
        WHEN 'INVALID_CREDENTIALS' THEN 'Invalid email or password'
        WHEN 'INVALID_PASSWORD' THEN 'Invalid password'
        WHEN 'EMAIL_NOT_VERIFIED' THEN 'Your email is not verified. Please check your email and follow the verification link.'
        WHEN 'ACCOUNT_BLOCKED' THEN 'Your account is blocked. Please contact support.'
        WHEN 'ACCOUNT_INACTIVE' THEN 'Your account is inactive'
        WHEN 'TOKEN_NOT_PROVIDED' THEN 'Authentication token not provided'
        WHEN 'INVALID_TOKEN' THEN 'Invalid authentication token'
        WHEN 'TOKEN_EXPIRED' THEN 'Authentication token expired'
        WHEN 'USER_NOT_FOUND' THEN 'User not found'
        WHEN 'EMAIL_ALREADY_EXISTS' THEN 'Email already exists'
        WHEN 'INVALID_USER_DATA' THEN 'Invalid user data'
        WHEN 'INVALID_ROLE' THEN 'Invalid user role'
        WHEN 'SYSTEM_ERROR' THEN 'An unexpected error occurred. Please try again later.'
        WHEN 'DATABASE_ERROR' THEN 'Database error occurred'
        WHEN 'EMAIL_SEND_ERROR' THEN 'Failed to send email'
        WHEN 'INVALID_REQUEST' THEN 'Invalid request data'
        ELSE arc.description
    END,
    0
FROM `api_response_codes` arc; 