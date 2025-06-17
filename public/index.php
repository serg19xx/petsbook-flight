<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('X-Debug-CORS: index.php reached');
// Load environment variables
require_once __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/Middleware/CorsMiddleware.php';
\App\Middleware\CorsMiddleware::handle();

$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

require  __DIR__ . '/../src/routes/api.php';

Flight::start();

use App\Utils\Logger;

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
