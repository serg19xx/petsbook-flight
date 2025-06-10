<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Utils\Logger;

// Настраиваем error_log
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
ini_set('log_errors', 'On');
ini_set('display_errors', 'Off');

// Инициализируем логгер
Logger::init();

// Тестируем логирование
error_log("Test error_log message");
Logger::info("Test Logger message", "Test");

echo "Test completed. Check both log files:\n";
echo "1. " . __DIR__ . "/logs/php_errors.log\n";
echo "2. " . __DIR__ . "/logs/" . date('Y-m-d') . ".log\n";