<?php
require_once __DIR__ . '/../bootstrap.php';

use App\Services\GoogleTranslateService;

try {
    $db = Flight::db();
    $googleTranslate = new GoogleTranslateService();

    // 1. Добавляем новые ключи, если их нет
    $newKeys = [
        [
            'key_name' => 'UI.editprofile.fields.full_name',
            'namespace' => 'UI',
            'description' => 'Full name field label in edit profile form',
            'value' => 'Full Name'
        ],
        [
            'key_name' => 'UI.editprofile.fields.location',
            'namespace' => 'UI',
            'description' => 'Location field label in edit profile form',
            'value' => 'Location'
        ]
    ];

    foreach ($newKeys as $key) {
        // Проверяем, существует ли ключ
        $stmt = $db->prepare("
            SELECT id FROM i18n_translation_keys 
            WHERE key_name = ?
        ");
        $stmt->execute([$key['key_name']]);
        $existingKey = $stmt->fetch();

        if (!$existingKey) {
            // Добавляем новый ключ
            $stmt = $db->prepare("
                INSERT INTO i18n_translation_keys (key_name, namespace, description)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$key['key_name'], $key['namespace'], $key['description']]);
            $keyId = $db->lastInsertId();

            // Добавляем английское значение
            $stmt = $db->prepare("
                INSERT INTO i18n_translation_values (key_id, locale, value, is_auto_translated)
                VALUES (?, 'en', ?, 0)
            ");
            $stmt->execute([$keyId, $key['value']]);

            // Получаем все языки кроме английского
            $stmt = $db->prepare("
                SELECT code FROM i18n_locales 
                WHERE code != 'en' AND already_translated = 1
            ");
            $stmt->execute();
            $languages = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Переводим для каждого языка
            foreach ($languages as $lang) {
                $result = $googleTranslate->translate($key['value'], $lang);
                if ($result) {
                    $translatedText = html_entity_decode($result['text'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    
                    $stmt = $db->prepare("
                        INSERT INTO i18n_translation_values 
                        (key_id, locale, value, is_auto_translated)
                        VALUES (?, ?, ?, 1)
                    ");
                    $stmt->execute([$keyId, $lang, $translatedText]);
                }
            }
        }
    }

    echo "New keys translated successfully\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
} 