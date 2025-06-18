<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Load environment variables
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Handle CORS first
require __DIR__ . '/../src/Middleware/CorsMiddleware.php';
\App\Middleware\CorsMiddleware::handle();

header('X-Debug-CORS: index.php reached');

require  __DIR__ . '/../src/routes/api.php';


use App\Utils\Logger;
file_put_contents('/api.petsbook.ca/logs/debug_env.log', 'CORS_ALLOWED_ORIGINS: ' . (isset($_ENV['CORS_ALLOWED_ORIGINS']) ? $_ENV['CORS_ALLOWED_ORIGINS'] : (getenv('CORS_ALLOWED_ORIGINS') ?: 'NOT SET')) . PHP_EOL, FILE_APPEND);

Logger::info('DEBUG_CORS_ALLOWED_ORIGINS', 'Env', [
    'CORS_ALLOWED_ORIGINS' => $_ENV['CORS_ALLOWED_ORIGINS'] ?? getenv('CORS_ALLOWED_ORIGINS') ?? 'NOT SET'
]);

Flight::start();

// Настраиваем error_log
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
ini_set('log_errors', 'On');
ini_set('display_errors', 'Off');
// Инициализируем логгер
Logger::init();

// Глобальный обработчик ошибок
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    Logger::error("PHP Error", "Global", [
        'error' => $errstr,
        'file' => $errfile,
        'line' => $errline,
        'type' => $errno
    ]);
    return true;
});

// Глобальный обработчик исключений
set_exception_handler(function($e) {
    Logger::error("Uncaught Exception", "Global", [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
});


