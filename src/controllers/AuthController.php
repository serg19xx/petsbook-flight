<?php

namespace App\Controllers;

use PDO;
use Flight;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

class AuthController {
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

    public function login() {
        try {
            error_log("Starting login process");
            
            // Получаем данные запроса
            $requestBody = Flight::request()->getBody();
            error_log("Raw request body: " . $requestBody);
            
            $data = json_decode($requestBody, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON decode error: " . json_last_error_msg());
                throw new \Exception('Invalid JSON data');
            }

            error_log("Parsed login data: " . print_r($data, true));

            // Валидация входных данных
            if (!isset($data['email']) || !isset($data['password'])) {
                error_log("Missing required fields");
                return Flight::json([
                    'success' => false,
                    'message' => 'Email и пароль обязательны'
                ], 400);
            }

            $email = trim($data['email']);
            $password = $data['password'];

            error_log("Attempting to find user with email: " . $email);

            // Проверяем подключение к БД
            if (!$this->db) {
                error_log("Database connection is not available");
                throw new \Exception('Database connection is not available');
            }

            // Вызываем существующую хранимую процедуру
            $stmt = $this->db->prepare("CALL sp_Login(:email)");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            error_log("User data from procedure: " . print_r($user, true));

            if (!$user || !isset($user['stored_password'])) {
                error_log("User not found or password field missing");
                return Flight::json([
                    'success' => false,
                    'message' => 'Неверные учетные данные'
                ], 401);
            }

            // Проверяем пароль
            if (!password_verify($password, $user['stored_password'])) {
                error_log("Password verification failed");
                return Flight::json([
                    'success' => false,
                    'message' => 'Неверные учетные данные'
                ], 401);
            }

            error_log("Password verified successfully");

            // Проверяем наличие JWT_SECRET
            if (!isset($_ENV['JWT_SECRET'])) {
                error_log("JWT_SECRET is not set");
                throw new \Exception('JWT configuration is missing');
            }

            // Генерируем токен
            $token = $this->generateJWT([
                'id' => $user['id'],
                'email' => $user['email'],
                'role' => $user['role']
            ]);

            error_log("JWT token generated successfully");

            // Возвращаем успешный ответ
            return Flight::json([
                'success' => true,
                'token' => $token,
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'role' => $user['role']
                ]
            ]);

        } catch (\Exception $e) {
            error_log("Login error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            return Flight::json([
                'success' => false,
                'message' => 'Ошибка аутентификации: ' . ($e->getMessage()),
                'debug' => $_ENV['APP_DEBUG'] ? [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ] : null
            ], 500);
        }
    }

    public function register() {
        try {
            $requestBody = Flight::request()->getBody();
            $data = json_decode($requestBody, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON data');
            }

            // Валидация входных данных
            if (empty($data['email']) || empty($data['password']) || empty($data['name']) || empty($data['role'])) {
                return Flight::json([
                    'success' => false,
                    'message' => 'Все поля обязательны для заполнения'
                ], 400);
            }

            // Проверяем корректность role
            $allowedRoles = ['admin', 'moderator', 'partner', 'commercial', 'user'];
            if (!in_array($data['role'], $allowedRoles)) {
                return Flight::json([
                    'success' => false,
                    'message' => 'Некорректная роль пользователя'
                ], 400);
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
                // Send welcome email
                $this->sendWelcomeEmail($email, $name);
                return Flight::json([
                    'success' => true,
                    'message' => 'Регистрация успешно завершена'
                ]);
            } else {
                throw new \Exception($result['error'] ?? 'Ошибка при регистрации пользователя');
            }

        } catch (\Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            return Flight::json([
                'success' => false,
                'message' => 'Ошибка при регистрации',
                'debug' => $_ENV['APP_DEBUG'] ? $e->getMessage() : null
            ], 500);
        }
    }

    public function logout() {
        // TODO: Реализовать логику выхода
        return $this->success([], 'Logout successful');
    }

    public function passwordReset() {
        try {
            // Получаем данные запроса
            $requestBody = Flight::request()->getBody();
            $data = json_decode($requestBody, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON data');
            }

            // Проверяем наличие email
            if (empty($data['email'])) {
                return Flight::json([
                    'success' => false,
                    'message' => 'Email обязателен'
                ], 400);
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
            return Flight::json([
                'success' => false,
                'message' => 'Ошибка при обработке запроса',
                'debug' => $_ENV['APP_DEBUG'] ? $e->getMessage() : null
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
}
