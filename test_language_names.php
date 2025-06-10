<?php
// test_language_names.php

require_once __DIR__ . '/vendor/autoload.php';

// Загружаем .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

try {
    echo "Creating service...\n";
    
    // Проверяем API ключ
    echo "Checking API key...\n";
    if (empty($_ENV['GOOGLE_TRANSLATE_API_KEY'])) {
        throw new \Exception('GOOGLE_TRANSLATE_API_KEY is not set in .env file');
    }
    echo "API key is set\n";
    
    // Создаем клиент с таймаутом
    $translate = new \Google\Cloud\Translate\TranslateClient([
        'key' => $_ENV['GOOGLE_TRANSLATE_API_KEY'],
        'timeout' => 5, // 5 секунд таймаут
    ]);
    
    echo "Testing API connection...\n";
    try {
        // Пробуем простой перевод для проверки API
        $result = $translate->translate('Hello', [
            'target' => 'es',
            'source' => 'en'
        ]);
        echo "API connection successful\n";
    } catch (\Exception $e) {
        echo "API connection failed: " . $e->getMessage() . "\n";
        throw $e;
    }
    
    $service = new \App\Services\GoogleTranslateService();
    echo "Service created successfully\n\n";

    // Тестируем разные языки
    $testLanguages = ['ar', 'he', 'zh', 'ja'];
    
    foreach ($testLanguages as $lang) {
        echo "\nTesting $lang:\n";
        echo "Getting language names...\n";
        $names = $service->getLanguageNames($lang);
        
        if ($names) {
            echo "Result:\n";
            echo "English name: {$names['name']}\n";
            echo "Native name: {$names['native_name']}\n";
        } else {
            echo "Language names not found\n";
        }
    }

    // Тестируем перевод
    echo "Testing translation:\n";
    $text = "Hello, world!";
    echo "Translating text...\n";
    $result = $service->translate($text, 'ja');
    echo "Translation received\n";
    
    if ($result) {
        echo "Original: $text\n";
        echo "Translated: {$result['text']}\n";
        echo "Direction: {$result['direction']}\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
