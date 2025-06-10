<?php
// app/Services/GoogleTranslateService.php

namespace App\Services;

use Google\Cloud\Translate\TranslateClient;
use App\Utils\Logger;

class GoogleTranslateService
{
    private $translate;
    private $rtlLanguages = [
        'ar', // Arabic
        'he', // Hebrew
        'fa', // Persian
        'ur', // Urdu
        'ps', // Pashto
        'sd', // Sindhi
        'ug', // Uyghur
        'yi', // Yiddish
    ];

    public function __construct()
    {
        $this->translate = new TranslateClient([
            'key' => $_ENV['GOOGLE_TRANSLATE_API_KEY']
        ]);
    }

    /**
     * Get list of RTL languages
     * 
     * @return array List of RTL language codes
     */
    public function getRtlLanguages(): array
    {
        return $this->rtlLanguages;
    }

    /**
     * Get language names from Google Translate API
     * 
     * @param string $locale Language code
     * @return array|null Array with language names or null if not found
     */
    public function getLanguageNames(string $locale): ?array
    {
        try {
            // Получаем название на английском
            $enResult = $this->translate->translate('Language', [
                'target' => 'en',
                'source' => $locale
            ]);

            // Получаем название на родном языке
            $nativeResult = $this->translate->translate('Language', [
                'target' => $locale,
                'source' => 'en'
            ]);

            return [
                'name' => $enResult['text'] ?? null,
                'native_name' => $nativeResult['text'] ?? null
            ];

        } catch (\Exception $e) {
            Logger::error(
                $e->getMessage(),
                'GoogleTranslateService::getLanguageNames',
                ['locale' => $locale]
            );
            return null;
        }
    }

    /**
     * Get native name of the language
     * 
     * @param string $locale Language code
     * @return string Native name
     */
    private function getNativeName(string $locale): string
    {
        try {
            $languages = $this->translate->languages(['target' => $locale]);
            foreach ($languages as $language) {
                if ($language['language'] === $locale) {
                    return $language['name'];
                }
            }
            return $locale;
        } catch (\Exception $e) {
            Logger::error(
                $e->getMessage(),
                'GoogleTranslateService::getNativeName',
                ['locale' => $locale]
            );
            return $locale;
        }
    }

    /**
     * Translate text with direction
     * 
     * @param string $text Text to translate
     * @param string $targetLocale Target language code
     * @return array|null Array with translated text and direction or null on error
     */
    public function translate(string $text, string $targetLocale): ?array
    {
        try {
            $result = $this->translate->translate($text, [
                'target' => $targetLocale,
                'source' => 'en'
            ]);

            return [
                'text' => $result['text'] ?? null,
                'direction' => in_array($targetLocale, $this->rtlLanguages) ? 'rtl' : 'ltr'
            ];

        } catch (\Exception $e) {
            Logger::error(
                $e->getMessage(),
                'GoogleTranslateService::translate',
                [
                    'text' => $text,
                    'targetLocale' => $targetLocale,
                    'error' => $e->getMessage()
                ]
            );
            return null;
        }
    }

    /**
     * Get list of supported languages
     * 
     * @return array List of supported languages
     */
    public function getSupportedLanguages(): array
    {
        try {
            return $this->translate->languages();
        } catch (\Exception $e) {
            Logger::error(
                $e->getMessage(),
                'GoogleTranslateService::getSupportedLanguages',
                ['error' => $e->getMessage()]
            );
            return [];
        }
    }
}