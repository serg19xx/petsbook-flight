<?php
// test_rtl_translation.php

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

    // Проверяем методы
    echo "Checking methods...\n";
    echo "isRtlLanguage exists: " . (method_exists($service, 'isRtlLanguage') ? "Yes" : "No") . "\n";
    echo "getLanguageDirection exists: " . (method_exists($service, 'getLanguageDirection') ? "Yes" : "No") . "\n";
    echo "translateWithDirection exists: " . (method_exists($service, 'translateWithDirection') ? "Yes" : "No") . "\n\n";

    // Тест RTL языков
    $rtlLanguages = ['ar', 'he', 'fa', 'ur', 'ps'];
    $text = "Welcome to our website";

    echo "Testing RTL languages:\n";
    foreach ($rtlLanguages as $lang) {
        echo "\nTesting $lang:\n";
        echo "Is RTL: " . ($service->isRtlLanguage($lang) ? "Yes" : "No") . "\n";
        echo "Direction: " . $service->getLanguageDirection($lang) . "\n";
        
        $result = $service->translateWithDirection($text, $lang);
        if ($result) {
            echo "Original: $text\n";
            echo "Translated: {$result['text']}\n";
            echo "Direction: {$result['direction']}\n";
        } else {
            echo "Translation failed for $lang\n";
        }
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}