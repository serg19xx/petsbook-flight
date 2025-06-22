<?php
// app/Controllers/I18n/TranslationController.php

namespace App\Controllers\I18n;

use App\Controllers\BaseController;
use \PDO;
use \Flight;
use App\Services\GoogleTranslateService;
use App\Utils\Logger;

class TranslationController extends BaseController
{
    private PDO $db;
    private $googleTranslate;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->googleTranslate = new GoogleTranslateService();
    }

    /**
     * Get all translations for specific locale
     * 
     * @param string $locale Locale code (e.g., 'en', 'ru')
     * @return void
     */
    public function getByLocale($locale)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM v_i18n_translations 
            WHERE locale = ?
        ");
        $stmt->execute([$locale]);
        $translations = $stmt->fetchAll();

        if (empty($translations)) {
            return Flight::json([
                'status' => 404,
                'error_code' => 'TRANSLATIONS_NOT_FOUND',
                'message' => 'Translations not found for this locale',
                'data' => null
            ], 404);
        }

        // Группируем переводы по namespace и делаем плоскую структуру
        $groupedTranslations = [];
        foreach ($translations as $translation) {
            $namespace = $translation['namespace'];
            $key = $namespace . '.' . $translation['key_name'];
            $groupedTranslations[$key] = $translation['value'];
        }

        return Flight::json([
            'status' => 200,
            'error_code' => 'SUCCESS',
            'message' => 'Translations retrieved successfully',
            'data' => [
                'translations' => $groupedTranslations
            ]
        ], 200);
    }

    /**
     * Get translations by namespace for specific locale
     * 
     * @param string $locale Locale code (e.g., 'en', 'ru')
     * @param string $namespace Namespace (e.g., 'ui', 'email')
     * @return void
     */
    public function getByNamespace($locale, $namespace)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM v_i18n_translations 
            WHERE locale = ? AND namespace = ?
        ");
        $stmt->execute([$locale, $namespace]);
        $translations = $stmt->fetchAll();

        if (empty($translations)) {
            return Flight::json([
                'status' => 404,
                'error_code' => 'TRANSLATIONS_NOT_FOUND',
                'message' => 'Translations not found for this locale and namespace',
                'data' => null
            ], 404);
        }

        // Форматируем переводы
        $formattedTranslations = [];
        foreach ($translations as $translation) {
            $formattedTranslations[] = [
                'key' => $translation['key_name'],
                'value' => $translation['value'],
                'description' => $translation['description'],
                'is_auto_translated' => (bool)$translation['is_auto_translated']
            ];
        }

        return Flight::json([
            'status' => 200,
            'error_code' => 'SUCCESS',
            'message' => 'Translations retrieved successfully',
            'data' => [
                'namespace' => $namespace,
                'translations' => $formattedTranslations
            ]
        ], 200);
    }

    /**
     * Add new language with automatic translation
     * 
     * @param string $locale Language code
     * @return array Response with status and message
     */
    public function addLanguage(string $locale): array
    {
        try {
            // Проверяем поддержку языка
            $supportedLanguages = $this->googleTranslate->getSupportedLanguages();
            if (!in_array($locale, $supportedLanguages)) {
                return [
                    'status' => 'error',
                    'message' => "Language $locale is not supported"
                ];
            }

            // Получаем названия языка
            $names = $this->googleTranslate->getLanguageNames($locale);
            if (!$names) {
                return [
                    'status' => 'error',
                    'message' => "Language names not found for $locale"
                ];
            }

            // Добавляем язык в i18n_locales
            $direction = in_array($locale, $this->googleTranslate->getRtlLanguages()) ? 'rtl' : 'ltr';
            $stmt = $this->db->prepare("
                INSERT INTO i18n_locales (code, name, native_name, direction) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $locale, 
                $names['name'],
                $names['native_name'],
                $direction
            ]);

            // Получаем все ключи и их значения на английском
            $stmt = $this->db->prepare("
                SELECT tk.id as key_id, tk.key_name, tk.namespace, tk.description, tv.value
                FROM i18n_translation_keys tk
                JOIN i18n_translation_values tv ON tk.id = tv.key_id
                WHERE tv.locale = 'en'
            ");
            $stmt->execute();
            $englishStrings = $stmt->fetchAll();

            // Добавляем новые ключи, если их нет
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
                $stmt = $this->db->prepare("
                    SELECT id FROM i18n_translation_keys 
                    WHERE key_name = ?
                ");
                $stmt->execute([$key['key_name']]);
                $existingKey = $stmt->fetch();

                if (!$existingKey) {
                    // Добавляем новый ключ
                    $stmt = $this->db->prepare("
                        INSERT INTO i18n_translation_keys (key_name, namespace, description)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$key['key_name'], $key['namespace'], $key['description']]);
                    $keyId = $this->db->lastInsertId();

                    // Добавляем английское значение
                    $stmt = $this->db->prepare("
                        INSERT INTO i18n_translation_values (key_id, locale, value, is_auto_translated)
                        VALUES (?, 'en', ?, 0)
                    ");
                    $stmt->execute([$keyId, $key['value']]);

                    // Добавляем ключ в список для перевода
                    $englishStrings[] = [
                        'key_id' => $keyId,
                        'value' => $key['value']
                    ];
                }
            }

            // Переводим каждую строку
            $translations = [];
            foreach ($englishStrings as $string) {
                $result = $this->googleTranslate->translate($string['value'], $locale);
                if ($result) {
                    $translations[] = [
                        'key_id' => $string['key_id'],
                        'locale' => $locale,
                        'value' => $result['text'],
                        'is_auto_translated' => true
                    ];
                }
            }

            // Сохраняем переводы в базу
            $stmt = $this->db->prepare("
                INSERT INTO i18n_translation_values 
                (key_id, locale, value, is_auto_translated) 
                VALUES 
                (:key_id, :locale, :value, :is_auto_translated)
            ");

            foreach ($translations as $translation) {
                $stmt->execute($translation);
            }

            return [
                'status' => 'success',
                'message' => "Language $locale added successfully",
                'translations' => count($translations)
            ];

        } catch (\Exception $e) {
            Logger::error(
                $e->getMessage(),
                'TranslationController::addLanguage',
                ['locale' => $locale]
            );
            return [
                'status' => 'error',
                'message' => 'Failed to add language'
            ];
        }
    }

    /**
     * Get list of translated languages
     * 
     * @return void
     */
    public function getTranslatedLanguages()
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * 
                FROM i18n_locales
                WHERE flag_icon IS NOT NULL AND flag_icon != ''
                  AND already_translated = 1
                ORDER BY name
            ");
            $stmt->execute();
            $languages = $stmt->fetchAll();

            Flight::json([
                'status' => 200,
                'data' => [
                    'languages' => $languages
                ]
            ]);
        } catch (\Exception $e) {
            Flight::json([
                'status' => 500,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get list of available languages for translation
     * 
     * @return void
     */
    public function getAvailableLanguages()
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * 
                FROM i18n_locales
                WHERE show_in_dialog=1
                  AND already_translated = 0
                ORDER BY name
            ");
            $stmt->execute();
            $languages = $stmt->fetchAll();

            Flight::json([
                'status' => 200,
                'data' => [
                    'languages' => $languages
                ]
            ]);
        } catch (\Exception $e) {
            Flight::json([
                'status' => 500,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Initialize languages from Google Translate
     */
    public function initializeLanguages()
    {
        try {
            Logger::info(
                'Starting languages initialization',
                'TranslationController::initializeLanguages'
            );
            
            // Получаем список языков от Google
            $languages = $this->googleTranslate->getSupportedLanguages();
            
            Logger::info(
                'Languages received from Google Translate',
                'TranslationController::initializeLanguages',
                ['languages' => $languages]
            );
            
            if (empty($languages)) {
                throw new \Exception('No languages received from Google Translate');
            }
            
            // Очищаем таблицу
            $this->db->query("TRUNCATE TABLE i18n_locales");
            
            // Добавляем языки
            $stmt = $this->db->prepare("
                INSERT INTO i18n_locales 
                (code, name, native_name, direction, is_default, is_enabled, already_translated) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $addedCount = 0;
            foreach ($languages as $lang) {
                if (!isset($lang['language']) || !isset($lang['name'])) {
                    Logger::warning(
                        'Invalid language format',
                        'TranslationController::initializeLanguages',
                        ['lang' => $lang]
                    );
                    continue;
                }
                
                $code = $lang['language'];
                $name = $lang['name'];
                $nativeName = $lang['native_name'] ?? $name;
                
                $stmt->execute([
                    $code,
                    $name,
                    $nativeName,
                    'ltr',
                    $code === 'en' ? 1 : 0,
                    1,
                    $code === 'en' || $code === 'ru' ? 1 : 0
                ]);
                
                $addedCount++;
            }
            
            Logger::info(
                'Languages initialized successfully',
                'TranslationController::initializeLanguages',
                ['count' => $addedCount]
            );
            
            return Flight::json([
                'status' => 200,
                'error_code' => 'SUCCESS',
                'message' => 'Languages initialized successfully',
                'data' => ['count' => $addedCount]
            ]);
            
        } catch (\Exception $e) {
            Logger::error(
                $e->getMessage(),
                'TranslationController::initializeLanguages',
                ['error' => $e->getMessage()]
            );
            
            return Flight::json([
                'status' => 500,
                'error_code' => 'INTERNAL_ERROR',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function createTranslationTask($locale) {
        $sql = "INSERT INTO translation_tasks (language_code, status, created_at, updated_at) VALUES (?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$locale, 'pending', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
        
        return $this->db->lastInsertId();
    }    

    public function startTranslationInBackground($taskId, $locale) {
        // Запускаем перевод в фоне
        // Вариант 1: Через exec (если есть доступ к командной строке)
        $command = "php /path/to/your/translation-worker.php $taskId $locale > /dev/null 2>&1 &";
        exec($command);
        
        // Вариант 2: Через shell_exec
        // shell_exec("php /path/to/your/translation-worker.php $taskId $locale > /dev/null 2>&1 &");
        
        // Вариант 3: Если нет доступа к exec, можно использовать cron job
        // Просто обновляем статус, а cron будет проверять pending задачи
    }

    public function translateLanguage($locale){
        Logger::warning("Method translateLanguage called", "", ['locale' => $locale]);
        try {
            Logger::info(
                "Starting translation for locale $locale",
                'TranslationController::translateLanguage'
            );
            
            // 1. Создаем запись в таблице
            Logger::info("Preparing SQL statement", 'TranslationController::translateLanguage');
            
            $stmt = $this->db->prepare("INSERT INTO i18n_translation_tasks (locale, status, total_strings, processed_strings, skipped_strings) VALUES (?, ?, ?, ?, ?)");
            
            Logger::info("Executing SQL statement", 'TranslationController::translateLanguage');
            $stmt->execute([$locale, 'pending', 0, 0, 0]);
            
            Logger::info("SQL executed successfully", 'TranslationController::translateLanguage');
            $taskId = $this->db->lastInsertId();
            
            Logger::info("Task ID: $taskId", 'TranslationController::translateLanguage');

//==================================================

// 2. Запускаем фоновый процесс

$currentDir = __DIR__;
$projectRoot = dirname(dirname(dirname($currentDir))); // Поднимаемся на 3 уровня выше
$filePath = $projectRoot . "/translate-task.php";

Logger::info(
    "File path check",
    'TranslationController::translateLanguage',
    [
        'filePath' => $filePath,
        'exists' => file_exists($filePath)
    ]
);

if (!file_exists($filePath)) {
    Logger::error(
        "File not found",
        'TranslationController::translateLanguage',
        ['filePath' => $filePath]
    );
    throw new Exception("translate-task.php not found at: $filePath");
}

$command = "php " . $filePath . " $taskId > /dev/null 2>&1 &";
Logger::info(
    "Executing command: $command",
    'TranslationController::translateLanguage'
);

//$result = exec($command, $output, $returnCode);
//$result = shell_exec($command);
pclose(popen($command, 'r'));
Logger::info(
    "Command result",
    'TranslationController::translateLanguage',
    [
        //'result' => $result,
        //'output' => $output || '',
        //'returnCode' => $returnCode || 0
    ]
);

//==================================================
          
            
            Logger::info(
                "Background process started for task $taskId",
                'TranslationController::translateLanguage'
            );
            
            return Flight::json([
                'status' => 200,
                'error_code' => 'USER_DATA_SUCCESS',
                'message' => '',
                'data' => [
                    'taskId' => $taskId
                ]
            ], 200);
            
        } catch (\PDOException $e) {
            Logger::error(
                "Database error in translation",
                'TranslationController::translateLanguage',
                [
                    'locale' => $locale, 
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            );
            
            return Flight::json([
                'status' => 500,
                'error_code' => 'DATABASE_ERROR',
                'message' => 'Database error occurred',
                'data' => []
            ], 500);
            
        } catch (\Exception $e) {
            Logger::error(
                "Translation failed",
                'TranslationController::translateLanguage',
                ['locale' => $locale, 'error' => $e->getMessage()]
            );
            
            return Flight::json([
                'status' => 500,
                'error_code' => 'TRANSLATION_FAILED',
                'message' => 'Translation failed',
                'data' => []
            ], 500);
        }
    }

    /**
     * Start translation task for specific locale
     * 
     * @param string $locale Locale code (e.g., 'en', 'ru')
     * @return void
     */


    /**
     * Get translation task status
     * 
     * @param int $taskId Task ID
     * @return void
     */
    public function getTaskStatus($taskId) {
        try {
            Logger::info(
                "Checking status for task $taskId",
                'TranslationController::getTaskStatus'
            );
            
            // Получаем данные задачи из БД
            $stmt = $this->db->prepare("SELECT * FROM i18n_translation_tasks WHERE id = ?");
            $stmt->execute([$taskId]);
            $task = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$task) {
                Logger::warning(
                    "Task not found",
                    'TranslationController::getTaskStatus',
                    ['taskId' => $taskId]
                );
                
                return Flight::json([
                    'status' => 404,
                    'error_code' => 'TASK_NOT_FOUND',
                    'message' => 'Task not found',
                    'data' => []
                ], 404);
            }
            
            // Вычисляем прогресс
            $progress = $task['total_strings'] > 0 ? 
                round(($task['processed_strings'] / $task['total_strings']) * 100) : 0;
            
            Logger::info(
                "Task status retrieved",
                'TranslationController::getTaskStatus',
                [
                    'taskId' => $taskId,
                    'status' => $task['status'],
                    'progress' => $progress
                ]
            );
            
            return Flight::json([
                'status' => 200,
                'error_code' => 'USER_DATA_SUCCESS',
                'message' => '',
                'data' => [
                    'taskId' => $taskId,
                    'status' => $task['status'],
                    'progress' => $progress,
                    'processed_strings' => $task['processed_strings'],
                    'total_strings' => $task['total_strings'],
                    'skipped_strings' => $task['skipped_strings'],
                    'errors' => $task['errors'] ? json_decode($task['errors'], true) : null,
                    'created_at' => $task['created_at'],
                    'updated_at' => $task['updated_at'],
                    'completed_at' => $task['completed_at']
                ]
            ], 200);
            
        } catch (\PDOException $e) {
            Logger::error(
                "Database error in getTaskStatus",
                'TranslationController::getTaskStatus',
                [
                    'taskId' => $taskId,
                    'error' => $e->getMessage(),
                    'code' => $e->getCode()
                ]
            );
            
            return Flight::json([
                'status' => 500,
                'error_code' => 'DATABASE_ERROR',
                'message' => 'Database error occurred',
                'data' => []
            ], 500);
            
        } catch (\Exception $e) {
            Logger::error(
                "Error in getTaskStatus",
                'TranslationController::getTaskStatus',
                ['taskId' => $taskId, 'error' => $e->getMessage()]
            );
            
            return Flight::json([
                'status' => 500,
                'error_code' => 'INTERNAL_ERROR',
                'message' => 'Internal error occurred',
                'data' => []
            ], 500);
        }
    }    

    /**
     * Add a single translation key with its English value
     * 
     * @return void
     */
    public function addTranslationKey()
    {
        try {
            $input = json_decode(Flight::request()->getBody(), true);
            
            if (!$input) {
                return Flight::json([
                    'status' => 400,
                    'error_code' => 'INVALID_INPUT',
                    'message' => 'Invalid JSON input'
                ], 400);
            }

            $keyName = $input['key_name'] ?? null;
            $namespace = $input['namespace'] ?? null;
            $description = $input['description'] ?? null;
            $englishValue = $input['value'] ?? null; // Это английское значение от фронтенда

            if (!$keyName || !$namespace || !$englishValue) {
                return Flight::json([
                    'status' => 400,
                    'error_code' => 'MISSING_REQUIRED_FIELDS',
                    'message' => 'key_name, namespace, and value are required'
                ], 400);
            }

            // Начинаем транзакцию
            $this->db->beginTransaction();

            // Проверяем, существует ли ключ
            $stmt = $this->db->prepare("
                SELECT id FROM i18n_translation_keys 
                WHERE key_name = ?
            ");
            $stmt->execute([$keyName]);
            $existingKey = $stmt->fetch();

            if ($existingKey) {
                $this->db->rollBack();
                return Flight::json([
                    'status' => 409,
                    'error_code' => 'KEY_ALREADY_EXISTS',
                    'message' => 'Translation key already exists'
                ], 409);
            }

            // Добавляем новый ключ
            $stmt = $this->db->prepare("
                INSERT INTO i18n_translation_keys (key_name, namespace, description)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$keyName, $namespace, $description]);
            $keyId = $this->db->lastInsertId();

            // Добавляем английское значение (базовое, не переводим)
            $stmt = $this->db->prepare("
                INSERT INTO i18n_translation_values (key_id, locale, value, is_auto_translated)
                VALUES (?, 'en', ?, 0)
            ");
            $stmt->execute([$keyId, $englishValue]);

            // Получаем все языки, которые уже имеют переводы (кроме английского)
            $stmt = $this->db->prepare("
                SELECT code FROM i18n_locales 
                WHERE already_translated = 1 AND code != 'en'
            ");
            $stmt->execute();
            $translatedLanguages = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Переводим с английского на другие языки
            foreach ($translatedLanguages as $lang) {
                $result = $this->googleTranslate->translate($englishValue, $lang);
                if ($result) {
                    $translatedText = html_entity_decode($result['text'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    
                    $stmt = $this->db->prepare("
                        INSERT INTO i18n_translation_values 
                        (key_id, locale, value, is_auto_translated)
                        VALUES (?, ?, ?, 1)
                    ");
                    $stmt->execute([$keyId, $lang, $translatedText]);
                }
            }

            // Завершаем транзакцию
            $this->db->commit();

            return Flight::json([
                'status' => 200,
                'error_code' => 'SUCCESS',
                'message' => 'Translation key added successfully',
                'data' => [
                    'key_id' => $keyId,
                    'key_name' => $keyName,
                    'namespace' => $namespace,
                    'english_value' => $englishValue,
                    'translated_languages' => $translatedLanguages
                ]
            ], 200);

        } catch (\Exception $e) {
            // Откатываем транзакцию в случае ошибки
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            
            Logger::error(
                "Error adding translation key",
                'TranslationController::addTranslationKey',
                ['error' => $e->getMessage()]
            );
            
            return Flight::json([
                'status' => 500,
                'error_code' => 'INTERNAL_ERROR',
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Translate new keys for all existing languages
     */
    public function translateNewKeys()
    {
        try {
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
                $stmt = $this->db->prepare("
                    SELECT id FROM i18n_translation_keys 
                    WHERE key_name = ?
                ");
                $stmt->execute([$key['key_name']]);
                $existingKey = $stmt->fetch();

                if (!$existingKey) {
                    // Добавляем новый ключ
                    $stmt = $this->db->prepare("
                        INSERT INTO i18n_translation_keys (key_name, namespace, description)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$key['key_name'], $key['namespace'], $key['description']]);
                    $keyId = $this->db->lastInsertId();

                    // Добавляем английское значение
                    $stmt = $this->db->prepare("
                        INSERT INTO i18n_translation_values (key_id, locale, value, is_auto_translated)
                        VALUES (?, 'en', ?, 0)
                    ");
                    $stmt->execute([$keyId, $key['value']]);

                    // Получаем все языки кроме английского
                    $stmt = $this->db->prepare("
                        SELECT code FROM i18n_locales 
                        WHERE code != 'en' AND already_translated = 1
                    ");
                    $stmt->execute();
                    $languages = $stmt->fetchAll(PDO::FETCH_COLUMN);

                    // Переводим для каждого языка
                    foreach ($languages as $lang) {
                        $result = $this->googleTranslate->translate($key['value'], $lang);
                        if ($result) {
                            $translatedText = html_entity_decode($result['text'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            
                            $stmt = $this->db->prepare("
                                INSERT INTO i18n_translation_values 
                                (key_id, locale, value, is_auto_translated)
                                VALUES (?, ?, ?, 1)
                            ");
                            $stmt->execute([$keyId, $lang, $translatedText]);
                        }
                    }
                }
            }

            return Flight::json([
                'status' => 200,
                'message' => 'New keys translated successfully'
            ]);

        } catch (\Exception $e) {
            return Flight::json([
                'status' => 500,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clean up all translations except English and reset language states
     */
    public function cleanupTranslations()
    {
        Logger::info(
            "Starting translations cleanup",
            'TranslationController::cleanupTranslations'
        );

        try {
            // Начинаем транзакцию
            $this->db->beginTransaction();

            Logger::info(
                "Transaction started",
                'TranslationController::cleanupTranslations'
            );

            // Получаем количество переводов до удаления
            $stmt = $this->db->query("SELECT COUNT(*) as count FROM i18n_translation_values WHERE locale != 'en'");
            $beforeCount = $stmt->fetch()['count'];

            Logger::info(
                "Found translations to delete",
                'TranslationController::cleanupTranslations',
                ['count' => $beforeCount]
            );

            // 1. Удаляем все переводы кроме английского
            $stmt = $this->db->prepare("
                DELETE FROM i18n_translation_values 
                WHERE locale != 'en'
            ");
            $stmt->execute();
            $deletedCount = $stmt->rowCount();

            Logger::info(
                "Deleted translations",
                'TranslationController::cleanupTranslations',
                ['count' => $deletedCount]
            );

            // Получаем количество языков для сброса
            $stmt = $this->db->query("SELECT COUNT(*) as count FROM i18n_locales WHERE code != 'en'");
            $localesCount = $stmt->fetch()['count'];

            Logger::info(
                "Found locales to reset",
                'TranslationController::cleanupTranslations',
                ['count' => $localesCount]
            );

            // 2. Сбрасываем флаг already_translated для всех языков кроме английского
            $stmt = $this->db->prepare("
                UPDATE i18n_locales 
                SET already_translated = 0 
                WHERE code != 'en'
            ");
            $stmt->execute();
            $resetLocalesCount = $stmt->rowCount();

            Logger::info(
                "Reset locales",
                'TranslationController::cleanupTranslations',
                ['count' => $resetLocalesCount]
            );

            // Получаем количество английских переводов
            $stmt = $this->db->query("SELECT COUNT(*) as count FROM i18n_translation_values WHERE locale = 'en'");
            $englishCount = $stmt->fetch()['count'];

            Logger::info(
                "Found English translations",
                'TranslationController::cleanupTranslations',
                ['count' => $englishCount]
            );

            // 3. Сбрасываем флаг is_auto_translated для английских переводов
            $stmt = $this->db->prepare("
                UPDATE i18n_translation_values 
                SET is_auto_translated = 0 
                WHERE locale = 'en'
            ");
            $stmt->execute();
            $resetEnglishCount = $stmt->rowCount();

            Logger::info(
                "Reset English translations",
                'TranslationController::cleanupTranslations',
                ['count' => $resetEnglishCount]
            );

            // Завершаем транзакцию
            $this->db->commit();

            Logger::info(
                "Transaction committed",
                'TranslationController::cleanupTranslations'
            );

            $response = [
                'status' => 200,
                'message' => 'Translations cleanup completed successfully',
                'data' => [
                    'before_count' => $beforeCount,
                    'deleted_translations' => $deletedCount,
                    'total_locales' => $localesCount,
                    'reset_locales' => $resetLocalesCount,
                    'total_english' => $englishCount,
                    'reset_english' => $resetEnglishCount
                ]
            ];

            Logger::info(
                "Sending response",
                'TranslationController::cleanupTranslations',
                ['response' => $response]
            );

            return Flight::json($response);

        } catch (\Exception $e) {
            // Откатываем транзакцию в случае ошибки
            $this->db->rollBack();

            Logger::error(
                "Translations cleanup failed",
                'TranslationController::cleanupTranslations',
                ['error' => $e->getMessage()]
            );

            return Flight::json([
                'status' => 500,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all translation keys with their English values only
     * 
     * @return void
     */
    public function getAllTranslationKeys()
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    tk.id as key_id,
                    tk.key_name,
                    tk.namespace,
                    tk.description,
                    tv.value as english_value,
                    tv.is_auto_translated
                FROM i18n_translation_keys tk
                LEFT JOIN i18n_translation_values tv ON tk.id = tv.key_id AND tv.locale = 'en'
                ORDER BY tk.namespace, tk.key_name
            ");
            $stmt->execute();
            $results = $stmt->fetchAll();

            // Форматируем результат
            $formattedKeys = [];
            foreach ($results as $row) {
                $formattedKeys[] = [
                    'key_id' => $row['key_id'],
                    'key_name' => $row['key_name'],
                    'namespace' => $row['namespace'],
                    'description' => $row['description'],
                    'english_value' => $row['english_value'],
                    'is_auto_translated' => (bool)$row['is_auto_translated']
                ];
            }

            return Flight::json([
                'status' => 200,
                'error_code' => 'SUCCESS',
                'message' => 'All translation keys retrieved successfully',
                'data' => [
                    'keys' => $formattedKeys
                ]
            ], 200);

        } catch (\Exception $e) {
            Logger::error(
                "Error getting all translation keys",
                'TranslationController::getAllTranslationKeys',
                ['error' => $e->getMessage()]
            );
            
            return Flight::json([
                'status' => 500,
                'error_code' => 'INTERNAL_ERROR',
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update translation key and its values
     * 
     * @return void
     */
    public function updateTranslationKey()
    {
        try {
            $input = json_decode(Flight::request()->getBody(), true);
            
            if (!$input) {
                return Flight::json([
                    'status' => 400,
                    'error_code' => 'INVALID_INPUT',
                    'message' => 'Invalid JSON input'
                ], 400);
            }
    
            $keyId = $input['key_id'] ?? null;
            $keyName = $input['key_name'] ?? null;
            $namespace = $input['namespace'] ?? null;
            $description = $input['description'] ?? null;
            $newValue = $input['value'] ?? null;
    
            if (!$keyId || !$keyName || !$namespace) {
                return Flight::json([
                    'status' => 400,
                    'error_code' => 'MISSING_REQUIRED_FIELDS',
                    'message' => 'key_id, key_name, and namespace are required'
                ], 400);
            }
    
            // Начинаем транзакцию
            $this->db->beginTransaction();
    
            // Проверяем, существует ли ключ
            $stmt = $this->db->prepare("
                SELECT id FROM i18n_translation_keys 
                WHERE id = ?
            ");
            $stmt->execute([$keyId]);
            $existingKey = $stmt->fetch();
    
            if (!$existingKey) {
                $this->db->rollBack();
                return Flight::json([
                    'status' => 404,
                    'error_code' => 'KEY_NOT_FOUND',
                    'message' => 'Translation key not found'
                ], 404);
            }
    
            // Получаем текущее значение для сравнения
            $stmt = $this->db->prepare("
                SELECT value FROM i18n_translation_values 
                WHERE key_id = ? AND locale = 'en'
            ");
            $stmt->execute([$keyId]);
            $currentValue = $stmt->fetch();
    
            // Обновляем ключ
            $stmt = $this->db->prepare("
                UPDATE i18n_translation_keys 
                SET key_name = ?, namespace = ?, description = ?
                WHERE id = ?
            ");
            $stmt->execute([$keyName, $namespace, $description, $keyId]);
    
            // Обновляем английское значение
            if ($newValue) {
                $stmt = $this->db->prepare("
                    UPDATE i18n_translation_values 
                    SET value = ?, updated_at = NOW()
                    WHERE key_id = ? AND locale = 'en'
                ");
                $stmt->execute([$newValue, $keyId]);
    
                // Проверяем, изменилось ли значение
                $valueChanged = ($currentValue && $currentValue['value'] !== $newValue);
    
                // Если значение изменилось, обновляем переводы для других языков
                if ($valueChanged) {
                    // Получаем все переведенные языки (кроме английского)
                    $stmt = $this->db->prepare("
                        SELECT DISTINCT locale 
                        FROM i18n_translation_values 
                        WHERE key_id = ? AND locale != 'en'
                    ");
                    $stmt->execute([$keyId]);
                    $translatedLanguages = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
                    // Для каждого языка переводим новое значение
                    foreach ($translatedLanguages as $locale) {
                        try {
                            // Здесь вызываем метод перевода
                            $translatedText = $this->translateText($newValue, 'en', $locale);
                            
                            // Обновляем перевод
                            $stmt = $this->db->prepare("
                                UPDATE i18n_translation_values 
                                SET value = ?, is_auto_translated = 1, updated_at = NOW()
                                WHERE key_id = ? AND locale = ?
                            ");
                            $stmt->execute([$translatedText, $keyId, $locale]);
                            
                        } catch (\Exception $e) {
                            Logger::warning(
                                "Failed to translate text for locale: $locale",
                                'TranslationController::updateTranslationKey',
                                ['error' => $e->getMessage()]
                            );
                        }
                    }
                }
            }
    
            // Завершаем транзакцию
            $this->db->commit();
    
            return Flight::json([
                'status' => 200,
                'error_code' => 'SUCCESS',
                'message' => 'Translation key updated successfully',
                'data' => [
                    'key_id' => $keyId,
                    'key_name' => $keyName,
                    'namespace' => $namespace,
                    'value' => $newValue,
                    'value_changed' => $valueChanged ?? false
                ]
            ], 200);
    
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            
            Logger::error(
                "Error updating translation key",
                'TranslationController::updateTranslationKey',
                ['error' => $e->getMessage()]
            );
            
            return Flight::json([
                'status' => 500,
                'error_code' => 'INTERNAL_ERROR',
                'message' => 'Internal server error: ' . $e->getMessage()
            ], 500);
        }
    }
    
    // Добавить метод перевода (если его нет)
    private function translateText($text, $sourceLang, $targetLang)
    {
        // Здесь должна быть логика перевода через Google Translate API
        // Используйте тот же код, что и в translate-task.php
        
        // Пример:
        $apiKey = $_ENV['GOOGLE_TRANSLATE_API_KEY'];
        $url = "https://translation.googleapis.com/language/translate/v2?key=" . $apiKey;
        
        $data = [
            'q' => $text,
            'source' => $sourceLang,
            'target' => $targetLang
        ];
        
        $response = file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => json_encode($data)
            ]
        ]));
        
        $result = json_decode($response, true);
        
        if (isset($result['data']['translations'][0]['translatedText'])) {
            return $result['data']['translations'][0]['translatedText'];
        }
        
        throw new \Exception('Translation failed');
    }

    /**
     * Delete translation key and all its values
     * 
     * @return void
     */
    public function deleteTranslationKey()
    {
        try {
            $input = json_decode(Flight::request()->getBody(), true);
            
            if (!$input) {
                return Flight::json([
                    'status' => 400,
                    'error_code' => 'INVALID_INPUT',
                    'message' => 'Invalid JSON input'
                ], 400);
            }

            $keyId = $input['key_id'] ?? null;

            if (!$keyId) {
                return Flight::json([
                    'status' => 400,
                    'error_code' => 'MISSING_REQUIRED_FIELDS',
                    'message' => 'key_id is required'
                ], 400);
            }

            // Начинаем транзакцию
            $this->db->beginTransaction();

            // Проверяем, существует ли ключ
            $stmt = $this->db->prepare("
                SELECT id, key_name FROM i18n_translation_keys 
                WHERE id = ?
            ");
            $stmt->execute([$keyId]);
            $existingKey = $stmt->fetch();

            if (!$existingKey) {
                $this->db->rollBack();
                return Flight::json([
                    'status' => 404,
                    'error_code' => 'KEY_NOT_FOUND',
                    'message' => 'Translation key not found'
                ], 404);
            }

            // Удаляем все значения перевода для этого ключа
            $stmt = $this->db->prepare("
                DELETE FROM i18n_translation_values 
                WHERE key_id = ?
            ");
            $stmt->execute([$keyId]);

            // Удаляем сам ключ
            $stmt = $this->db->prepare("
                DELETE FROM i18n_translation_keys 
                WHERE id = ?
            ");
            $stmt->execute([$keyId]);

            // Завершаем транзакцию
            $this->db->commit();

            return Flight::json([
                'status' => 200,
                'error_code' => 'SUCCESS',
                'message' => 'Translation key deleted successfully',
                'data' => [
                    'deleted_key_id' => $keyId,
                    'deleted_key_name' => $existingKey['key_name']
                ]
            ], 200);

        } catch (\Exception $e) {
            // Откатываем транзакцию в случае ошибки
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            
            Logger::error(
                "Error deleting translation key",
                'TranslationController::deleteTranslationKey',
                ['error' => $e->getMessage()]
            );
            
            return Flight::json([
                'status' => 500,
                'error_code' => 'INTERNAL_ERROR',
                'message' => 'Internal server error'
            ], 500);
        }
    }
}