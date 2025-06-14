<?php

require_once __DIR__ . '/vendor/autoload.php';

// Загружаем .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

if (!isset($_ENV['GOOGLE_TRANSLATE_API_KEY'])) {
    die("GOOGLE_TRANSLATE_API_KEY not found in .env file\n");
}

echo "API Key: " . substr($_ENV['GOOGLE_TRANSLATE_API_KEY'], 0, 10) . "...\n";

try{

    $service = new \App\Services\GoogleTranslateService();  

    $db = new PDO(
        "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4",
        $_ENV['DB_USER'],
        $_ENV['DB_PASSWORD']
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Получаем только языки, которые показываются в диалоге
    $stmt = $db->query("
        SELECT code, name, native_name, flag_icon 
        FROM i18n_locales 
        WHERE already_translated = 1 and code<>'en'
        ORDER BY name
    ");
    $locales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$locales) {
        echo "Нет языков для перевода\n";
        exit(0);
    }

    // Получаем все ключи и английские значения
    $stmt = $db->query("
        SELECT k.id as key_id, k.key_name, v.value as en_value
        FROM i18n_translation_keys k
        JOIN i18n_translation_values v ON v.key_id = k.id AND v.locale = 'en'
    ");
    $keys = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$keys) {
        echo "Нет ключей для перевода\n";
        exit(0);
    }

    foreach ($locales as $localeRow) {
        $locale = $localeRow['code'];
        if ($locale === 'en') continue; // пропускаем английский

        echo "Обработка языка: $locale\n";
        $count = 0;

        foreach ($keys as $key) {
            // Проверяем, есть ли перевод
            $stmt = $db->prepare("
                SELECT id, value FROM i18n_translation_values 
                WHERE key_id = ? AND locale = ?
            ");
            $stmt->execute([$key['key_id'], $locale]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$existing || trim((string)$existing['value']) === '') {
                try {
                    $result = $service->translate($key['en_value'], $locale);
                    if ($result && !empty($result['text'])) {
                        $translatedText = html_entity_decode($result['text'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        if ($existing) {
                            // Обновляем
                            $stmt = $db->prepare("
                                UPDATE i18n_translation_values 
                                SET value = ?, is_auto_translated = 1, updated_at = NOW()
                                WHERE id = ?
                            ");
                            $stmt->execute([$translatedText, $existing['id']]);
                            echo "Обновлён: {$key['key_name']} ($locale)\n";
                        } else {
                            // Вставляем
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
                    usleep(100000); // 100ms задержка для API
                } catch (Exception $e) {
                    echo "Ошибка перевода {$key['key_name']} ($locale): " . $e->getMessage() . "\n";
                }
            }
        }
        echo "Для языка $locale переведено $count ключей\n";
    }

    echo "Готово!\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}


