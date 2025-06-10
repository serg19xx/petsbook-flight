<?php

// Проверяем настройки error_log
echo "error_log path: " . ini_get('error_log') . "\n";
echo "display_errors: " . ini_get('display_errors') . "\n";
echo "log_errors: " . ini_get('log_errors') . "\n";

// Пробуем записать в error_log
error_log("Test error_log message");

// Пробуем записать в файл напрямую
file_put_contents(__DIR__ . '/php_errors.log', "Test direct file write\n", FILE_APPEND);

echo "Test completed. Check php_errors.log\n";