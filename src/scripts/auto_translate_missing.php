<?php
require_once __DIR__ . '/../bootstrap.php';

use App\Services\GoogleTranslateService;

try {
    $db = Flight::db();
    $googleTranslate = new GoogleTranslateService();

    // 1. Получаем все переведённые языки (кроме английского)
    $stmt = $db->prepare("SELECT code FROM i18n_locales WHERE already_translated = 1 AND code != 'en'");
    $stmt->execute();
    $locales = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!$locales) {
        echo "Нет переведённых языков\n";
        exit(0);
    }

    // 2. Получаем все ключи и английские значения
    $stmt = $db->prepare("
        SELECT k.id as key_id, k.key_name, v.value as en_value
        FROM i18n_translation_keys k
        JOIN i18n_translation_values v ON v.key_id = k.id AND v.locale = 'en'
    ");
    $stmt->execute();
    $keys = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$keys) {
        echo "Нет ключей для перевода\n";
        exit(0);
    }

    foreach ($locales as $locale) {
        echo "Обработка языка: $locale\n";

        $count = 0;

        foreach ($keys as $key) {
            $stmt = $db->prepare("
                SELECT id, value FROM i18n_translation_values 
                WHERE key_id = ? AND locale = ?
            ");
            $stmt->execute([$key['key_id'], $locale]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$existing) {
                echo "Нет перевода для ключа: {$key['key_name']} ($locale)\n";
            } elseif (trim((string)$existing['value']) === '') {
                echo "Пустой перевод для ключа: {$key['key_name']} ($locale)\n";
            }

            if (!$existing || trim((string)$existing['value']) === '') {
                $result = $googleTranslate->translate($key['en_value'], $locale);
                if ($result && !empty($result['text'])) {
                    $translatedText = html_entity_decode($result['text'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    if ($existing) {
                        $stmt = $db->prepare("
                            UPDATE i18n_translation_values 
                            SET value = ?, is_auto_translated = 1, updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([$translatedText, $existing['id']]);
                        echo "Обновлён: {$key['key_name']} ($locale)\n";
                    } else {
                        $stmt = $db->prepare("
                            INSERT INTO i18n_translation_values (key_id, locale, value, is_auto_translated)
                            VALUES (?, ?, ?, 1)
                        ");
                        $stmt->execute([$key['key_id'], $locale, $translatedText]);
                        echo "Добавлен: {$key['key_name']} ($locale)\n";
                    }
                    $count++;
                } else {
                    echo "Ошибка перевода: {$key['key_name']} ($locale)\n";
                }
            }
        }
        echo "Для языка $locale переведено $count ключей\n";
    }

    echo "Готово!\n";

} catch (\Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
    exit(1);
} 