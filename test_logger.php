<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Utils\Logger;

// Проверяем, что класс существует
if (!class_exists('App\Utils\Logger')) {
    die("Logger class not found!\n");
}

echo "Logger class found, testing...\n";

// Инициализируем логгер
Logger::init();

// Пробуем записать разные типы логов
Logger::info("Test info message", "Test");
Logger::error("Test error message", "Test", ['data' => 'test data']);
Logger::debug("Test debug message", "Test");
Logger::warning("Test warning message", "Test");
Logger::critical("Test critical message", "Test");

echo "Logging test completed. Check the logs directory.\n";

// Проверяем, создался ли файл лога
$logFile = __DIR__ . '/logs/' . date('Y-m-d') . '.log';
if (file_exists($logFile)) {
    echo "Log file exists at: $logFile\n";
    echo "Contents:\n";
    echo file_get_contents($logFile);
} else {
    echo "Log file not found at: $logFile\n";
}