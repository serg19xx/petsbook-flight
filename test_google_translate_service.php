<?php
// test_google_translate_service.php

require_once __DIR__ . '/vendor/autoload.php';

// Загружаем .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Проверяем наличие API ключа
if (!isset($_ENV['GOOGLE_TRANSLATE_API_KEY'])) {
    die("GOOGLE_TRANSLATE_API_KEY not found in .env file\n");
}

echo "API Key: " . substr($_ENV['GOOGLE_TRANSLATE_API_KEY'], 0, 10) . "...\n";

try {
    echo "Creating service...\n";
    $service = new \App\Services\GoogleTranslateService();
    echo "Service created successfully\n\n";

    // Проверяем метод getRtlLanguages
    echo "Checking RTL languages...\n";
    $rtlLanguages = $service->getRtlLanguages();
    echo "RTL languages:\n";
    print_r($rtlLanguages);

    // Тест перевода
    echo "\nTesting translation...\n";
    $text = "Hello, world!";
    $targetLang = "uk";
    $result = $service->translate($text, $targetLang);
    echo "Original: $text\n";
    echo "Result:\n";
    print_r($result);

    // Тест перевода с определением направления
    $testLanguages = [
        'ar', // Arabic (RTL)
        'he', // Hebrew (RTL)
        'fa', // Persian (RTL)
        'ru', // Russian (LTR)
        'en', // English (LTR)
        'es'  // Spanish (LTR)
    ];

    $text = "Welcome to our website";

    foreach ($testLanguages as $lang) {
        echo "\nTesting $lang:\n";
        $result = $service->translate($text, $lang);
        if ($result) {
            echo "Original: $text\n";
            echo "Translated: {$result['text']}\n";
            echo "Direction: {$result['direction']}\n";
        } else {
            echo "Translation failed for $lang\n";
        }
    }

    // Тест получения списка языков
    echo "\nTesting supported languages:\n";
    $languages = $service->getSupportedLanguages();
    echo "Total supported languages: " . count($languages) . "\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}