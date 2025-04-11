<?php

namespace App\Controllers;

use PDO;
use Flight;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * API Response Codes
 * 
 * Success codes:
 * - USER_DATA_SUCCESS: Successfully retrieved user data
 * - USERS_LIST_SUCCESS: Successfully retrieved users list
 * - USER_UPDATE_SUCCESS: Successfully updated user data
 * - USER_DELETE_SUCCESS: Successfully deleted user
 * - USERS_SEARCH_SUCCESS: Successfully performed user search
 * 
 * Authentication error codes:
 * - TOKEN_NOT_PROVIDED: Token was not provided
 * - INVALID_TOKEN: Invalid token
 * - JWT_CONFIG_MISSING: JWT configuration is missing
 * 
 * User error codes:
 * - USER_NOT_FOUND: User not found
 * - INVALID_ROLE: Invalid user role
 * 
 * System error codes:
 * - SYSTEM_ERROR: System error
 */
class UserController {
    /** @var array Avatar upload and processing settings */
    private $avatarSettings;
    
    /** @var PDO Database connection instance */
    private $db;

    public function __construct() {
        try {
            // Database connection
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

            // Load avatar settings
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

    /**
     * Get current user data
     * 
     * @return void JSON response with user data
     * 
     * @api {get} /api/user/getuser Get user data
     * @apiHeader {String} Authorization JWT token
     * 
     * @apiSuccess {Object} user User information
     * 
     * @apiError {String} error_code Error code (USER_NOT_FOUND, INVALID_TOKEN, etc.)
     */
    public function getUserData() {
        try {
            // Получаем и проверяем токен
            $headers = getallheaders();
            $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
            
            if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                return Flight::json([
                    'status' => 401,
                    'error_code' => 'TOKEN_NOT_PROVIDED',
                    'message' => 'Authorization token is required',
                    'data' => null
                ], 401);
            }

            $token = $matches[1];
            
            if (!isset($_ENV['JWT_SECRET'])) {
                return Flight::json([
                    'status' => 500,
                    'error_code' => 'JWT_CONFIG_MISSING',
                    'message' => 'JWT configuration is missing',
                    'data' => null
                ], 500);
            }
            
            try {
                $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
            } catch (\Exception $e) {
                return Flight::json([
                    'status' => 401,
                    'error_code' => 'INVALID_TOKEN',
                    'message' => 'Invalid token',
                    'data' => null
                ], 401);
            }
            
            // Вызываем хранимую процедуру
            $stmt = $this->db->prepare("CALL sp_GetUserData(:login_id)");
            $stmt->execute([':login_id' => $decoded->id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Проверяем результат
            if (!$result) {
                throw new \Exception('No data returned from database');
            }

            // Декодируем JSON из поля data
            $userData = json_decode($result['data'], true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Failed to decode JSON data: ' . json_last_error_msg());
            }

            // СОХРАНЯЕМ существующую обработку аватара
            if (empty($userData['user']['avatar'])) {
                $userData['user']['avatar'] = $this->generateDicebearUrl($userData['user']);
            } else {
                $avatarUrl = $userData['user']['avatar'];
                
                if (filter_var($avatarUrl, FILTER_VALIDATE_URL)) {
                    $headers = @get_headers($avatarUrl);
                    if (!$headers || strpos($headers[0], '200') === false) {
                        $userData['user']['avatar'] = $this->generateDicebearUrl($userData['user']);
                    }
                } else {
                    $localPath = $_SERVER['DOCUMENT_ROOT'] . $avatarUrl;
                    if (!file_exists($localPath)) {
                        $userData['user']['avatar'] = $this->generateDicebearUrl($userData['user']);
                    }
                }
            }

            // Возвращаем успешный ответ
            return Flight::json([
                'status' => 200,
                'error_code' => null,
                'message' => null,
                'data' => $userData
            ], 200);
            
        } catch (\Exception $e) {
            error_log("Get user data error: " . $e->getMessage());
            return Flight::json([
                'status' => 500,
                'error_code' => 'SYSTEM_ERROR',
                'message' => $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    public function getAllUsers() {
        try {
            // TODO: Implement getting user list with pagination and filtering
            return Flight::json([
                'status' => 200,
                'error_code' => 'USERS_LIST_SUCCESS',
                'message' => '',
                'data' => [
                    'users' => []
                ]
            ], 200);
        } catch (\Exception $e) {
            error_log("Get all users error: " . $e->getMessage());
            return Flight::json([
                'status' => 500,
                'error_code' => 'SYSTEM_ERROR',
                'message' => '',
                'data' => null
            ], 500);
        }
    }

    /**
     * Update user profile
     * 
     * @return void JSON response with updated user data
     * 
     * @api {put} /api/user/update Update user profile
     * @apiHeader {String} Authorization JWT token
     * @apiBody {Object} userData Updated user information
     * 
     * @apiSuccess {Object} user Updated user information
     * 
     * @apiError {String} error_code Error code (INVALID_DATA, UPDATE_FAILED, etc.)
     */
    public function updateUser() {
        try {
            // TODO: Implement user data update
            return Flight::json([
                'status' => 200,
                'error_code' => 'USER_UPDATE_SUCCESS',
                'message' => '',
                'data' => [
                    'user' => []
                ]
            ], 200);
        } catch (\Exception $e) {
            error_log("Update user error: " . $e->getMessage());
            return Flight::json([
                'status' => 500,
                'error_code' => 'SYSTEM_ERROR',
                'message' => '',
                'data' => null
            ], 500);
        }
    }

    public function deleteUser() {
        try {
            // TODO: Implement user deletion
            return Flight::json([
                'status' => 200,
                'error_code' => 'USER_DELETE_SUCCESS',
                'message' => '',
                'data' => null
            ], 200);
        } catch (\Exception $e) {
            error_log("Delete user error: " . $e->getMessage());
            return Flight::json([
                'status' => 500,
                'error_code' => 'SYSTEM_ERROR',
                'message' => '',
                'data' => null
            ], 500);
        }
    }

    public function searchUsers() {
        try {
            // TODO: Implement user search
            return Flight::json([
                'status' => 200,
                'error_code' => 'USERS_SEARCH_SUCCESS',
                'message' => '',
                'data' => [
                    'users' => []
                ]
            ], 200);
        } catch (\Exception $e) {
            error_log("Search users error: " . $e->getMessage());
            return Flight::json([
                'status' => 500,
                'error_code' => 'SYSTEM_ERROR',
                'message' => '',
                'data' => null
            ], 500);
        }
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
