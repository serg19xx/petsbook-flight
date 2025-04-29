<?php

//use App\Middleware\CorsMiddleware;
use App\Controllers\AuthController;
use App\Controllers\UserController;
use App\Controllers\AvatarController;
use App\Controllers\CoverController;
use App\Controllers\StatsController;

/**
 * API Routes configuration
 * 
 * This file contains all API route definitions and their corresponding controller methods.
 * Routes are grouped by functionality (auth, user, etc.)
 */

// Enable CORS
//CorsMiddleware::handle();

$db = new PDO(
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

$authController = new AuthController($db);
$userController = new UserController($db);
$avatarController = new AvatarController($db);
$coverController = new CoverController($db);
$statsController = new StatsController($db);

Flight::route('GET /api/test-db', function() use ($db) {
    try {
        // Пример: получить список таблиц
        $stmt = $db->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        Flight::json([
            'status' => 'success',
            'tables' => $tables
        ]);
    } catch (Exception $e) {
        Flight::json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
});

/**
 * Authentication Routes
 * 
 * @route POST /api/auth/login - User login
 * @route POST /api/auth/register - New user registration
 * @route POST /api/auth/logout - User logout
 * @route POST /api/auth/password-reset - Password reset request
 * @route POST /api/auth/set-new-password - Set new password
 * @route POST /api/auth/validate-reset-token - Validate reset token
 * @route GET /api/auth/verify-email/@token - Email verification
 */
Flight::route('POST /api/auth/login', [$authController, 'login']);
Flight::route('POST /api/auth/register', [$authController, 'register']);
Flight::route('POST /api/auth/logout', [$authController, 'logout']);
Flight::route('POST /api/auth/password-reset', [$authController, 'passwordReset']);
Flight::route('POST /api/auth/set-new-password', [$authController, 'setNewPassword']);
Flight::route('POST /api/auth/validate-reset-token', [$authController, 'validateResetToken']);
Flight::route('GET /api/auth/verify-email/@token', [$authController, 'verifyEmail']);

/**
 * User Routes
 * 
 * @route GET /api/user/getuser - Get current user data
 */
Flight::route('GET /api/user/getuser', [$userController, 'getUserData']);
Flight::route('PUT /api/user/update', [$userController, 'updateUser']);

// Profile image routes
Flight::route('POST /api/user/avatar', [$avatarController, 'upload']);
Flight::route('POST /api/user/cover', [$coverController, 'upload']);    
Flight::route('GET /api/images/*', [$avatarController, 'getImage']);

Flight::route('POST /api/stats/visit', [$statsController, 'visit']);

// Handle 404
Flight::map('notFound', function() {
    Flight::json([
        'status' => 'error',
        'message' => 'Route not found'
    ], 404);
});

Flight::before('start', function(&$params, &$output) {
    //CorsMiddleware::handle();
});

