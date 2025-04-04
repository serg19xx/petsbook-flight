<?php

namespace App\Controllers;

use PDO;
use Flight;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthController {
    private $avatarSettings;
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

            // Загружаем настройки аватаров из конфигурационного файла
            $this->avatarSettings = require __DIR__ . '/../config/avatar.php';
            if (!isset($this->avatarSettings['settings'])) {
                throw new \Exception('Avatar configuration not found');
            }
            $this->avatarSettings = $this->avatarSettings['settings'];

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

            if (!$user || !isset($user['stored_password'])) {  // Изменено с password на stored_password
                error_log("User not found or password field missing");
                return Flight::json([
                    'success' => false,
                    'message' => 'Неверные учетные данные'
                ], 401);
            }

            // Проверяем пароль
            if (!password_verify($password, $user['stored_password'])) {  // Изменено с password на stored_password
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
        $data = \Flight::request()->data;
        
        // Базовая валидация
        if (empty($data->email) || empty($data->password) || empty($data->name)) {
            return $this->error('Name, email and password are required');
        }

        // TODO: Реализовать логику регистрации
        return $this->success([], 'Registration successful');
    }

    public function logout() {
        // TODO: Реализовать логику выхода
        return $this->success([], 'Logout successful');
    }

    public function passwordReset() {
        $data = \Flight::request()->data;
        
        if (empty($data->email)) {
            return $this->error('Email is required');
        }

        // TODO: Реализовать логику сброса пароля
        return $this->success([], 'Password reset instructions sent');
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

    public function getUserData() {
        try {
            $headers = getallheaders();
            $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
            
            if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                return Flight::json([
                    'success' => false,
                    'message' => 'Token not provided'
                ], 401);
            }

            $token = $matches[1];
            
            // Проверяем наличие JWT_SECRET
            if (!isset($_ENV['JWT_SECRET'])) {
                throw new \Exception('JWT configuration is missing');
            }
            
            try {
                $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
                error_log("Decoded token data: " . print_r($decoded, true));
            } catch (\Exception $e) {
                return Flight::json([
                    'success' => false,
                    'message' => 'Invalid token'
                ], 401);
            }
            
            $userId = $decoded->id;
            $userRole = $decoded->role;
            
            error_log("User ID: {$userId}, Role: {$userRole}");
            
            // Получаем имя таблицы на основе роли
            $stmt = $this->db->prepare("SELECT user_tbl FROM role_table WHERE role = :role");
            $stmt->execute([':role' => $userRole]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                return Flight::json([
                    'success' => false,
                    'message' => 'Invalid role'
                ], 400);
            }
            
            $table = $result['user_tbl'];
            error_log("Using table: {$table}");
            
            // Получаем данные пользователя используя login_id
            $stmt = $this->db->prepare("SELECT * FROM {$table} WHERE login_id = :id");
            $stmt->execute([':id' => $userId]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$userData) {
                error_log("User not found with login_id: {$userId} in table: {$table}");
                return Flight::json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            error_log("Found user data: " . print_r($userData, true));

            // Обработка аватара
            if (empty($userData['avatar'])) {
                $userData['avatar'] = $this->generateDicebearUrl($userData);
            } else {
                // Проверяем доступность существующего аватара
                $avatarUrl = $userData['avatar'];
                
                if (filter_var($avatarUrl, FILTER_VALIDATE_URL)) {
                    // Для URL аватара
                    $headers = @get_headers($avatarUrl);
                    if (!$headers || strpos($headers[0], '200') === false) {
                        $userData['avatar'] = $this->generateDicebearUrl($userData);
                    }
                } else {
                    // Для локального файла
                    $localPath = $_SERVER['DOCUMENT_ROOT'] . $avatarUrl;
                    if (!file_exists($localPath)) {
                        $userData['avatar'] = $this->generateDicebearUrl($userData);
                    }
                }
            }

            // Форматируем данные пользователя
            $formattedUserData = [
                'login_id' => (int)$userData['login_id'],
                'name' => $userData['name'],
                'dob' => $userData['dob'],
                'gender' => $userData['gender'],
                'appt' => $userData['appt'],
                'street' => $userData['street'],
                'city' => $userData['city'],
                'subdivision' => $userData['subdivision'],
                'subdivision_code' => $userData['subdivision_code'],
                'country' => $userData['country'],
                'country_code2' => $userData['country_code2'],
                'postal' => $userData['postal'],
                'cellPhone' => $userData['cellPhone'],
                'avatar' => $userData['avatar'],
                'date_created' => $userData['date_created'],
                'date_updated' => $userData['date_updated']
            ];

            return Flight::json([
                'success' => true,
                'user' => $formattedUserData
            ]);

        } catch (\Exception $e) {
            error_log("Get user data error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return Flight::json([
                'success' => false,
                'message' => 'Error getting user data: ' . $e->getMessage()
            ], 500);
        }
    }

    private function generateDicebearUrl($userData) {
        // Предопределенные seed для разных полов
        $defaultSeeds = [
            'female' => 'Destiny',
            'male' => 'Christopher',
            'other' => 'Alex'
        ];

        $params = [
            'backgroundColor' => $this->avatarSettings['backgroundColor'],
            'radius' => '50',
            'size' => $this->avatarSettings['size']
        ];

        // Определяем seed на основе пола
        if (isset($userData['gender'])) {
            $gender = strtolower($userData['gender']);
            
            if (isset($defaultSeeds[$gender])) {
                // Используем только предопределенный seed
                $params['seed'] = $defaultSeeds[$gender];
            } else {
                // Если пол не определен, используем нейтральный seed
                $params['seed'] = $defaultSeeds['other'];
            }
        } else {
            // Если пол не указан, используем нейтральный seed
            $params['seed'] = $defaultSeeds['other'];
        }

        $baseUrl = $this->avatarSettings['baseUrl'];
        $style = $this->avatarSettings['style'];
        
        return "{$baseUrl}/{$style}/svg?" . http_build_query($params);
    }
}
