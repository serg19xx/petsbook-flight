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
            // Подключение к БД
            $this->db = new PDO(
                "mysql:host=" . $_ENV['DB_HOST'] . 
                ";dbname=" . $_ENV['DB_NAME'] . 
                ";charset=utf8mb4",
                $_ENV['DB_USER'],
                $_ENV['DB_PASSWORD'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
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
                return $this->error('INVALID_JSON_FORMAT');
            }

            if (empty($data['email']) || empty($data['password']) || 
                empty($data['name']) || empty($data['role'])) {
                return $this->error('MISSING_REQUIRED_FIELDS');
            }

            $allowedRoles = ['admin', 'moderator', 'partner', 'commercial', 'user'];
            if (!in_array($data['role'], $allowedRoles)) {
                return $this->error('INVALID_ROLE');
            }

            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return $this->error('INVALID_EMAIL_FORMAT');
            }

            if (strlen($data['password']) < 6) {
                return $this->error('PASSWORD_TOO_SHORT');
            }

            $name = trim($data['name']);
            $email = trim($data['email']);
            // Хешируем пароль перед сохранением
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT, ['cost' => 12]);
            $role = $data['role'];

            // Проверка формата email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return Flight::json([
                    'success' => false,
                    'message' => 'Неверный формат email'
                ], 400);
            }

            // Проверка длины пароля
            if (strlen($data['password']) < 6) {
                return Flight::json([
                    'success' => false,
                    'message' => 'Пароль должен быть не менее 6 символов'
                ], 400);
            }

            // Вызываем хранимую процедуру с хешированным паролем
            $stmt = $this->db->prepare("CALL sp_Register(?, ?, ?, ?)");
            $stmt->execute([$name, $email, $hashedPassword, $role]);
            
            $result = $stmt->fetch();
            
            if ($result && !isset($result['error'])) {
                // Создаем токен верификации
                $token = $this->createVerificationToken($result['id']);
                
                // Отправляем email для верификации
                $this->sendVerificationEmail($email, $name, $token);
                
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
                ]);
            } else {
                throw new \Exception($result['error'] ?? 'Ошибка при регистрации пользователя');
            }

        } catch (\Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            return $this->error('SYSTEM_ERROR', 500);
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
                return $this->error('EMAIL_REQUIRED');
            }

            $email = trim($data['email']);

            // Проверяем существование пользователя
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if (!$stmt->fetch()) {
                // Для безопасности возвращаем тот же ответ, что и при успехе
                return Flight::json([
                    'success' => true,
                    'message' => 'Если указанный email существует, инструкции по сбросу пароля будут отправлены'
                ]);
            }

            // TODO: Здесь должна быть логика отправки email
            // Пока просто логируем
            error_log("Password reset requested for email: " . $email);

            return Flight::json([
                'success' => true,
                'message' => 'Если указанный email существует, инструкции по сбросу пароля будут отправлены'
            ]);

        } catch (\Exception $e) {
            error_log("Password reset error: " . $e->getMessage());
            return $this->error('SYSTEM_ERROR', 500);
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
        $token = bin2hex(random_bytes(32)); // 64 символа
        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $stmt = $this->db->prepare("
            INSERT INTO email_verification_tokens (user_id, token, expires_at) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$userId, $token, $expires]);
        
        return $token;
    }

    private function sendVerificationEmail($email, $name, $token) {
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

            // Формируем ссылку для верификации
            $verificationUrl = $_ENV['APP_URL'] . "/verify-email/" . $token;

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Подтверждение email адреса';
            
            $mail->Body = "
            <html>
            <head>
                <title>Подтверждение email адреса</title>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .button { 
                        display: inline-block; 
                        padding: 10px 20px; 
                        background-color: #2563eb; 
                        color: white; 
                        text-decoration: none; 
                        border-radius: 5px; 
                    }
                </style>
            </head>
            <body>
                <div class='container'>
                    <h2>Здравствуйте, {$name}!</h2>
                    <p>Для завершения регистрации необходимо подтвердить ваш email адрес.</p>
                    <p>Пожалуйста, нажмите на кнопку ниже:</p>
                    <p>
                        <a href='{$verificationUrl}' class='button'>Подтвердить email</a>
                    </p>
                    <p>Или перейдите по ссылке: {$verificationUrl}</p>
                    <p>Ссылка действительна в течение 24 часов.</p>
                    <p>Если вы не регистрировались на нашем сайте, просто проигнорируйте это письмо.</p>
                </div>
            </body>
            </html>
            ";

            $mail->AltBody = "Здравствуйте, {$name}!\n\n" .
                "Для завершения регистрации необходимо подтвердить ваш email адрес.\n\n" .
                "Перейдите по ссылке: {$verificationUrl}\n\n" .
                "Ссылка действительна в течение 24 часов.\n\n" .
                "Если вы не регистрировались на нашем сайте, просто проигнорируйте это письмо.";

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Error sending verification email: " . $e->getMessage());
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
}
