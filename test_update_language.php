<?php
// test_update_language.php

require_once __DIR__ . '/vendor/autoload.php';

// Загружаем .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

try {
    // Создаем подключение к БД
    $db = new PDO(
        "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4",
        $_ENV['DB_USER'],
        $_ENV['DB_PASSWORD']
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Обновляем запись
    $stmt = $db->prepare("
        UPDATE i18n_locales 
        SET name = 'Ukrainian', 
            native_name = 'Українська' 
        WHERE code = 'uk'
    ");
    $stmt->execute();

    // Проверяем результат
    $stmt = $db->query("SELECT * FROM i18n_locales WHERE code = 'uk'");
    $locale = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Updated locale record:\n";
    print_r($locale);

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
