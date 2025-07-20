<?php

namespace App\Controllers;
require __DIR__ . '/../../vendor/autoload.php';

use App\Utils\Logger;
use PDO;
use Flight;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Constants\ResponseCodes;
use App\Mail\MailService;
use App\Mail\DTOs\PersonalizedRecipient;
use App\Services\EmailTemplateRenderer;
use App\Exceptions\EmailException;
use App\Exceptions\ValidationException;
use App\Exceptions\DatabaseException;
use App\Exceptions\TokenException;

/*

LOGIN_SUCCESS, MISSING_CREDENTIALS, EMAIL_NOT_VERIFIED
REGISTRATION_SUCCESS, EMAIL_ALREADY_EXISTS
LOGOUT_SUCCESS
EMAIL_VERIFICATION_SUCCESS, INVALID_TOKEN
PASSWORD_RESET_SUCCESS, SYSTEM_ERROR
TOKEN_ALREADY_USED, TOKEN_EXPIRED, TOKEN_VALID

*/

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
    private $renderer;
    


    /**
     * Constructor initializes database connection
     * 
     * @throws \Exception When database connection fails
     */
    public function __construct($db) {
        $this->request = Flight::request();
        $this->db = $db;
        $this->renderer = new EmailTemplateRenderer();

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
     *   FROM SP_LOGIN
     *   - INVALID_CREDENTIALS: Invalid credentials
     *   - ACCOUNT_BLOCKED: Account is blocked
     *   - EMAIL_NOT_VERIFIED: Your email is not verified. Please check your email and follow the verification link.
     * 
     * Authentication error codes:
     * - MISSING_CREDENTIALS: Missing email or password
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
                throw new ValidationException("Invalid JSON format");
            }
            
            if (!isset($data['email']) || !isset($data['password'])) {
                throw new ValidationException("Missing email or password");
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
                $statusCode = 400;
                
                // Специальная обработка для EMAIL_NOT_VERIFIED
                if ($result['error_code'] === 'EMAIL_NOT_VERIFIED') {
                    $statusCode = 403; // Forbidden - более подходящий статус для неподтвержденного email
                }
                
                $response = [
                    'status' => $statusCode,
                    'error_code' => $result['error_code'],
                    'data' => null
                ];
                
                Logger::info("Sending error response", "AuthController", [
                    'response' => $response,
                    'result' => $result
                ]);
                
                return Flight::json($response, $statusCode);
            }

            // Проверяем пароль
            if (!password_verify($data['password'], $result['stored_password'])) {
                return Flight::json([
                    'status' => 400,
                    'error_code' => 'INVALID_PASSWORD',
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

                $options = [
                    'expires' => time() + 7 * 24 * 60 * 60,
                    'path' => '/',
                    'secure' => !$isLocal,
                    'httponly' => true,
                    'samesite' => $isLocal ? 'Lax' : 'None'
                ];

                if (!$isLocal) {
                    $options['domain'] = '.petsbook.ca';
                }

                setcookie('auth_token', $token, $options);    

                // Проверяем, установилась ли кука
                $cookieSet = isset($_COOKIE['auth_token']);
                Logger::info("Cookie set status", "AuthController", [
                    'cookie_set' => $cookieSet,
                    'cookie_value' => $cookieSet ? 'exists' : 'missing'
                ]);

                // Добавляем заголовок для отладки
                header('X-Set-Cookie-Debug: auth_token set with domain=' . ($isLocal ? 'localhost' : '.site.petsbook.ca'));
            }    

            return Flight::json([
                'status' => 200,
                'error_code' => 'LOGIN_SUCCESS',
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

        } catch (ValidationException $e) {
            Logger::warning("Validation error in login", "AuthController", [
                'error' => $e->getMessage()
            ]);
            return Flight::json([
                'status' => 400,
                'error_code' => 'INVALID_REQUEST',
                'data' => null
            ], 400);
        } catch (DatabaseException $e) {
            Logger::error("Database error in login", "AuthController", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Flight::json([
                'status' => 500,
                'error_code' => 'DATABASE_ERROR',
                'data' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        } catch (\Exception $e) {
            Logger::error("System error in login", "AuthController", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return Flight::json([
                'status' => 500,
                'error_code' => 'SYSTEM_ERROR',
                'data' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
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
                throw new ValidationException("Invalid JSON format");
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
                throw new ValidationException("Missing required fields");
            }

            $allowedRoles = ['agent', 'bussiness', 'user'];
            if (!in_array($role, $allowedRoles)) {
                throw new ValidationException("Invalid role");
            }

            // Password hashing
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            Logger::info("Calling registration procedure", "AuthController", [
                'email' => $email,
                'role' => $role
            ]);
            
            try {
                // Call registration procedure
                $stmt = $this->db->prepare("CALL sp_Register(?, ?, ?, ?)");
                $stmt->execute([$name, $email, $hashedPassword, $role]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $stmt->closeCursor();

                // Временное логирование для отладки
                Logger::info("DEBUG: Full registration result", "AuthController", [
                    'result' => $result,
                    'resultType' => gettype($result),
                    'resultKeys' => $result ? array_keys($result) : 'null',
                    'success' => $result['success'] ?? 'not set',
                    'error_code' => $result['error_code'] ?? 'not set',
                    'id' => $result['id'] ?? 'not set'
                ]);

                Logger::info("Registration procedure completed", "AuthController", [
                    'userId' => $result['id'] ?? null,
                    'success' => $result['success'] ?? null,
                    'error_code' => $result['error_code'] ?? null,
                    'message' => $result['message'] ?? 'User registered successfully'
                ]);
                
                // Проверяем успешность регистрации
                if (!$result || $result['success'] == 0) {  // Изменили === false на == 0
                    // Проверяем специфичные ошибки
                    if (isset($result['error_code']) && $result['error_code'] === 'EMAIL_ALREADY_EXISTS') {
                        Logger::warning("Email already exists during registration", "AuthController", [
                            'email' => $email,
                            'message' => $result['message']
                        ]);
                        
                        return Flight::json([
                            'status' => 400,
                            'error_code' => 'EMAIL_ALREADY_EXISTS',
                            'data' => null
                        ], 400);
                    }
                    
                    Logger::error("Registration failed", "AuthController", [
                        'result' => $result
                    ]);
                    
                    return Flight::json([
                        'status' => 500,
                        'error_code' => 'REGISTRATION_FAILED',
                        'data' => null
                    ], 500);
                }
                
                // Проверяем, что есть ID пользователя
                if (!isset($result['id']) || $result['id'] === null) {
                    Logger::error("Registration failed - no user ID returned", "AuthController", [
                        'result' => $result
                    ]);
                    
                    return Flight::json([
                        'status' => 500,
                        'error_code' => 'REGISTRATION_FAILED',
                        'data' => null
                    ], 500);
                }
                
                // Только если регистрация прошла успешно, создаем токен и отправляем email
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
                        'error_code' => 'REGISTRATION_SUCCESS',
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
                } catch (EmailException $e) {
                    Logger::error("Email sending failed during registration", "AuthController", [
                        'error' => $e->getMessage(),
                        'userId' => $result['id'] ?? null
                    ]);
                
                    // Registration is successful even if email sending failed
                    return Flight::json([
                        'status' => 200,
                        'error_code' => 'REGISTRATION_SUCCESS',
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
                
            } catch (\PDOException $e) {
                // Обработка других ошибок базы данных
                throw $e;
            }

        } catch (ValidationException $e) {
            Logger::warning("Validation error in registration", "AuthController", [
                'error' => $e->getMessage()
            ]);
            return Flight::json([
                'status' => 400,
                'error_code' => 'INVALID_REQUEST',
                'data' => null
            ], 400);
        } catch (DatabaseException $e) {
            Logger::error("Database error in registration", "AuthController", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Flight::json([
                'status' => 500,
                'error_code' => 'DATABASE_ERROR',
                'data' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        } catch (\Exception $e) {
            Logger::error("System error in registration", "AuthController", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return Flight::json([
                'status' => 500,
                'error_code' => 'SYSTEM_ERROR',
                'data' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        }
    }

    public function logout() {
        // Implementation of logout logic will be added here
        setcookie('auth_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
                            //'domain' => '.site.petsbook.ca'
            'secure' => false, // для локалки, для продакшена — true
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        
        return Flight::json([
            'status' => 200,
            'error_code' => 'LOGOUT_SUCCESS',
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
            
            $requestBody = Flight::request()->getBody();
            $data = json_decode($requestBody, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new ValidationException("Invalid JSON format");
            }
            
            if (!isset($data['email']) || empty(trim($data['email']))) {
                throw new ValidationException("Email is required");
            }

            $email = trim($data['email']);

            Logger::info("Password reset request", "AuthController", [
                'email' => $email,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

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
                    'error_code' => 'PASSWORD_RESET_SUCCESS',
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
                        'role' => $user['role'] ?? 'unknown',
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

            Logger::info("Calling sendPasswordResetEmail", "AuthController", [
                'email' => $user['email'],
                'name' => $name,
                'token' => $token
            ]);

            $this->sendPasswordResetEmail($user['email'], $name, $token);

            Logger::info("Password reset process completed successfully", "AuthController", [
                'email' => $user['email']
            ]);                

            return Flight::json([
                'status' => 200,
                'error_code' => 'PASSWORD_RESET_SUCCESS',
                'data' => null
            ], 200);

        } catch (ValidationException $e) {
            Logger::warning("Validation error in password reset", "AuthController", [
                'error' => $e->getMessage()
            ]);
            return Flight::json([
                'status' => 400,
                'error_code' => 'INVALID_REQUEST',
                'data' => null
            ], 400);
        } catch (EmailException $e) {
            Logger::error("Email sending error in password reset", "AuthController", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Flight::json([
                'status' => 500,
                'error_code' => 'EMAIL_SEND_ERROR',
                'data' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        } catch (DatabaseException $e) {
            Logger::error("Database error in password reset", "AuthController", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Flight::json([
                'status' => 500,
                'error_code' => 'DATABASE_ERROR',
                'data' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        } catch (\Exception $e) {
            Logger::error("System error in password reset", "AuthController", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'email' => $email ?? 'not provided'
            ]);

            return Flight::json([
                'status' => 500,
                'error_code' => 'SYSTEM_ERROR',
                'data' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
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
            //$protocol = $_ENV['APP_ENV'] === 'production' ? 'https://' : 'http://';
            //$baseUrl = preg_replace('/^https?:\/\//', '', $_ENV['APP_URL']);
            //$verifyUrl = $protocol . $baseUrl . '/verify-email/' . $token;

            $origin = $_SERVER['HTTP_ORIGIN'] ?? 'http://localhost:5173';
            $verifyUrl = $origin . '/verify-email/' . $token;

            Logger::info("Generated verification link", "AuthController", [
                'verifyUrl' => $verifyUrl,
                //'environment' => $_ENV['APP_ENV'] ?? 'development',
                //'protocol' => $protocol,
                //'originalUrl' => $_ENV['APP_URL'],
                //'baseUrl' => $baseUrl
                'origin' => $origin
            ]);
            // Получаем шаблон из базы данных
            $stmt = $this->db->prepare("
                SELECT * FROM v_email_templates WHERE code='auth.registration.welcome' AND locale='en'");
            $stmt->execute();
            $template = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$template) {
                Logger::error("Email template not found", "AuthController", [
                    'code' => 'auth.registration.welcome',
                    'locale' => 'en'
                ]);
                throw new \Exception("Email template not found");
            }

            $tmplSubj = $template['subject'];
            $tmplBody = $template['header_html'] . $template['body_html'] . $template['footer_html'];

            // Преобразуем имя в UTF-8
            $utf8Name = mb_convert_encoding($name, 'UTF-8', 'auto');

            // Рендерим тему и тело письма через Twig
            $rendered = $this->renderer->render(
                $tmplBody,
                $tmplSubj,
                [
                    'clientName' => $utf8Name,
                    'verifyUrl' => $verifyUrl,
                    'Sender_Phone' => $_ENV['MAIL_SENDER_PHONE'],
                    'Sender_Email' => $_ENV['MAIL_SENDER_EMAIL'],
                    'now' => date('Y')
                ]
            );

            $mailService = new MailService();

            Logger::info("Prepared rendered email", "AuthController", [
                'email' => $email
            ]);

            $mailService->sendMail(
                $email,
                $rendered['subject'],
                $rendered['body']
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
            if (empty($token)) {
                throw new ValidationException("Token is required");
            }

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
                    'error_code' => 'INVALID_TOKEN',
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
                'error_code' => 'EMAIL_VERIFICATION_SUCCESS',
                'data' => null
            ], 200);

        } catch (ValidationException $e) {
            Logger::warning("Validation error in email verification", "AuthController", [
                'error' => $e->getMessage()
            ]);
            return Flight::json([
                'status' => 400,
                'error_code' => 'INVALID_REQUEST',
                'data' => null
            ], 400);
        } catch (DatabaseException $e) {
            Logger::error("Database error in email verification", "AuthController", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Flight::json([
                'status' => 500,
                'error_code' => 'DATABASE_ERROR',
                'data' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        } catch (\Exception $e) {
            Logger::error("System error in email verification", "AuthController", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Flight::json([
                'status' => 500,
                'error_code' => 'SYSTEM_ERROR',
                'data' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
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
            $protocol = (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'production') ? 'https://' : 'http://';

            // Убираем существующие протоколы из APP_URL
            $baseUrl = preg_replace('/^https?:\/\//', '', $_ENV['APP_URL']);
            $baseUrl = rtrim($baseUrl, '/');

            $resetLink = $protocol . $baseUrl . '/reset-password/' . $token;

            Logger::info("Generated reset link", "AuthController", [
                'resetLink' => $resetLink,
                'environment' => $_ENV['APP_ENV'] ?? 'development',
                'protocol' => $protocol,
                'originalUrl' => $_ENV['APP_URL'] ?? null,
                'baseUrl' => $baseUrl
            ]);
            
            $mailService = new MailService();
            
            Logger::info("Created PersonalizedRecipient", "AuthController", [
                'email' => $email
            ]);

            $stmt = $this->db->prepare("SELECT * FROM v_email_templates WHERE code='auth.resetpassword.link' AND locale='en'");
            $stmt->execute();
            $template = $stmt->fetch(PDO::FETCH_ASSOC);

            $tmplSubj = $template['subject'];
            $tmplBody = $template['header_html'] . $template['body_html'] . $template['footer_html'];
            $utf8Name = mb_convert_encoding($name, 'UTF-8', 'auto');

            $rendered = $this->renderer->render(
                $tmplBody,
                $tmplSubj,
                [
                    'clientName' => $utf8Name,
                    'resetUrl' => $resetLink,
                    'Sender_Phone' => $_ENV['MAIL_SENDER_PHONE'],
                    'Sender_Email' => $_ENV['MAIL_SENDER_EMAIL'],
                    'now' => date('Y')
                ]
            );

            $mailService->sendMail(
                $email,
                $rendered['subject'],
                $rendered['body']
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

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new ValidationException("Invalid JSON format");
            }

            if (!isset($data['token']) || !isset($data['password'])) {
                throw new ValidationException("Token and password are required");
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
                    'status' => 400,
                    'error_code' => 'INVALID_TOKEN',
                    'data' => null
                ], 400);
            }

            if ($result['used']) {
                return Flight::json([
                    'status' => 400,
                    'error_code' => 'TOKEN_ALREADY_USED',
                    'data' => null
                ], 400);
            }

            if (strtotime($result['expires_at']) < time()) {
                return Flight::json([
                    'status' => 400,
                    'error_code' => 'TOKEN_EXPIRED',
                    'data' => null
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
                    'status' => 200,
                    'error_code' => 'PASSWORD_RESET_SUCCESS',
                    'data' => null
                ], 200);

            } catch (\Exception $e) {
                $this->db->rollBack();
                Logger::error("Transaction rolled back due to error", "AuthController", [
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }

        } catch (ValidationException $e) {
            Logger::warning("Validation error in set new password", "AuthController", [
                'error' => $e->getMessage()
            ]);
            return Flight::json([
                'status' => 400,
                'error_code' => 'INVALID_REQUEST',
                'data' => null
            ], 400);
        } catch (TokenException $e) {
            Logger::warning("Token error in set new password", "AuthController", [
                'error' => $e->getMessage()
            ]);
            return Flight::json([
                'status' => 400,
                'error_code' => 'INVALID_TOKEN',
                'data' => null
            ], 400);
        } catch (DatabaseException $e) {
            Logger::error("Database error in set new password", "AuthController", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Flight::json([
                'status' => 500,
                'error_code' => 'DATABASE_ERROR',
                'data' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        } catch (\Exception $e) {
            Logger::error("System error in set new password", "AuthController", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Flight::json([
                'status' => 500,
                'error_code' => 'SYSTEM_ERROR',
                'data' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
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

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new ValidationException("Invalid JSON format");
            }

            if (!isset($data['token'])) {
                throw new ValidationException("Token is required");
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
                    'error_code' => 'INVALID_TOKEN',
                    'data' => null
                ], 400);
            }

            if ($result['used']) {
                return Flight::json([
                    'status' => 400,
                    'error_code' => 'TOKEN_ALREADY_USED',
                    'data' => null
                ], 400);
            }

            if (strtotime($result['expires_at']) < time()) {
                return Flight::json([
                    'status' => 400,
                    'error_code' => 'TOKEN_EXPIRED',
                    'data' => null
                ], 400);
            }

            return Flight::json([
                'status' => 200,
                'error_code' => 'TOKEN_VALID',
                'data' => null
            ], 200);

        } catch (ValidationException $e) {
            Logger::warning("Validation error in token validation", "AuthController", [
                'error' => $e->getMessage()
            ]);
            return Flight::json([
                'status' => 400,
                'error_code' => 'INVALID_REQUEST',
                'data' => null
            ], 400);
        } catch (DatabaseException $e) {
            Logger::error("Database error in token validation", "AuthController", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Flight::json([
                'status' => 500,
                'error_code' => 'DATABASE_ERROR',
                'data' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        } catch (\Exception $e) {
            Logger::error("System error in token validation", "AuthController", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Flight::json([
                'status' => 500,
                'error_code' => 'SYSTEM_ERROR',
                'data' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        }
    }

    /**
     * Execute stored procedure with error handling
     * 
     * @param string $procedureName Name of the procedure
     * @param array $params Parameters for the procedure
     * @return array Result from procedure
     * @throws DatabaseException|ValidationException
     */
    private function executeStoredProcedure($procedureName, $params) {
        try {
            $placeholders = str_repeat('?,', count($params) - 1) . '?';
            $stmt = $this->db->prepare("CALL {$procedureName}({$placeholders})");
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            
            return $result;
            
        } catch (\PDOException $e) {
            // Обработка специфичных ошибок из хранимых процедур
            if ($e->getCode() == 45000) {
                $message = $e->getMessage();
                
                if (strpos($message, 'Email already exists') !== false) {
                    throw new ValidationException("Email already exists");
                }
                
                if (strpos($message, 'Invalid role') !== false) {
                    throw new ValidationException("Invalid role");
                }
                
                if (strpos($message, 'User not found') !== false) {
                    throw new ValidationException("User not found");
                }
                
                if (strpos($message, 'Email already verified') !== false) {
                    throw new ValidationException("Email already verified");
                }
            }
            
            // Другие ошибки базы данных
            throw new DatabaseException("Database error: " . $e->getMessage(), $e->getCode(), $e);
        }
    }
}

