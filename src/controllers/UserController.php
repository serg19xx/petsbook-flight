<?php

namespace App\Controllers;

use PDO;
use Flight;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class UserController {
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

            // Загружаем настройки аватаров
            $this->avatarSettings = require __DIR__ . '/../config/avatar.php';
            if (!isset($this->avatarSettings['settings'])) {
                throw new \Exception('Avatar configuration not found');
            }
            $this->avatarSettings = $this->avatarSettings['settings'];

        } catch (\PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw new \Exception('Database connection failed: ' . $e->getMessage());
        }
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
            
            if (!isset($_ENV['JWT_SECRET'])) {
                throw new \Exception('JWT configuration is missing');
            }
            
            try {
                $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
            } catch (\Exception $e) {
                return Flight::json([
                    'success' => false,
                    'message' => 'Invalid token'
                ], 401);
            }
            
            $userId = $decoded->id;
            $userRole = $decoded->role;
            
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
            
            // Получаем данные пользователя
            $stmt = $this->db->prepare("SELECT * FROM {$table} WHERE login_id = :id");
            $stmt->execute([':id' => $userId]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$userData) {
                return Flight::json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Обработка аватара
            if (empty($userData['avatar'])) {
                $userData['avatar'] = $this->generateDicebearUrl($userData);
            } else {
                $avatarUrl = $userData['avatar'];
                
                if (filter_var($avatarUrl, FILTER_VALIDATE_URL)) {
                    $headers = @get_headers($avatarUrl);
                    if (!$headers || strpos($headers[0], '200') === false) {
                        $userData['avatar'] = $this->generateDicebearUrl($userData);
                    }
                } else {
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
            return Flight::json([
                'success' => false,
                'message' => 'Error getting user data: ' . $e->getMessage()
            ], 500);
        }
    }

    // Заготовки для будущих методов
    public function getAllUsers() {
        // TODO: Реализовать получение списка пользователей с пагинацией и фильтрацией
    }

    public function updateUser() {
        // TODO: Реализовать обновление данных пользователя
    }

    public function deleteUser() {
        // TODO: Реализовать удаление пользователя
    }

    public function searchUsers() {
        // TODO: Реализовать поиск пользователей
    }

    private function generateDicebearUrl($userData) {
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

        if (isset($userData['gender'])) {
            $gender = strtolower($userData['gender']);
            
            if (isset($defaultSeeds[$gender])) {
                $params['seed'] = $defaultSeeds[$gender];
            } else {
                $params['seed'] = $defaultSeeds['other'];
            }
        } else {
            $params['seed'] = $defaultSeeds['other'];
        }

        $baseUrl = $this->avatarSettings['baseUrl'];
        $style = $this->avatarSettings['style'];
        
        return "{$baseUrl}/{$style}/svg?" . http_build_query($params);
    }
}