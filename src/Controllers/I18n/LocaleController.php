<?php
// app/Controllers/I18n/LocaleController.php

namespace App\Controllers\I18n;

use App\Controllers\BaseController;
use \PDO;
use \Flight;  // Добавляем импорт Flight

class LocaleController extends BaseController
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Get list of all available locales
     * 
     * @return void
     */
    public function index()
    {
        $stmt = $this->db->query("SELECT * FROM v_i18n_locales");
        $locales = $stmt->fetchAll();

        return Flight::json([
            'status' => 200,
            'error_code' => 'SUCCESS',
            'message' => 'Locales retrieved successfully',
            'data' => [
                'locales' => $locales
            ]
        ], 200);
    }

    /**
     * Get information about specific locale
     * 
     * @param string $code Locale code (e.g., 'en', 'ru')
     * @return void
     */
    public function show($code)
    {
        $stmt = $this->db->prepare("SELECT * FROM v_i18n_locales WHERE code = ?");
        $stmt->execute([$code]);
        $locale = $stmt->fetch();

        if (!$locale) {
            return Flight::json([
                'status' => 404,
                'error_code' => 'LOCALE_NOT_FOUND',
                'message' => 'Locale not found',
                'data' => null
            ], 404);
        }

        return Flight::json([
            'status' => 200,
            'error_code' => 'SUCCESS',
            'message' => 'Locale retrieved successfully',
            'data' => [
                'locale' => $locale
            ]
        ], 200);
    }
}