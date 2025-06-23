<?php
require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use App\Utils\Logger;

Logger::init();

$taskId = $argv[1] ?? null;
if (!$taskId) {
    Logger::error("No task ID provided"); 
    exit(1);
}

try {
    Logger::info("Loading environment variables...");
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
    Logger::info("Environment variables loaded");
    
    $googleApiKey = $_ENV['GOOGLE_TRANSLATE_API_KEY'];
    if (!$googleApiKey) {
        Logger::error("No API key");
        exit(1);
    }
    Logger::info("API key found");

    Logger::info("Connecting to database...");
    $db = new PDO(
        "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4",
        $_ENV['DB_USER'],
        $_ENV['DB_PASSWORD']
    );
    Logger::info("Database connected");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Получаем locale из задачи
    $stmt = $db->prepare("SELECT locale FROM i18n_translation_tasks WHERE id = ?");
    $stmt->execute([$taskId]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
 
    if (!$task) exit(1);
    
    $targetLocale = $task['locale'];
    
    // Обновляем статус
    $stmt = $db->prepare("UPDATE i18n_translation_tasks SET status = 'processing' WHERE id = ?");
    $stmt->execute([$taskId]);

    // Получаем все ключи перевода с английскими значениями
    $stmt = $db->prepare("
        SELECT tk.id, tk.namespace, tk.key_name, tv.value 
        FROM i18n_translation_keys tk
        JOIN i18n_translation_values tv ON tk.id = tv.key_id
        WHERE tv.locale = 'en' AND tk.is_active = 1
    ");
    $stmt->execute();
    $strings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = count($strings);
    $processed = 0;
    $skipped = 0;
    $errors = [];
    $translatedCount = 0;
    
    $stmt = $db->prepare("UPDATE i18n_translation_tasks SET total_strings = ? WHERE id = ?");
    $stmt->execute([$total, $taskId]);
    
    Logger::info("====Начинаем цикл перевода=====");
    
    foreach ($strings as $index => $string) {
        Logger::info("====Обрабатываем строку $index=====", "", ["string" => $string]);

        // Проверяем существующий перевод
        $stmt = $db->prepare("SELECT id FROM i18n_translation_values WHERE key_id = ? AND locale = ?");
        $stmt->execute([$string['id'], $targetLocale]);
        $result = $stmt->fetch();

        if ($result) {
            Logger::info("====Перевод найден, пропускаем=====", "", ["stringId" => $string['id']]);
            $skipped++;
            $processed++;
        } else {
            Logger::info("====Перевод не найден, продолжаем=====", "", ["stringId" => $string['id']]);
            
            Logger::info("====До вызова Google Translate=====");
            Logger::info("#########Здесь вызов гугл#########");

            // Переводим
            $translated = translateText($string['value'], $targetLocale, $googleApiKey);
            Logger::info("====После вызова Google Translate=====", "", ["translated" => $translated]);
            
            if ($translated) {
                Logger::info("====Перевод успешен=====", "", [
                    "original" => $string['value'],
                    "translated" => $translated
                ]);

                $stmt = $db->prepare("INSERT INTO i18n_translation_values (key_id, locale, value, is_auto_translated) VALUES (?, ?, ?, 1)");
                $stmt->execute([$string['id'], $targetLocale, $translated]);

                Logger::info("====Перевод сохранен в БД=====", "", [
                    "stringId" => $string['id'],
                    "original" => $string['value'], 
                    "translated" => $translated
                ]);

                $processed++;
                $translatedCount++;
            } else {
                Logger::error("====Перевод не удался=====", "", ["original" => $string['value']]);
                $errors[] = "Failed to translate: {$string['namespace']}.{$string['key_name']}";
            }
        }
        
        // Обновляем прогресс
        $stmt = $db->prepare("UPDATE i18n_translation_tasks SET processed_strings = ?, skipped_strings = ? WHERE id = ?");
        $stmt->execute([$processed, $skipped, $taskId]);
        Logger::info("====Прогресс обновлен в БД=====", "", [
            "processed" => $processed,
            "skipped" => $skipped,
            "total" => $total
        ]);    

        usleep(100000);
    }

    // Завершаем - обновляем статус задачи и языка
    Logger::info("====Перевод завершен=====", "", ["processed" => $processed, "skipped" => $skipped, "total" => $total, "translatedCount" => $translatedCount]);

    // Определяем статус завершения
    $status = 'completed';
    if (!empty($errors)) {
        $status = 'completed_with_errors';
    }
    
    $errorsJson = !empty($errors) ? json_encode($errors) : null;
    $stmt = $db->prepare("UPDATE i18n_translation_tasks SET status = ?, completed_at = NOW(), errors = ? WHERE id = ?");
    $stmt->execute([$status, $errorsJson, $taskId]);

    // Обновляем статус в таблице локалей только если есть переводы
    if ($translatedCount > 0 || $skipped > 0) {
        $stmt = $db->prepare("UPDATE i18n_locales SET already_translated = 1 WHERE code = ?");
        $stmt->execute([$targetLocale]);
        Logger::info("====Статус языка обновлен=====", "", ["locale" => $targetLocale, "translatedCount" => $translatedCount, "skipped" => $skipped]);
    }

    Logger::info("====Задача завершена успешно=====", "", ["status" => $status, "locale" => $targetLocale]);

} catch (Exception $e) {
    Logger::error("====КРИТИЧЕСКАЯ ОШИБКА=====", "", [
        "error" => $e->getMessage(),
        "file" => $e->getFile(),
        "line" => $e->getLine(),
        "trace" => $e->getTraceAsString()
    ]);
    
    // Обновляем статус задачи с ошибкой
    try {
        $stmt = $db->prepare("UPDATE i18n_translation_tasks SET status = 'failed', errors = ? WHERE id = ?");
        $stmt->execute([json_encode([$e->getMessage()]), $taskId]);
    } catch (Exception $updateError) {
        Logger::error("Не удалось обновить статус задачи", "", ["error" => $updateError->getMessage()]);
    }
    
    exit(1);
}

function translateText($text, $targetLocale, $apiKey) {
    Logger::info("1 ====Начинаем перевод=====", "", ["text" => $text, "targetLocale" => $targetLocale, "apiKeyLength" => strlen($apiKey)]);
    
    $data = [
        'q' => $text,
        'source' => 'en',
        'target' => $targetLocale,
        'key' => $apiKey
    ];
    
    Logger::info("2 ====Данные для запроса=====", "", ["data" => $data]);
    
    $url = 'https://translation.googleapis.com/language/translate/v2?' . http_build_query($data);
    
    Logger::info("3 ====Отправляем запрос к Google=====");
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    Logger::info("4 ====Получен ответ от Google=====", "", ["resultLength" => strlen($result), "result" => $result]);
    
    if ($httpCode !== 200) {
        Logger::error("5 ====Ошибка HTTP=====", "", ["httpCode" => $httpCode, "result" => $result]);
        return false;
    }
    
    $response = json_decode($result, true);
    Logger::info("6 ====Ответ декодирован=====", "", ["response" => $response]);
    
    if (isset($response['data']['translations'][0]['translatedText'])) {
        $translatedText = $response['data']['translations'][0]['translatedText'];
        Logger::info("7 ====Перевод успешен=====", "", ["translatedText" => $translatedText]);
        return html_entity_decode($translatedText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    } else {
        Logger::error("8 ====Ошибка в ответе=====", "", ["response" => $response]);
        return false;
    }
}
?>