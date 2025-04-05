<?php

namespace App\Controllers;

use PDO;
use Flight;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

class AuthController extends BaseController {
    private $db;

    public function __construct() {
        try {
            // Добавляем установку кодировки соединения
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];

            $this->db = new PDO(
                "mysql:host=" . $_ENV['DB_HOST'] . 
                ";dbname=" . $_ENV['DB_NAME'] . 
                ";charset=utf8mb4",
                $_ENV['DB_USER'],
                $_ENV['DB_PASSWORD'],
                $options
            );

            error_log("Database connection successful");
        } catch (\PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw new \Exception('Database connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Login method response codes:
     * 
     * Success responses:
     * - LOGIN_SUCCESS: Успешная авторизация
     * 
     * Error responses:
     * - MISSING_CREDENTIALS: Отсутствуют email или пароль
     * - INVALID_CREDENTIALS: Неверный email или пароль
     * - LOGIN_FAILED: Ошибка входа (общая)
     * - ACCOUNT_INACTIVE: Аккаунт неактивен
     * - EMAIL_NOT_VERIFIED: Email не подтвержден
     * - ACCOUNT_BLOCKED: Аккаунт заблокирован
     * - SYSTEM_ERROR: Системная ошибка
     */
    public function login() {
        try {
            $requestBody = Flight::request()->getBody();
            $data = json_decode($requestBody, true);

            //print_r(data);
            
            if (!isset($data['email']) || !isset($data['password'])) {
                return Flight::json([
                    'status' => 400,
                    'error_code' => 'MISSING_CREDENTIALS'
                ], 400);
            }

            $email = trim($data['email']);
            $password = $data['password'];

            $stmt = $this->db->prepare("CALL sp_Login(:email)");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            // Проверка статуса аккаунта
            if ($user && isset($user['is_active']) && !$user['is_active']) {
                return Flight::json([
                    'status' => 400,
                    'error_code' => 'ACCOUNT_INACTIVE'
                ], 400);
            }

            // Проверка верификации email
            if ($user && isset($user['email_verified']) && !$user['email_verified']) {
                return Flight::json([
                    'status' => 400,
                    'error_code' => 'EMAIL_NOT_VERIFIED'
                ], 400);
            }

            if (!$user || !isset($user['success']) || !$user['success']) {
                return Flight::json([
                    'status' => 400,
                    'error_code' => $user['error_code'] ?? 'LOGIN_FAILED'
                ], 400);
            }

            if (!password_verify($password, $user['stored_password'])) {
                return Flight::json([
                    'status' => 400,
                    'error_code' => 'INVALID_CREDENTIALS'
                ], 400);
            }

            $token = $this->generateJWT([
                'id' => $user['id'],
                'email' => $email,
                'role' => $user['role']
            ]);

            return Flight::json([
                'status' => 200,
                'error_code' => 'LOGIN_SUCCESS',
                'data' => [
                    'token' => $token,
                    'user' => [
                        'id' => $user['id'],
                        'email' => $email,
                        'role' => $user['role']
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return Flight::json([
                'status' => 500,
                'error_code' => 'SYSTEM_ERROR',
                'data' => null
            ], 500);
        }
    }

    public function register() {
        try {
            $requestBody = Flight::request()->getBody();
            $data = json_decode($requestBody, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON decode error: " . json_last_error_msg());
                return $this->error('INVALID_JSON_FORMAT');
            }

            // Санитизация и нормализация входных данных
            $name = isset($data['name']) ? trim(mb_convert_encoding($data['name'], 'UTF-8', 'auto')) : '';
            $email = isset($data['email']) ? trim(mb_convert_encoding($data['email'], 'UTF-8', 'auto')) : '';
            $password = isset($data['password']) ? $data['password'] : '';
            $role = isset($data['role']) ? trim(mb_convert_encoding($data['role'], 'UTF-8', 'auto')) : '';

            // Проверки данных...
            if (empty($email) || empty($password) || empty($name) || empty($role)) {
                return $this->error('MISSING_REQUIRED_FIELDS');
            }

            $allowedRoles = ['admin', 'moderator', 'partner', 'commercial', 'user'];
            if (!in_array($role, $allowedRoles)) {
                return $this->error('INVALID_ROLE');
            }

            // Хешируем пароль
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Вызов процедуры
            $stmt = $this->db->prepare("CALL sp_Register(?, ?, ?, ?)");
            $stmt->execute([$name, $email, $hashedPassword, $role]);
            $result = $stmt->fetch();
            
            // Закрываем курсор
            $stmt->closeCursor();

            if ($result && isset($result['success']) && $result['success']) {
                try {
                    // Создаем токен верификации
                    $token = $this->createVerificationToken($result['id']);
                    
                    // Закрываем все возможные открытые курсоры перед следующим запросом
                    while ($this->db->inTransaction()) {
                        $this->db->commit();
                    }
                    
                    // Отправляем email
                    $emailSent = $this->sendVerificationEmail($email, $name, $token);
                    
                    return Flight::json([
                        'success' => true,
                        'message' => 'Регистрация успешна. Пожалуйста, проверьте вашу почту для подтверждения email адреса.',
                        'user' => [
                            'id' => $result['id'],
                            'email' => $email,
                            'name' => $name,
                            'role' => $role,
                            'email_verified' => false
                        ]
                    ], 200);
                } catch (\Exception $e) {
                    error_log("Post-registration process error: " . $e->getMessage());
                    // Даже если возникла ошибка с email, регистрация уже выполнена успешно
                    return Flight::json([
                        'success' => true,
                        'message' => 'Регистрация успешна, но возникли проблемы с отправкой email для подтверждения.',
                        'user' => [
                            'id' => $result['id'],
                            'email' => $email,
                            'name' => $name,
                            'role' => $role,
                            'email_verified' => false
                        ]
                    ], 200);
                }
            } else {
                throw new \Exception('Registration failed: ' . ($result['message'] ?? 'Unknown error'));
            }

        } catch (\Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            return Flight::json([
                'success' => false,
                'error_code' => 'SYSTEM_ERROR',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function logout() {
        // TODO: Реализовать логику выхода
        return $this->success([], 'Logout successful');
    }

    public function passwordReset() {
        try {
            $requestBody = Flight::request()->getBody();
            $data = json_decode($requestBody, true);

            if (!isset($data['email'])) {
                return Flight::json([
                    'success' => false,
                    'message' => 'Email обязателен'
                ], 400);
            }

            $email = trim($data['email']);

            // Проверяем существование пользователя
            $stmt = $this->db->prepare("
                SELECT id, email 
                FROM logins 
                WHERE email = ? 
                AND is_active = 1
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                return Flight::json([
                    'success' => true,
                    'message' => 'Если указанный email существует, инструкции по сбросу пароля будут отправлены'
                ]);
            }

            // Генерируем токен для сброса пароля
            $token = bin2hex(random_bytes(32));

            // Сохраняем токен в базе данных
            $stmt = $this->db->prepare("
                INSERT INTO password_reset_tokens (login_id, token, expires_at, used) 
                VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), FALSE)
            ");
            $stmt->execute([$user['id'], $token]);

            // Отправляем email через отдельный метод
            $this->sendPasswordResetEmail($user['email'], $user['name'] ?? 'Пользователь', $token);

            return Flight::json([
                'success' => true,
                'message' => 'Инструкции по сбросу пароля отправлены на ваш email'
            ]);

        } catch (\Exception $e) {
            error_log("Password reset error: " . $e->getMessage());
            return Flight::json([
                'success' => false,
                'message' => 'Произошла ошибка при обработке запроса'
            ], 500);
        }
    }

    private function generateJWT($payload) {
        try {
            if (!isset($_ENV['JWT_SECRET'])) {
                throw new \Exception('JWT_SECRET не настроен');
            }

            return JWT::encode([
                ...$payload,
                'iat' => time(),
                'exp' => time() + (60 * 60 * 24)
            ], $_ENV['JWT_SECRET'], 'HS256');
        } catch (\Exception $e) {
            error_log("JWT generation error: " . $e->getMessage());
            throw $e;
        }
    }

    protected function error($message, $code = 400) {
        Flight::json([
            'success' => false,
            'message' => $message
        ], $code);
    }

    protected function success($data = [], $message = '') {
        Flight::json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], 200);
    }

    private function sendWelcomeEmail($email, $name) {
        try {
            $mail = new PHPMailer(true);

            // Server settings
            $mail->isSMTP();
            $mail->Host       = $_ENV['SMTP_HOST'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['SMTP_USERNAME'];
            $mail->Password   = $_ENV['SMTP_PASSWORD'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $_ENV['SMTP_PORT'];

            // Recipients
            $mail->setFrom($_ENV['MAIL_FROM_ADDRESS'], 'PetsBook');
            $mail->addAddress($email, $name);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Welcome to PetsBook!';
            
            $mail->Body = "
            <html>
            <head>
                <title>Welcome to PetsBook</title>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { color: #2563eb; font-size: 24px; margin-bottom: 20px; }
                    .footer { margin-top: 30px; font-size: 14px; color: #666; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>Hello, {$name}!</h2>
                    </div>
                    <div class='content'>
                        <p>Welcome to PetsBook - the social network for pet owners and their beloved animals.</p>
                        <p>Your account has been successfully created and is ready to use.</p>
                        <p>You can now:</p>
                        <ul>
                            <li>Complete your profile</li>
                            <li>Add your pets</li>
                            <li>Connect with other pet owners</li>
                            <li>Share your pet stories</li>
                        </ul>
                    </div>
                    <div class='footer'>
                        <p>Best regards,<br>The PetsBook Team</p>
                        <small>This is an automated message, please do not reply to this email.</small>
                    </div>
                </div>
            </body>
            </html>
            ";

            // Plain text version for non-HTML mail clients
            $mail->AltBody = "Hello, {$name}!\n\n" .
                "Welcome to PetsBook - the social network for pet owners and their beloved animals.\n\n" .
                "Your account has been successfully created and is ready to use.\n\n" .
                "You can now:\n" .
                "- Complete your profile\n" .
                "- Add your pets\n" .
                "- Connect with other pet owners\n" .
                "- Share your pet stories\n\n" .
                "Best regards,\n" .
                "The PetsBook Team";

            $mail->send();
            error_log("Welcome email sent successfully to: " . $email);
            return true;

        } catch (Exception $e) {
            error_log("Error sending welcome email: " . $e->getMessage());
            return false;
        }
    }

    private function createVerificationToken($userId) {
        try {
            $token = bin2hex(random_bytes(32));
            $stmt = $this->db->prepare("
                INSERT INTO email_verification_tokens (user_id, token, expires_at)
                VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))
            ");
            $stmt->execute([$userId, $token]);
            $stmt->closeCursor(); // Закрываем курсор
            return $token;
        } catch (\Exception $e) {
            error_log("Token creation error: " . $e->getMessage());
            throw $e;
        }
    }

    private function logMessage($message, $type = 'INFO') {
        $logFile = __DIR__ . '/../../logs/auth.log';
        $timestamp = date('Y-m-d H:i:s');
        $formattedMessage = "[$timestamp][$type] $message" . PHP_EOL;
        
        // Создаем директорию для логов, если она не существует
        if (!file_exists(dirname($logFile))) {
            mkdir(dirname($logFile), 0777, true);
        }
        
        file_put_contents($logFile, $formattedMessage, FILE_APPEND);
    }

    private function sendVerificationEmail($email, $name, $token) {
        try {
            // Проверяем наличие необходимых переменных окружения
            $requiredEnvVars = [
                'APP_URL',
                'SMTP_HOST',
                'SMTP_PORT',
                'SMTP_USERNAME',
                'SMTP_PASSWORD',
                'MAIL_FROM_ADDRESS',
                'MAIL_FROM_NAME'
            ];

            foreach ($requiredEnvVars as $var) {
                if (!isset($_ENV[$var])) {
                    throw new \Exception("Missing required environment variable: $var");
                }
            }

            $this->logMessage("=== Starting Email Sending Process ===");
            $this->logMessage("Recipient: $email, Name: $name");
            
            $mail = new PHPMailer(true);
            
            // Debug output
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            $mail->Debugoutput = function($str, $level) {
                $this->logMessage($str, $level);
            };

            // Server settings
            $mail->isSMTP();
            $mail->Host = $_ENV['SMTP_HOST'];
            $mail->Port = intval($_ENV['SMTP_PORT']);
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['SMTP_USERNAME'];
            $mail->Password = $_ENV['SMTP_PASSWORD'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            
            // Log SMTP configuration
            $this->logMessage("SMTP Configuration:");
            $this->logMessage("Host: " . $mail->Host);
            $this->logMessage("Port: " . $mail->Port);
            $this->logMessage("Username: " . $mail->Username);
            $this->logMessage("SMTPSecure: " . $mail->SMTPSecure);
            
            // Test SMTP connection
            $this->logMessage("Testing SMTP connection...");
            try {
                if ($mail->smtpConnect()) {
                    $this->logMessage("SMTP connection test successful");
                    $mail->smtpClose();
                }
            } catch (\Exception $e) {
                $this->logMessage("SMTP connection test failed: " . $e->getMessage(), 'ERROR');
                throw $e;
            }

            // Recipients
            $mail->setFrom($_ENV['MAIL_FROM_ADDRESS'], $_ENV['MAIL_FROM_NAME']);
            $mail->addAddress($email, $name);
            $this->logMessage("From: " . $_ENV['MAIL_FROM_ADDRESS']);
            $this->logMessage("To: " . $email);

            // Content
            $frontendUrl = "http://localhost:5173/verify-email/" . $token;
            $this->logMessage("Verification URL: " . $frontendUrl);
            
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = 'Подтверждение email адреса';
            
            $mail->Body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h2>Здравствуйте, {$name}!</h2>
                    <p>Для завершения регистрации необходимо подтвердить ваш email адрес.</p>
                    <p>Пожалуйста, нажмите на кнопку ниже:</p>
                    <p style='text-align: center;'>
                        <a href='{$frontendUrl}' 
                           style='display: inline-block; padding: 10px 20px; 
                                  background-color: #4CAF50; color: white; 
                                  text-decoration: none; border-radius: 5px;'>
                            Подтвердить email
                        </a>
                    </p>
                    <p>Или перейдите по ссылке: <a href='{$frontendUrl}'>{$frontendUrl}</a></p>
                    <p>Ссылка действительна в течение 24 часов.</p>
                    <p>Если вы не регистрировались на нашем сайте, просто проигнорируйте это письмо.</p>
                </div>";

            $mail->AltBody = "Здравствуйте, {$name}!\n\n" .
                "Для завершения регистрации необходимо подтвердить ваш email адрес.\n\n" .
                "Перейдите по ссылке: {$frontendUrl}\n\n" .
                "Ссылка действительна в течение 24 часов.\n\n" .
                "Если вы не регистрировались на нашем сайте, просто проигнорируйте это письмо.";

            $this->logMessage("Attempting to send email...");
            
            if (!$mail->send()) {
                $this->logMessage("Mailer Error: " . $mail->ErrorInfo, 'ERROR');
                throw new \Exception("Failed to send email: " . $mail->ErrorInfo);
            }
            
            $this->logMessage("Email sent successfully to: " . $email);
            return true;

        } catch (\Exception $e) {
            $this->logMessage("=== Email Sending Error ===", 'ERROR');
            $this->logMessage("Error message: " . $e->getMessage(), 'ERROR');
            $this->logMessage("Stack trace: " . $e->getTraceAsString(), 'ERROR');
            if (isset($mail)) {
                $this->logMessage("Mailer Error Details: " . $mail->ErrorInfo, 'ERROR');
            }
            $this->logMessage("=== End Email Sending Error ===", 'ERROR');
            return false;
        }
    }

    public function verifyEmail($token) {
        try {
            $stmt = $this->db->prepare("
                SELECT t.user_id, t.expires_at 
                FROM email_verification_tokens t
                WHERE t.token = ? AND t.expires_at > NOW()
            ");
            $stmt->execute([$token]);
            $result = $stmt->fetch();

            if (!$result) {
                return $this->error('INVALID_OR_EXPIRED_TOKEN');
            }

            // Обновляем статус верификации
            $stmt = $this->db->prepare("
                UPDATE logins 
                SET email_verified = 1 
                WHERE id = ?
            ");
            $stmt->execute([$result['user_id']]);

            // Удаляем использованный токен
            $stmt = $this->db->prepare("
                DELETE FROM email_verification_tokens 
                WHERE token = ?
            ");
            $stmt->execute([$token]);

            return $this->success();

        } catch (\Exception $e) {
            error_log("Email verification error: " . $e->getMessage());
            return $this->error('SYSTEM_ERROR', 500);
        }
    }

    private function sendPasswordResetEmail($email, $name, $token) {
        try {
            $mail = new PHPMailer(true);

            // Server settings
            $mail->isSMTP();
            $mail->Host = $_ENV['SMTP_HOST'];
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['SMTP_USERNAME'];
            $mail->Password = $_ENV['SMTP_PASSWORD'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $_ENV['SMTP_PORT'];
            $mail->CharSet = 'UTF-8';

            // Recipients
            $mail->setFrom($_ENV['MAIL_FROM_ADDRESS'], $_ENV['MAIL_FROM_NAME']);
            $mail->addAddress($email, $name);

            // Content
            $resetUrl = "http://localhost:5173/reset-password/" . $token;  // Исправленный URL с портом 5173
            
            $mail->isHTML(true);
            $mail->Subject = 'Сброс пароля';
            $mail->Body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h2>Здравствуйте, {$name}!</h2>
                    <p>Мы получили запрос на сброс пароля для вашей учетной записи.</p>
                    <p>Для создания нового пароля, пожалуйста, перейдите по ссылке ниже:</p>
                    <p style='text-align: center;'>
                        <a href='{$resetUrl}' 
                           style='display: inline-block; padding: 10px 20px; 
                                  background-color: #4CAF50; color: white; 
                                  text-decoration: none; border-radius: 5px;'>
                            Сбросить пароль
                        </a>
                    </p>
                    <p>Или используйте эту ссылку: <a href='{$resetUrl}'>{$resetUrl}</a></p>
                    <p>Ссылка действительна в течение 1 часа.</p>
                    <p>Если вы не запрашивали сброс пароля, проигнорируйте это письмо.</p>
                </div>";

            $mail->AltBody = "Здравствуйте, {$name}!\n\n" .
                "Мы получили запрос на сброс пароля для вашей учетной записи.\n\n" .
                "Для создания нового пароля перейдите по ссылке: {$resetUrl}\n\n" .
                "Ссылка действительна в течение 1 часа.\n\n" .
                "Если вы не запрашивали сброс пароля, проигнорируйте это письмо.";

            $mail->send();
            return true;
        } catch (\Exception $e) {
            error_log("Error sending password reset email: " . $e->getMessage());
            throw $e;
        }
    }

    // Новый метод для установки нового пароля
    public function setNewPassword() {
        try {
            $requestBody = Flight::request()->getBody();
            $data = json_decode($requestBody, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return Flight::json([
                    'success' => false,
                    'message' => 'Неверный формат данных'
                ], 400);
            }

            if (!isset($data['token']) || !isset($data['password'])) {
                return Flight::json([
                    'success' => false,
                    'message' => 'Отсутствует токен или новый пароль'
                ], 400);
            }

            $token = trim($data['token']);
            $password = trim($data['password']);

            // Проверка длины пароля
            if (strlen($password) < 8) {
                return Flight::json([
                    'success' => false,
                    'message' => 'Пароль должен содержать минимум 8 символов'
                ], 400);
            }

            // Проверяем токен
            $stmt = $this->db->prepare("
                SELECT prt.login_id, l.email 
                FROM password_reset_tokens prt
                JOIN logins l ON l.id = prt.login_id
                WHERE prt.token = ? 
                    AND prt.expires_at > NOW() 
                    AND prt.used = FALSE
                LIMIT 1
            ");
            $stmt->execute([$token]);
            $result = $stmt->fetch();

            if (!$result) {
                return Flight::json([
                    'success' => false,
                    'message' => 'Недействительная или устаревшая ссылка для сброса пароля'
                ], 400);
            }

            // Хешируем новый пароль
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Начинаем транзакцию
            $this->db->beginTransaction();

            try {
                // Обновляем пароль
                $stmt = $this->db->prepare("
                    UPDATE logins 
                    SET password = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$hashedPassword, $result['login_id']]);

                // Помечаем токен как использованный
                $stmt = $this->db->prepare("
                    UPDATE password_reset_tokens 
                    SET used = TRUE 
                    WHERE token = ?
                ");
                $stmt->execute([$token]);

                // Удаляем все старые неиспользованные токены для этого пользователя
                $stmt = $this->db->prepare("
                    DELETE FROM password_reset_tokens 
                    WHERE login_id = ? 
                    AND used = FALSE
                ");
                $stmt->execute([$result['login_id']]);

                $this->db->commit();

                return Flight::json([
                    'success' => true,
                    'message' => 'Пароль успешно изменен'
                ]);

            } catch (\Exception $e) {
                $this->db->rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            error_log("Set new password error: " . $e->getMessage());
            return Flight::json([
                'success' => false,
                'message' => 'Произошла ошибка при смене пароля'
            ], 500);
        }
    }
}
