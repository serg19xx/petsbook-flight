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
use App\Controllers\AuthUnverifiedEmailController;
use App\Controllers\MyPetsController;
use App\Controllers\PetGalleryController;
use App\Utils\Logger;

/**
 * API Routes configuration
 * 
 * This file contains all API route definitions and their corresponding controller methods.
 * Routes are grouped by functionality (auth, user, etc.)
 */

// Enable CORS
CorsMiddleware::handle();

// Логируем все входящие запросы для отладки
Logger::info("API request received", "Routes", [
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
    'uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'N/A',
    'content_length' => $_SERVER['CONTENT_LENGTH'] ?? 'N/A'
]);

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
$authUnverifiedEmailController = new AuthUnverifiedEmailController($db);
$myPetsController = new MyPetsController($db);
$petGalleryController = new PetGalleryController($db);

Flight::route('GET /', function() {
    echo json_encode(['status' => 'API is alive', 'env' => $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?? 'not set']);
});

// Тестовый endpoint без базы
Flight::route('GET /api/test', function() {
    Flight::json([
        'status' => 'success',
        'message' => 'API работает! Новый /workflow/deploy !!!!!!!'
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
Flight::route('GET /auth/verify-email/@token', [$authController, 'verifyEmail']);

// Auth Unverified Email routes
Flight::route('POST /auth/resend-unverified-email', [$authUnverifiedEmailController, 'resendUnverifiedEmail']);
Flight::route('DELETE /auth/delete-unverified-email', [$authUnverifiedEmailController, 'deleteUnverifiedEmail']);
Flight::route('PATCH /auth/update-unverified-email', [$authUnverifiedEmailController, 'updateUnverifiedEmail']);


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

/**
 * Pet Management Routes
 * 
 * @route GET /api/pets/my-pets - Get user's pets
 * @route POST /api/pets - Create new pet
 * @route PUT /api/pets/:id - Update pet
 * @route DELETE /api/pets/:id - Delete pet
 * @route PATCH /api/pets/:id/status - Update pet status
 * @route POST /api/pets/photo/upload - Upload pet photo
 * 
 * Pet Photo Management (via request body):
 * @route POST /api/pets/photo/upload
 *   - petId: 0 → создать нового питомца и загрузить фото
 *   - petId: 229 → добавить фото к существующему питомцу #229
 *   - petId: null → создать нового питомца и загрузить фото
 * 
 * Request structure:
 *   JSON body: { petId: number|null, filename: string|null, fileInfo: {...} }
 *   OR multipart/form-data with petId field
 *   + файл в $_FILES['photo']
 * 
 * File naming:
 *   - filename: null → создается новый файл pet_{uniqueId}.{ext}
 *   - filename: "pet_64f8a1b2.jpg" → обновляется существующий файл
 * 
 * Examples:
 *   { petId: 0, filename: null } → новый питомец + pet_64f8a1b2c3d4e.jpg
 *   { petId: 229, filename: null } → новое фото pet_64f8a1b2c3d4f.jpg для питомца #229
 *   { petId: 229, filename: "pet_64f8a1b2c3d4e.jpg" } → обновление существующего файла
 */
Flight::route('GET /api/pets/my-pets', [$myPetsController, 'getMyPets']);
Flight::route('POST /api/pets', [$myPetsController, 'createPet']);
Flight::route('PUT /api/pets/@id', [$myPetsController, 'updatePet']);
Flight::route('DELETE /api/pets/@id', [$myPetsController, 'deletePet']);
Flight::route('PATCH /api/pets/@id/status', [$myPetsController, 'updatePetStatus']);
Flight::route('POST /api/pets/photo/upload', [$myPetsController, 'uploadPetPhoto']);
Flight::route('POST /api/pets/photo/delete', [$myPetsController, 'deletePetPhotos']);  // /api/pets/photo/delete

/**
 * Public Pet Gallery Routes (no authentication required)
 * 
 * @route GET /api/pets/public - Get public pets gallery with pagination and filters
 * @route GET /api/pets/public/:id - Get public pet details by ID
 * 
 * Query parameters for /api/pets/public:
 * - page: Page number (default: 1)
 * - limit: Items per page (default: 12, max: 50)
 * - species: Filter by species (dog, cat, bird, etc.)
 * - gender: Filter by gender (male, female)
 * - age: Filter by age (young, adult, senior)
 * - location: Location filter (NOT IMPLEMENTED)
 * - radius: Search radius in km (TEMPORARILY DISABLED)
 * - user_lat: User's latitude (TEMPORARILY DISABLED)
 * - user_lng: User's longitude (TEMPORARILY DISABLED)
 * - sort: Sort order (newest, oldest, name) - distance temporarily disabled
 */
Flight::route('GET /api/pets/public', [$petGalleryController, 'getPublicPets']);
Flight::route('GET /api/pets/public/@id', [$petGalleryController, 'getPetDetails']);

/**
 * Pet Like Routes (requires authentication)
 * 
 * @route POST /api/pets/:id/like - Toggle pet like status
 * @apiParam {Number} id Pet ID
 * @apiHeader {String} Authorization JWT token
 * 
 * @apiSuccess {Number} liked Like status (1 if liked, 0 if unliked)
 * @apiSuccess {String} action Action performed (liked or unliked)
 * 
 * @apiError {String} error_code Error codes:
 *   - MISSING_TOKEN: Authorization header missing
 *   - INVALID_TOKEN: Invalid or expired JWT token
 *   - PET_NOT_FOUND: Pet not found or not published
 *   - SYSTEM_ERROR: Database or system error
 */
Flight::route('POST /api/pets/@id/like', [$petGalleryController, 'togglePetLike']);

// Short URL for email template images
Flight::route('GET /api/email-tmpl/*', [$emailTemplateController, 'serveImage']);
// Эти роуты должны быть БЕЗ AuthMiddleware
Flight::route('POST /api/email-templates/translate', [$emailTemplateController, 'translateTemplates']);
Flight::route('POST /api/email-templates/translate-layouts', [$emailTemplateController, 'translateLayouts']);

// Email template images
Flight::route('GET /api/email-tmpl-images/*', [$emailTemplateController, 'serveImage']);


// Static image serving routes
Flight::route('GET /api/profile-images/avatars/*', [$avatarController, 'serveImage']);
Flight::route('GET /api/profile-images/covers/*', [$coverController, 'serveImage']);
Flight::route('GET /api/profile-images/email-tmpl/*', [$emailTemplateController, 'serveImage']);

// Добавить эти роуты для совместимости с фронтендом
Flight::route('POST /user/cover', [$coverController, 'upload']);
Flight::route('POST /user/avatar', [$avatarController, 'upload']);
Flight::route('GET /user/cover/*', [$coverController, 'serveImage']);
Flight::route('GET /user/avatar/*', [$avatarController, 'serveImage']);

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

