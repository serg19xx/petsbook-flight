<?php

namespace App\Controllers;
require __DIR__ . '/../../vendor/autoload.php';

use App\Utils\Logger;
use PDO;
use Flight;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
//use PHPMailer\PHPMailer\PHPMailer;
//use PHPMailer\PHPMailer\Exception;
//use PHPMailer\PHPMailer\SMTP;
use App\Constants\ResponseCodes;
use App\Mail\MailService;
use App\Mail\DTOs\PersonalizedRecipient;


/**
 * Authentication Controller
 * 
 * Handles all authentication-related operations including login, registration,
 * password reset, and email verification.
 */
class AuthController extends BaseController {
    /** @var PDO Database connection instance */
    private $db;
    private $request;

    /**
     * Constructor initializes database connection
     * 
     * @throws \Exception When database connection fails
     */
    public function __construct($db) {
        $this->request = Flight::request();
        $this->db = $db;

        Logger::info("AuthController initialized", "AuthController");
    }

    /**
     * API Response Codes
     * 
     * Success codes:
     * - LOGIN_SUCCESS: Successful authorization
     * - REGISTRATION_SUCCESS: Successful registration
     * - USER_DATA_SUCCESS: Successfully retrieved user data
     * - PASSWORD_RESET_SUCCESS: Successfully reset password
     * - EMAIL_VERIFICATION_SUCCESS: Successfully verified email
     * 
     * Authentication error codes:
     * - MISSING_CREDENTIALS: Missing email or password
     * - INVALID_CREDENTIALS: Invalid email or password
     * - LOGIN_FAILED: Login error (general)
     * - ACCOUNT_INACTIVE: Account is inactive
     * - EMAIL_NOT_VERIFIED: Email not verified
     * - ACCOUNT_BLOCKED: Account is blocked
     * - EMAIL_ALREADY_EXISTS: Email address is already registered in the system
     * 
     * Token error codes:
     * - TOKEN_NOT_PROVIDED: Token was not provided
     * - INVALID_TOKEN: Invalid token
     * - TOKEN_EXPIRED: Token has expired
     * 
     * User error codes:
     * - USER_NOT_FOUND: User not found
     * - INVALID_ROLE: Invalid user role
     * - EMAIL_ALREADY_EXISTS: Email already exists
     * - INVALID_USER_DATA: Invalid user data
     * 
     * System error codes:
     * - SYSTEM_ERROR: System error
     * - DATABASE_ERROR: Database error
     * - EMAIL_SEND_ERROR: Email sending error
     */
    /**
     * Handles user login
     * 
     * @return void JSON response with user data and JWT token on success
     * 
     * @api {post} /api/auth/login Login user
     * @apiBody {String} email User's email
     * @apiBody {String} password User's password
     * 
     * @apiSuccess {String} token JWT authentication token
     * @apiSuccess {Object} user User information
     * 
     * @apiError {String} error_code Error code (INVALID_EMAIL, INVALID_PASSWORD, etc.)
     */
    public function login() {
        Logger::info("=== LOGIN ===", "AuthController");
        
        try {
            $requestBody = Flight::request()->getBody();
            $data = json_decode($requestBody, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return Flight::json([
                    'status' => 400,
                    'error_code' => ResponseCodes::INVALID_REQUEST,
                    'message' => 'Invalid JSON data',
                    'data' => null
                ], 400);
            }
            
            if (!isset($data['email']) || !isset($data['password'])) {
                return Flight::json([
                    'status' => 400,
                    'error_code' => ResponseCodes::MISSING_CREDENTIALS,
                    'message' => 'Email and password are required',
                    'data' => null
                ], 400);
            }

            Logger::info("Login attempt Data: " . json_encode([
                'email' => $data['email']
            ]), "AuthController");
            
            // Вызываем хранимую процедуру
            $stmt = $this->db->prepare("CALL sp_Login(?)");
            $stmt->execute([$data['email']]);
            $result = $stmt->fetch();
            $stmt->closeCursor();

            Logger::info("Login procedure result: " . json_encode($result), "AuthController");
            
            if (!$result['success']) {
                $response = [
                    'status' => 400,
                    'error_code' => $result['error_code'],
                    'message' => $result['message'],
                    'data' => null
                ];
                
                Logger::info("Sending error response", "AuthController", [
                    'response' => $response,
                    'result' => $result
                ]);
                
                return Flight::json($response, 400);
            }

            // Проверяем пароль
            if (!password_verify($data['password'], $result['stored_password'])) {
                return Flight::json([
                    'status' => 400,
                    'error_code' => ResponseCodes::INVALID_PASSWORD,
                    'message' => 'Invalid password',
                    'data' => null
                ], 400);
            }

            // Генерируем JWT токен
            $token = $this->generateJWT([
                'user_id' => $result['id'],
                'role' => $result['role']
            ]);

            Logger::info("Login successful", "AuthController", [
                'user_id' => $result['id'],
                'role' => $result['role']
            ]);

            $id = $result['id'];
            $role = $result['role'];

            // Initialize variables for file checks
            $fileName = $role . '-' . $id;
            $possibleExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            $avatarExists = false;
            $coverExists = false;
            $avatar = null; // Инициализируем переменную

            // Check avatar
            foreach ($possibleExtensions as $ext) {
                $avatarPath = '/profile-images/avatars/' . $fileName . '.' . $ext;
                $localPath = $_SERVER['DOCUMENT_ROOT'] . $avatarPath;
                
                Logger::info("Checking avatar path", "AuthController", [
                    'localPath' => $localPath,
                    'exists' => file_exists($localPath)
                ]);
                
                if (file_exists($localPath)) {
                    $avatar = 'http://' . $_SERVER['HTTP_HOST'] . $avatarPath;
                    $avatarExists = true;
                    break;
                }
            }

            if($token){
                $isLocal = $_ENV['APP_ENV'] === 'local' || $_ENV['APP_ENV'] === 'development';
                
                // Добавляем отладочную информацию
                Logger::info("Setting auth cookie", "AuthController", [
                    'token_length' => strlen($token),
                    'domain' => $isLocal ? 'localhost' : '.petsbook.ca',
                    'secure' => !$isLocal,
                    'samesite' => $isLocal ? 'Lax' : 'None',
                    'env' => $_ENV['APP_ENV'] ?? 'not set'
                ]);

                setcookie('auth_token', $token, [
                    'expires' => time() + 7*24*60*60,
                    'path' => '/',
                    'domain' => $isLocal ? 'localhost' : '.petsbook.ca',
                    'secure' => !$isLocal,           // только по https! кроме локальной разработки
                    'httponly' => true,         // не доступно из JS
                    'samesite' => $isLocal ? 'Lax' : 'None'  // для локальной разработки используем Lax
                ]);    

                // Проверяем, установилась ли кука
                $cookieSet = isset($_COOKIE['auth_token']);
                Logger::info("Cookie set status", "AuthController", [
                    'cookie_set' => $cookieSet,
                    'cookie_value' => $cookieSet ? 'exists' : 'missing'
                ]);

                // Добавляем заголовок для отладки
                header('X-Set-Cookie-Debug: auth_token set with domain=' . ($isLocal ? 'localhost' : '.petsbook.ca'));
            }    

            return Flight::json([
                'status' => 200,
                'error_code' => ResponseCodes::LOGIN_SUCCESS,
                'message' => $result['message'],
                'data' => [
                    'token' => $token,
                    'user' => [
                        'id' => $result['id'],
                        'role' => $result['role'],
                        'full_name' => $result['full_name'],
                        'avatar_url' => $avatarExists ? $avatar : null
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            Logger::error("Login error", "AuthController", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return Flight::json([
                'status' => 500,
                'error_code' => ResponseCodes::SYSTEM_ERROR,
                'message' => 'An unexpected error occurred. Please try again later.',
                'data' => null
            ], 500);
        }
    }

    /**
     * Handles new user registration
     * 
     * @return void JSON response with registration status
     * 
     * @api {post} /api/auth/register Register new user
     * @apiBody {String} name User's full name
     * @apiBody {String} email User's email
     * @apiBody {String} password User's password
     * @apiBody {String} role User's role
     * 
     * @apiSuccess {Object} user Created user information
     * 
     * @apiError {String} error_code Error code (EMAIL_ALREADY_EXISTS, INVALID_ROLE, etc.)
     */
    public function register() {
        Logger::info("=== REGISTER ===", "AuthController");
        
        try {
            $requestBody = Flight::request()->getBody();
            $data = json_decode($requestBody, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Logger::error("JSON decode error: " . json_last_error_msg(), 'AuthController');
                return Flight::json([
                    'status' => 400,
                    'error_code' => ResponseCodes::INVALID_USER_DATA,
                    'message' => '',
                    'data' => null
                ], 400);
            }

            // Input data sanitization and normalization
            $name = isset($data['name']) ? trim(mb_convert_encoding($data['name'], 'UTF-8', 'auto')) : '';
            $email = isset($data['email']) ? trim(mb_convert_encoding($data['email'], 'UTF-8', 'auto')) : '';
            $password = isset($data['password']) ? $data['password'] : '';
            $role = isset($data['role']) ? trim(mb_convert_encoding($data['role'], 'UTF-8', 'auto')) : '';

            Logger::info("Registration attempt", "AuthController", [
                'email' => $email,
                'name' => $name,
                'role' => $role
            ]);

            // Data validation
            if (empty($email) || empty($password) || empty($name) || empty($role)) {
                Logger::warning("Missing credentials in registration", "AuthController", [
                    'email' => $email,
                    'name' => $name,
                    'role' => $role
                ]);
                return Flight::json([
                    'status' => 400,
                    'error_code' => ResponseCodes::MISSING_CREDENTIALS,
                    'message' => '',
                    'data' => null
                ], 400);
            }

            $allowedRoles = ['agent', 'bussiness', 'user'];
            if (!in_array($role, $allowedRoles)) {
                Logger::warning("Invalid role in registration", "AuthController", ['role' => $role]);
                return Flight::json([
                    'status' => 400,
                    'error_code' => ResponseCodes::INVALID_ROLE,
                    'message' => '',
                    'data' => null
                ], 400);
            }

            // Password hashing
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            Logger::info("Calling registration procedure", "AuthController", [
                'email' => $email,
                'role' => $role
            ]);
            
            // Call registration procedure
            $stmt = $this->db->prepare("CALL sp_Register(?, ?, ?, ?)");
            $stmt->execute([$name, $email, $hashedPassword, $role]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            Logger::info("Registration procedure completed", "AuthController", [
                'userId' => $result['id'] ?? null,
                'message' => $result['message'] ?? 'User registered successfully'
            ]);
            
            $stmt->closeCursor();
            
                try {
                    // Create verification token
                    $token = $this->createVerificationToken($result['id']);
                
                Logger::info("Verification token created", "AuthController", [
                    'userId' => $result['id'],
                    'token' => $token
                ]);
                    
                    // Close all possible open cursors before next query
                    while ($this->db->inTransaction()) {
                        $this->db->commit();
                    }
                    
                // Send welcome and verification email
                $emailSent = $this->sendWelcomeVerificationEmail($email, $name, $token);
                
                Logger::info("Registration completed successfully", "AuthController", [
                    'userId' => $result['id'],
                    'email' => $email,
                    'emailSent' => $emailSent
                ]);
                    
                    return Flight::json([
                        'status' => 200,
                        'error_code' => ResponseCodes::REGISTRATION_SUCCESS,
                        'message' => '',
                        'data' => [
                            'user' => [
                                'id' => $result['id'],
                                'email' => $email,
                                'name' => $name,
                                'role' => $role,
                                'email_verified' => false
                            ]
                        ]
                    ], 200);
                } catch (\Exception $e) {
                Logger::error("Post-registration process error", "AuthController", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'userId' => $result['id'] ?? null
                ]);
                
                    // Registration is successful even if email sending failed
                    return Flight::json([
                        'status' => 200,
                        'error_code' => ResponseCodes::EMAIL_SEND_ERROR,
                        'message' => '',
                        'data' => [
                            'user' => [
                                'id' => $result['id'],
                                'email' => $email,
                                'name' => $name,
                                'role' => $role,
                                'email_verified' => false
                            ]
                        ]
                    ], 200);
            }

        } catch (\Exception $e) {
            Logger::error("Registration error", "AuthController", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return Flight::json([
                'status' => 500,
                'error_code' => ResponseCodes::EMAIL_EXISTS,
                'message' => $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    public function logout() {
        // Implementation of logout logic will be added here
        setcookie('auth_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            //'domain' => '.petsbook.ca'
            'secure' => false, // для локалки, для продакшена — true
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        
        return Flight::json([
            'status' => 200,
            'error_code' => ResponseCodes::LOGOUT_SUCCESS,
            'message' => '',
            'data' => null
        ], 200);
    }

    /**
     * Handles password reset request
     * 
     * @return void JSON response with status
     * 
     * @api {post} /api/auth/password-reset Request password reset
     * @apiBody {String} email User's email
     * 
     * @apiSuccess {String} status Success status
     * @apiSuccess {String} error_code Response code
     */
    public function passwordReset() {
        try {
            Logger::info("Starting password reset process", "AuthController");
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
            
            $requestBody = Flight::request()->getBody();
            $data = json_decode($requestBody, true);
            $email = trim($data['email']);
    
            Logger::info("Password reset request", "AuthController", [
                'email' => $email,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            if (!isset($data['email']) || empty(trim($data['email']))) {
                Logger::warning("Password reset attempt without email", "AuthController");
                return Flight::json([
                    'status' => 400,
                    'error_code' => ResponseCodes::MISSING_CREDENTIALS,
                    'message' => 'Email is required',
                    'data' => null
                ], 400);
            }

            $email = trim($data['email']);

            Logger::info("Checking user existence", "AuthController", ['email' => $email]);
            // Check if user exists
            $stmt = $this->db->prepare("
                SELECT id, email, role
                FROM logins 
                WHERE email = ? 
                AND is_active = 1
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                Logger::warning("Password reset attempt for non-existent user", "AuthController", [
                    'email' => $email,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);

                return Flight::json([
                    'status' => 200,
                    'error_code' => ResponseCodes::PASSWORD_RESET_SUCCESS,
                    'message' => 'If the email exists, you will receive a password reset link',
                    'data' => null
                ], 200);
            }

            Logger::info("User found, getting additional info", "AuthController", [
                'userId' => $user['id'],
                'email' => $user['email'],
                'role' => $user['role']
            ]);

            $name = null;
            switch ($user['role']) {
                case 'user':
                    Logger::info("Getting user profile", "AuthController", [
                        'userId' => $user['id'],
                        'loginId' => $user['id']
                    ]);

                    $stmt = $this->db->prepare("
                        SELECT full_name 
                        FROM user_profiles 
                        WHERE login_id = ?
                    ");
                    break;
                default:
                    Logger::warning("Unknown user role", "AuthController", [
                        'userId' => $user['id'],
                        'role' => $user['role']
                    ]);
                    $name = $user['email'];
            }    
            
            if ($name === null) {
                try {
                    $stmt->execute([$user['id']]);
                    $userInfo = $stmt->fetch();
                    
                    Logger::info("User profile query result", "AuthController", [
                        'userId' => $user['id'],
                        'profileData' => $userInfo
                    ]);
                    
                    $name = $userInfo['full_name'] ?? $user['email'];
                    
                    Logger::info("User name retrieved", "AuthController", [
                        'userId' => $user['id'],
                        'name' => $name,
                        'source' => isset($userInfo['full_name']) ? 'profile' : 'email'
                    ]);
                } catch (\PDOException $e) {
                    Logger::error("Error getting user name", "AuthController", [
                        'error' => $e->getMessage(),
                        'userId' => $user['id'],
                        'role' => $role['name'] ?? 'unknown',
                        'sql' => $stmt->queryString
                    ]);
                    $name = $user['email'];
                }
            }            

            Logger::info("User info retrieved", "AuthController", [
                'userId' => $user['id'],
                'email' => $user['email'],
                'role' => $user['role'],
                'name' => $name
            ]);

            // Generate password reset token
            $token = bin2hex(random_bytes(32));

            Logger::info("Saving reset token to database", "AuthController", [
                'userId' => $user['id'],
                'token' => $token
            ]);

            // Save token in database with 24 hour expiration
            $stmt = $this->db->prepare("
                INSERT INTO password_reset_tokens (login_id, token, expires_at, used) 
                VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR), FALSE)
            ");
            $stmt->execute([$user['id'], $token]);


            Logger::info("Sending reset email", "AuthController", [
                'email' => $user['email'],
                'name' => $name
            ]);

            $this->sendPasswordResetEmail($user['email'], $name, $token);

            Logger::info("Password reset process completed successfully", "AuthController", [
                'email' => $user['email']
            ]);                


            return Flight::json([
                'status' => 200,
                'error_code' => ResponseCodes::PASSWORD_RESET_SUCCESS,
                'message' => 'If the email exists, you will receive a password reset link',
                'data' => null
            ], 200);

        } catch (\Exception $e) {
            Logger::error("Password reset error", "AuthController", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'email' => $email ?? 'not provided'
            ]);

            return Flight::json([
                'status' => 500,
                'error_code' => ResponseCodes::SYSTEM_ERROR,
                'message' => 'System error occurred: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    /**
     * Generates JWT token for authenticated user
     * 
     * @param array $payload Data to be encoded in token
     * @return string Generated JWT token
     * @throws \Exception When JWT_SECRET is not configured
     */
    private function generateJWT($payload) {
        try {
            if (!isset($_ENV['JWT_SECRET'])) {
                throw new \Exception('JWT_SECRET not configured');
            }

            return JWT::encode([
                ...$payload,
                'iat' => time(),
                'exp' => time() + (60 * 60 * 24)
            ], $_ENV['JWT_SECRET'], 'HS256');
        } catch (\Exception $e) {
            Logger::info("JWT generation error: " . $e->getMessage(),'AuthController');
            throw $e;
        }
    }

    private function sendWelcomeVerificationEmail($email, $name, $token) {
        Logger::info("Sending welcome and verification email", "AuthController", [
            'email' => $email,
            'name' => $name
        ]);
        
        try {
            if (!isset($_ENV['APP_URL'])) {
                throw new \Exception("APP_URL environment variable is not set");
            }
            
            // Определяем протокол в зависимости от окружения
            $protocol = $_ENV['APP_ENV'] === 'production' ? 'https://' : 'http://';
            
            // Убираем существующие протоколы из APP_URL
            $baseUrl = preg_replace('/^https?:\/\//', '', $_ENV['APP_URL']);
            
            // Формируем ссылку с учетом протокола
            $verifyUrl = $protocol . $baseUrl . '/verify-email/' . $token;
            
            Logger::info("Generated verification link", "AuthController", [
                'verifyUrl' => $verifyUrl,
                'environment' => $_ENV['APP_ENV'] ?? 'development',
                'protocol' => $protocol,
                'originalUrl' => $_ENV['APP_URL'],
                'baseUrl' => $baseUrl
            ]);
            
            $mailService = new MailService();
            
            // Формируем персональные данные
            $personalData = [
                'name' => $name,
                'verifyUrl' => $verifyUrl
            ];

            // Формируем данные отправителя
            $senderData = [
                'Sender_Phone' => $_ENV['MAIL_SENDER_PHONE'],
                'Sender_Email' => $_ENV['MAIL_SENDER_EMAIL'],
                'Company_Address' => $_ENV['MAIL_COMPANY_ADDRESS'],
                'Company_Website' => $_ENV['MAIL_COMPANY_WEBSITE']
            ];
            /*
            MAIL_SENDER_PHONE='+1 (555)888-9999'
            MAIL_SENDER_EMAIL=contact@petsbook.ca
            MAIL_COMPANY_NAME=Petsbook
            MAIL_COMPANY_ADDRESS='123 Pet Street, Toronto, ON'
            MAIL_COMPANY_WEBSITE=https://petsbook.ca
            */

            // Объединяем массивы
            $templateData = array_merge($personalData, $senderData);

            // Создаем объект PersonalizedRecipient с объединенными данными
            $recipient = new PersonalizedRecipient($email, $templateData);
            
            Logger::info("Created PersonalizedRecipient", "AuthController", [
                'email' => $email,
                'personalizedVars' => $recipient->getPersonalizedVars()
            ]);
            
            $mailService->sendMail(
                $recipient,
                'Welcome to PetsBook - Please Verify Your Email',
                '', // Пустая строка вместо имени шаблона, так как используем templateId
                'd-9d44e3c83e9245a7bbd5f1edf7621c08' // ID шаблона SendGrid
            );
            
            Logger::info("Welcome and verification email sent successfully", "AuthController", [
                'email' => $email
            ]);
        } catch (\Exception $e) {
            Logger::error("Failed to send welcome and verification email", "AuthController", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
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
            Logger::info("Token creation error: " . $e->getMessage(),'AuthController');
            throw $e;
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
                return Flight::json([
                    'status' => 400,
                    'error_code' => ResponseCodes::INVALID_TOKEN,
                    'message' => '',
                    'data' => null
                ], 400);
            }

            // Update verification status
            $stmt = $this->db->prepare("
                UPDATE logins 
                SET email_verified = 1 
                WHERE id = ?
            ");
            $stmt->execute([$result['user_id']]);

            // Remove used token
            $stmt = $this->db->prepare("
                DELETE FROM email_verification_tokens 
                WHERE token = ?
            ");
            $stmt->execute([$token]);

            return Flight::json([
                'status' => 200,
                'error_code' => ResponseCodes::EMAIL_VERIFICATION_SUCCESS,
                'message' => '',
                'data' => null
            ], 200);

        } catch (\Exception $e) {
            Logger::info("Email verification error: " . $e->getMessage(),'AuthController');
            return Flight::json([
                'status' => 500,
                'error_code' => ResponseCodes::SYSTEM_ERROR,
                'message' => '',
                'data' => null
            ], 500);
        }
    }

    private function sendPasswordResetEmail(string $email, string $name, string $token): void
    {
        Logger::info("Sending reset email", "AuthController", [
            'email' => $email,
            'name' => $name
        ]);
        
        try {
            if (!isset($_ENV['APP_URL'])) {
                throw new \Exception("APP_URL environment variable is not set");
            }
            
            // Определяем протокол в зависимости от окружения
            $protocol = $_ENV['APP_ENV'] === 'production' ? 'https://' : 'http://';
            
            // Убираем существующие протоколы из APP_URL
            $baseUrl = preg_replace('/^https?:\/\//', '', $_ENV['APP_URL']);
            
            // Формируем ссылку с учетом протокола
            $resetLink = $protocol . $baseUrl . '/reset-password/' . $token;
            
            Logger::info("Generated reset link", "AuthController", [
                'resetLink' => $resetLink,
                'environment' => $_ENV['APP_ENV'] ?? 'development',
                'protocol' => $protocol,
                'originalUrl' => $_ENV['APP_URL'],
                'baseUrl' => $baseUrl
            ]);
            
            $mailService = new MailService();
            
            // Формируем персональные данные
            $personalData = [
                'name' => $name,
                'resetUrl' => $resetLink
            ];

            // Формируем данные отправителя
            $senderData = [
                'Sender_Phone' => $_ENV['MAIL_SENDER_PHONE'],
                'Sender_Email' => $_ENV['MAIL_SENDER_EMAIL'],
                'Company_Address' => $_ENV['MAIL_COMPANY_ADDRESS'],
                'Company_Website' => $_ENV['MAIL_COMPANY_WEBSITE']
            ];

            // Объединяем массивы
            $templateData = array_merge($personalData, $senderData);

            // Создаем объект PersonalizedRecipient с объединенными данными
            $recipient = new PersonalizedRecipient($email, $templateData);
            
            Logger::info("Created PersonalizedRecipient", "AuthController", [
                'email' => $email,
                'personalizedVars' => $recipient->getPersonalizedVars()
            ]);
            
            $mailService->sendMail(
                $recipient,
                'Password Reset Request - Petsbook',
                'password_reset.twig',
                'd-f9df34b3b3404f55a869bbccbbeba172' // ID вашего шаблона SendGrid
            );
            
            Logger::info("Reset email sent successfully", "AuthController", [
                'email' => $email
            ]);
        } catch (\Exception $e) {
            Logger::error("Failed to send reset email", "AuthController", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    // Новый метод для установки нового пароля
    public function setNewPassword() {
        try {
            $requestBody = Flight::request()->getBody();
            $data = json_decode($requestBody, true);

            if (!isset($data['token']) || !isset($data['password'])) {
                return Flight::json([
                    'success' => false,
                    'message' => 'Token and password are required'
                ], 400);
            }

            $token = trim($data['token']);
            $password = trim($data['password']);

            // Проверяем токен
            $stmt = $this->db->prepare("
                SELECT prt.login_id, l.email, prt.expires_at, prt.used
                FROM password_reset_tokens prt
                JOIN logins l ON l.id = prt.login_id
                WHERE prt.token = ? 
                LIMIT 1
            ");
            $stmt->execute([$token]);
            $result = $stmt->fetch();
            
            Logger::info("Token check result: " . print_r($result, true),'AuthController');

            if (!$result) {
                return Flight::json([
                    'success' => false,
                    'message' => 'Invalid or expired password reset link'
                ], 400);
            }

            if ($result['used']) {
                return Flight::json([
                    'success' => false,
                    'message' => 'This password reset link has already been used'
                ], 400);
            }

            if (strtotime($result['expires_at']) < time()) {
                return Flight::json([
                    'success' => false,
                    'message' => 'Password reset link has expired'
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
                    'message' => 'Password has been successfully reset'
                ], 200);

            } catch (\Exception $e) {
                $this->db->rollBack();
                Logger::info("Transaction rolled back due to error: " . $e->getMessage(), 'AuthController');
                throw $e;
            }

        } catch (\Exception $e) {
            return Flight::json([
                'success' => false,
                'message' => 'An error occurred while changing the password'
            ], 500);
        }
    }

    /**
     * Validates password reset token
     * 
     * @return void JSON response with validation status
     * 
     * @api {post} /api/auth/validate-reset-token Validate reset token
     * @apiBody {String} token Password reset token
     * 
     * @apiSuccess {String} status Success status
     * @apiSuccess {String} error_code Response code
     */
    public function validateResetToken() {
        try {
            $requestBody = Flight::request()->getBody();
            $data = json_decode($requestBody, true);

            if (!isset($data['token'])) {
                return Flight::json([
                    'status' => 400,
                    'error_code' => ResponseCodes::TOKEN_NOT_PROVIDED,
                    'message' => 'Token is required',
                    'data' => null
                ], 400);
            }

            $token = trim($data['token']);

            // Check token validity
            $stmt = $this->db->prepare("
                SELECT prt.login_id, prt.expires_at, prt.used
                FROM password_reset_tokens prt
                WHERE prt.token = ? 
                LIMIT 1
            ");
            $stmt->execute([$token]);
            $result = $stmt->fetch();

            if (!$result) {
                return Flight::json([
                    'status' => 400,
                    'error_code' => ResponseCodes::INVALID_TOKEN,
                    'message' => 'Invalid or non-existent token',
                    'data' => null
                ], 400);
            }

            if ($result['used']) {
                return Flight::json([
                    'status' => 400,
                    'error_code' => ResponseCodes::INVALID_TOKEN,
                    'message' => 'Token has already been used',
                    'data' => null
                ], 400);
            }

            if (strtotime($result['expires_at']) < time()) {
                return Flight::json([
                    'status' => 400,
                    'error_code' => ResponseCodes::TOKEN_EXPIRED,
                    'message' => 'Token has expired',
                    'data' => null
                ], 400);
            }

            return Flight::json([
                'status' => 200,
                'error_code' => 'TOKEN_VALID',
                'message' => 'Token is valid',
                'data' => null
            ], 200);

        } catch (\Exception $e) {
            Logger::info("Token validation error: " . $e->getMessage(),'AuthController');
            return Flight::json([
                'status' => 500,
                'error_code' => ResponseCodes::SYSTEM_ERROR,
                'message' => 'System error occurred',
                'data' => null
            ], 500);
        }
    }
}
