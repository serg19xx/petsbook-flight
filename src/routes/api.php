<?php

use App\Middleware\CorsMiddleware;
use App\Controllers\AuthController;
use App\Controllers\UserController;
use App\Controllers\AvatarController;
use App\Controllers\CoverController;
use App\Controllers\StatsController;
use App\Controllers\I18n\LocaleController;
use App\Controllers\I18n\TranslationController;
use App\Controllers\I18n\EmailTemplateController;

/**
 * API Routes configuration
 * 
 * This file contains all API route definitions and their corresponding controller methods.
 * Routes are grouped by functionality (auth, user, etc.)
 */

// Enable CORS
CorsMiddleware::handle();

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
$localeController = new LocaleController($db);
$translationController = new TranslationController($db);
$emailTemplateController = new EmailTemplateController($db);

// Тестовый endpoint без базы
Flight::route('GET /api/test', function() {
    Flight::json([
        'status' => 'success',
        'message' => 'API работает!'
    ]);
});

// Тестовый endpoint с базой
Flight::route('GET /api/test-db', function() use ($db) {
    try {
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

Flight::route('POST /api/stats/visit', [$statsController, 'visit']);

// Locale routes
Flight::route('GET /api/i18n/locales', [$localeController, 'index']);
Flight::route('GET /api/i18n/locales/@code', [$localeController, 'show']);
Flight::route('GET /api/i18n/translations/@locale', [$translationController, 'getByLocale']);
Flight::route('GET /api/i18n/translations/@locale/@namespace', [$translationController, 'getByNamespace']);

// В routes.php или где у вас определены маршруты
Flight::route('GET /api/i18n/translated-languages', [$translationController, 'getTranslatedLanguages']);
Flight::route('GET /api/i18n/available-languages', [$translationController, 'getAvailableLanguages']);
Flight::route('GET /api/i18n/task-status/@taskId', [$translationController, 'getTaskStatus']);

Flight::route('OPTIONS /api/i18n/translate-language/@locale', function() {
    Flight::response()
        ->header('Access-Control-Allow-Origin', 'http://localhost:5173')
        ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type')
        ->header('Access-Control-Max-Age', '86400')
        ->status(200)
        ->send();
});

Flight::route('GET /api/i18n/translate-language/@locale', [$translationController, 'translateLanguage']);
Flight::route('POST /api/i18n/add-translation-key', [$translationController, 'addTranslationKey']);

// В routes.php
Flight::route('POST /api/i18n/initialize-languages', [$translationController, 'initializeLanguages']);

// На эти:
Flight::route('GET /api/i18n/all-translation-keys', [$translationController, 'getAllTranslationKeys']);
Flight::route('POST /api/i18n/update-translation-key', [$translationController, 'updateTranslationKey']);
Flight::route('POST /api/i18n/delete-translation-key', [$translationController, 'deleteTranslationKey']);

Flight::route('GET /api/i18n/email-templates', [$emailTemplateController, 'getTemplates']);
Flight::route('POST /api/i18n/email-templates/save', [$emailTemplateController, 'saveTemplate']);

// Static image serving routes
Flight::route('GET /api/profile-images/avatars/*', [$avatarController, 'serveImage']);
Flight::route('GET /api/profile-images/covers/*', [$coverController, 'serveImage']);
Flight::route('GET /api/profile-images/email-tmpl/*', [$emailTemplateController, 'serveImage']);

// Short URL for email template images
Flight::route('GET /api/email-tmpl/*', [$emailTemplateController, 'serveImage']);

// Handle 404
Flight::map('notFound', function() {
    Flight::json([
        'status' => 'error',
        'message' => 'Route not found'
    ], 404);
});

Flight::before('start', function(&$params, &$output) {
    CorsMiddleware::handle();
});

