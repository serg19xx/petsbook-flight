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

        // Группируем переводы по namespace
        $groupedTranslations = [];
        foreach ($translations as $translation) {
            $groupedTranslations[$translation['namespace']][] = [
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
}