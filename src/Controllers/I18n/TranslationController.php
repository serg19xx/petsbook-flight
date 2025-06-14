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

    public function translateLanguage($locale)
    {
        try {
            // 1. Получить все ключи и значения для английского языка
            $stmt = $this->db->prepare("
                SELECT tk.id as key_id, tv.value
                FROM i18n_translation_keys tk
                JOIN i18n_translation_values tv ON tk.id = tv.key_id
                WHERE tv.locale = 'en'
            ");
            $stmt->execute();
            $englishStrings = $stmt->fetchAll();

            // 2. Перевести каждую строку на целевой язык
            $translations = [];
            foreach ($englishStrings as $string) {
                $result = $this->googleTranslate->translate($string['value'], $locale);
                if ($result) {
                    // Декодируем HTML-сущности в переведенном тексте
                    $translatedText = html_entity_decode($result['text'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    
                    $translations[] = [
                        'key_id' => $string['key_id'],
                        'locale' => $locale,
                        'value' => $translatedText,
                        'is_auto_translated' => true
                    ];
                }
            }

            // 3. Сохранить переводы в базу
            $stmt = $this->db->prepare("
                INSERT INTO i18n_translation_values 
                (key_id, locale, value, is_auto_translated) 
                VALUES 
                (:key_id, :locale, :value, :is_auto_translated)
            ");
            foreach ($translations as $translation) {
                $stmt->execute($translation);
            }

            // 4. Обновить флаг already_translated
            $stmt = $this->db->prepare("
                UPDATE i18n_locales SET already_translated = 1 WHERE code = ?
            ");
            $stmt->execute([$locale]);

            return Flight::json([
                'status' => 200,
                'message' => "Language $locale translated and added successfully"
            ]);
        } catch (\Exception $e) {
            return Flight::json([
                'status' => 500,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add new translation keys
     */
    public function addTranslationKeys()
    {
        try {
            // Начинаем транзакцию
            $this->db->beginTransaction();

            // Добавляем новые ключи
            $stmt = $this->db->prepare("
                INSERT INTO i18n_translation_keys (key_name, namespace, description) 
                VALUES 
                ('UI.editprofile.fields.full_name', 'UI', 'Full name field label in edit profile form'),
                ('UI.editprofile.fields.location', 'UI', 'Location field label in edit profile form')
            ");
            $stmt->execute();

            // Получаем ID добавленных ключей
            $keyIds = $this->db->lastInsertId();
            $keyIds = range($keyIds, $keyIds + 1);

            // Добавляем английские значения
            $stmt = $this->db->prepare("
                INSERT INTO i18n_translation_values (key_id, locale, value, is_auto_translated) 
                VALUES 
                (?, 'en', 'Full Name', 0),
                (?, 'en', 'Location', 0)
            ");
            $stmt->execute($keyIds);

            // Добавляем пустые значения для всех остальных языков
            $stmt = $this->db->prepare("
                INSERT INTO i18n_translation_values (key_id, locale, value, is_auto_translated)
                SELECT 
                    k.id as key_id,
                    l.code as locale,
                    NULL as value,
                    0 as is_auto_translated
                FROM i18n_translation_keys k
                CROSS JOIN i18n_locales l
                WHERE k.id IN (?, ?)
                AND l.code != 'en'
            ");
            $stmt->execute($keyIds);

            // Завершаем транзакцию
            $this->db->commit();

            return Flight::json([
                'status' => 200,
                'message' => 'Translation keys added successfully'
            ]);

        } catch (\Exception $e) {
            // Откатываем транзакцию в случае ошибки
            $this->db->rollBack();
            
            return Flight::json([
                'status' => 500,
                'message' => $e->getMessage()
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
}