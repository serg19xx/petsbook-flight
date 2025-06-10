<?php
// test_add_language.php

require_once __DIR__ . '/vendor/autoload.php';

// Загружаем .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

try {
    echo "Connecting to database...\n";
    // Создаем подключение к БД с настройками из .env
    $db = new PDO(
        "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4",
        $_ENV['DB_USER'],
        $_ENV['DB_PASSWORD']
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Database connection successful\n\n";

    echo "Creating controller...\n";
    // Создаем контроллер
    $controller = new \App\Controllers\I18n\TranslationController($db);
    echo "Controller created successfully\n\n";
    
    // Тест добавления украинского языка
    echo "Testing Ukrainian language addition...\n";
    $result = $controller->addLanguage('ss');
    echo "Result:\n";
    print_r($result);
    
    // Проверяем добавленные данные
    echo "\nChecking database...\n";
    
    // Проверяем i18n_locales
    echo "Checking i18n_locales...\n";
    $stmt = $db->query("SELECT * FROM i18n_locales WHERE code = 'uk'");
    $locale = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Locale record:\n";
    print_r($locale);
    
    // Проверяем переводы
    echo "\nChecking translations...\n";
    $stmt = $db->query("
        SELECT tv.*, tk.key_name, tk.namespace 
        FROM i18n_translation_values tv
        JOIN i18n_translation_keys tk ON tv.key_id = tk.id
        WHERE tv.locale = 'uk'
    ");
    $translations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Translations:\n";
    print_r($translations);

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}