<?php

namespace App\Controllers;

use PDO;
use Flight;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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
     */
    public function updateUser() {
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

            // Декодируем токен используя $_ENV['JWT_SECRET'] вместо $this->jwtKey
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

            // Получаем данные из тела запроса
            $data = json_decode(Flight::request()->getBody(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return Flight::json([
                    'status' => 400,
                    'error_code' => 'INVALID_JSON',
                    'message' => 'Invalid JSON data',
                    'data' => null
                ], 400);
            }

            // Подготавливаем параметры для процедуры
            $params = [
                ':p_login_id' => $decoded->id,
                ':p_full_name' => isset($data['fullName']) ? trim($data['fullName']) : '',
                ':p_nickname' => isset($data['nickname']) ? trim($data['nickname']) : '',
                ':p_gender' => isset($data['gender']) ? trim($data['gender']) : 'Male',
                ':p_birth_date' => isset($data['birthDate']) ? trim($data['birthDate']) : null,
                ':p_about_me' => isset($data['aboutMe']) ? trim($data['aboutMe']) : '',
                ':p_contact_email' => isset($data['contactEmail']) ? trim($data['contactEmail']) : '',
                ':p_phone' => isset($data['phone']) ? trim($data['phone']) : '',
                ':p_website' => isset($data['website']) ? trim($data['website']) : ''
            ];

            // Обработка данных местоположения
            if (isset($data['location'])) {
                $location = $data['location'];
                $params[':p_full_address'] = isset($location['fullAddress']) ? trim($location['fullAddress']) : '';
                
                // Координаты
                if (isset($location['coordinates'])) {
                    $params[':p_latitude'] = isset($location['coordinates']['lat']) ? $location['coordinates']['lat'] : null;
                    $params[':p_longitude'] = isset($location['coordinates']['lng']) ? $location['coordinates']['lng'] : null;
                } else {
                    $params[':p_latitude'] = null;
                    $params[':p_longitude'] = null;
                }

                // Компоненты адреса
                if (isset($location['components'])) {
                    $components = $location['components'];
                    $params[':p_street_name'] = isset($components['streetName']) ? trim($components['streetName']) : '';
                    $params[':p_street_numb'] = isset($components['streetNumber']) ? trim($components['streetNumber']) : '';
                    $params[':p_unit_numb'] = isset($components['unitNumber']) ? trim($components['unitNumber']) : '';
                    $params[':p_city'] = isset($components['city']) ? trim($components['city']) : '';
                    $params[':p_district'] = isset($components['district']) ? trim($components['district']) : '';
                    $params[':p_region'] = isset($components['region']) ? trim($components['region']) : '';
                    $params[':p_region_code'] = isset($components['regionCode']) ? trim($components['regionCode']) : '';
                    $params[':p_postcode'] = isset($components['postcode']) ? trim($components['postcode']) : '';
                    $params[':p_country'] = isset($components['country']) ? trim($components['country']) : '';
                    $params[':p_country_code'] = isset($components['countryCode']) ? trim($components['countryCode']) : '';
                }
            } else {
                $params[':p_full_address'] = '';
                $params[':p_latitude'] = null;
                $params[':p_longitude'] = null;
                $params[':p_street_name'] = '';
                $params[':p_street_numb'] = '';
                $params[':p_unit_numb'] = '';
                $params[':p_city'] = '';
                $params[':p_district'] = '';
                $params[':p_region'] = '';
                $params[':p_region_code'] = '';
                $params[':p_postcode'] = '';
                $params[':p_country'] = '';
                $params[':p_country_code'] = '';
            }

            // Вызываем хранимую процедуру
            $stmt = $this->db->prepare("CALL sp_UpdateUserProfile(" . implode(',', array_keys($params)) . ")");
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result === false) {
                throw new \Exception('Failed to update user profile');
            }

            // Декодируем JSON данные из результата
            $resultData = json_decode($result['data'], true);

            return Flight::json([
                'status' => 200,
                'error_code' => null,
                'message' => 'Profile updated successfully',
                'data' => $resultData
            ], 200);

        } catch (\Exception $e) {
            error_log("Update user error: " . $e->getMessage());
            return Flight::json([
                'status' => 500,
                'error_code' => 'SYSTEM_ERROR',
                'message' => $e->getMessage(),
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

    private function sendContactEmailVerification($email, $name, $token) {
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
            $verifyUrl = "http://localhost:5173/verify-contact-email/" . $token;
            
            $mail->isHTML(true);
            $mail->Subject = 'Подтверждение контактного email адреса';
            $mail->Body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h2>Здравствуйте, {$name}!</h2>
                    <p>Вы изменили контактный email адрес в вашем профиле.</p>
                    <p>Для подтверждения нового адреса, пожалуйста, нажмите на кнопку ниже:</p>
                    <p style='text-align: center;'>
                        <a href='{$verifyUrl}' 
                           style='display: inline-block; padding: 10px 20px; 
                                  background-color: #4CAF50; color: white; 
                                  text-decoration: none; border-radius: 5px;'>
                            Подтвердить email
                        </a>
                    </p>
                    <p>Или перейдите по ссылке: <a href='{$verifyUrl}'>{$verifyUrl}</a></p>
                    <p>Ссылка действительна в течение 24 часов.</p>
                    <p>Если вы не меняли контактный email, пожалуйста, проигнорируйте это письмо.</p>
                </div>";

            $mail->AltBody = "Здравствуйте, {$name}!\n\n" .
                "Вы изменили контактный email адрес в вашем профиле.\n\n" .
                "Для подтверждения нового адреса перейдите по ссылке: {$verifyUrl}\n\n" .
                "Ссылка действительна в течение 24 часов.\n\n" .
                "Если вы не меняли контактный email, пожалуйста, проигнорируйте это письмо.";

            $mail->send();
            return true;
        } catch (\Exception $e) {
            error_log("Error sending contact email verification: " . $e->getMessage());
            throw $e;
        }
    }

    private function createContactEmailVerificationToken($userId) {
        try {
            $token = bin2hex(random_bytes(32));
            $stmt = $this->db->prepare("
                INSERT INTO contact_email_verification_tokens (user_id, token, expires_at)
                VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))
            ");
            $stmt->execute([$userId, $token]);
            return $token;
        } catch (\Exception $e) {
            error_log("Token creation error: " . $e->getMessage());
            throw $e;
        }
    }
}
