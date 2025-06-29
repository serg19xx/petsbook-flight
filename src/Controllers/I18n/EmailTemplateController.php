<?php

namespace App\Controllers\I18n;

use App\Controllers\BaseController;
use App\Utils\Logger;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use \PDO;
use \Flight;
use App\Services\GoogleTranslateService;

/**
 * Email Template Controller
 * 
 * Handles email template management including layouts and template content
 */
class EmailTemplateController extends BaseController
{
    private PDO $db;
    private $uploadDir;
    private $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
    private GoogleTranslateService $translateService;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->translateService = new GoogleTranslateService();
        
        // Создаем директорию для изображений email шаблонов если она не существует
        $this->uploadDir = __DIR__ . '/../../public/profile-images/email-tmpl/';
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    /**
     * Validate JWT token from cookie
     * 
     * @return array|null User data or null if invalid
     */
    private function validateToken()
    {
        $token = $_COOKIE['auth_token'] ?? null;
        
        if (!$token) {
            return null;
        }

        try {
            $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
            return [
                'user_id' => $decoded->user_id,
                'role' => $decoded->role
            ];
        } catch (\Exception $e) {
            Logger::error("Invalid token in EmailTemplateController", "EmailTemplateController", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get all available email templates
     * 
     * @return void JSON response with available email templates
     * 
     * @api {get} /api/email-templates Get email templates
     * @apiSuccess {Array} templates List of available email templates
     */
    public function getTemplates()
    {
        // Проверяем токен
        //$userData = $this->validateToken();
        //if (!$userData) {
        //    return Flight::json(['success' => false, 'error' => 'No token provided'], 401);
        //}

        Logger::info("Getting email templates", "EmailTemplateController", [
            //'user_id' => $userData['user_id']
        ]);

        try {
            $stmt = $this->db->prepare("SELECT * FROM v_email_templates");
            $stmt->execute();
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Logger::info("Email templates retrieved successfully", "EmailTemplateController", [
                'count' => count($templates)
                //'user_id' => $userData['user_id']
            ]);

            return Flight::json([
                'status' => 200,
                'error_code' => 'SUCCESS',
                'message' => 'Email templates retrieved successfully',
                'data' => [
                    'templates' => $templates
                ]
            ], 200);

        } catch (\Exception $e) {
            Logger::error("Error getting email templates", "EmailTemplateController", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
                //'user_id' => $userData['user_id']
            ]);

            return Flight::json([
                'status' => 500,
                'error_code' => 'SYSTEM_ERROR',
                'message' => 'Failed to retrieve email templates',
                'data' => null
            ], 500);
        }
    }

    /**
     * Serve email template images
     * 
     * @return void Serves image file or JSON error
     */
    public function serveImage() {
        // Проверяем токен
        //$userData = $this->validateToken();
        //if (!$userData) {
        //    return Flight::json(['error' => 'Unauthorized'], 401);
        //}

        $path = Flight::request()->url;
        $filename = basename($path);
        
        // Поддерживаем оба варианта URL
        $filePath = __DIR__ . '/../../public/profile-images/email-tmpl/' . $filename;
        
        // Отладочная информация
        Logger::info("Serving email template image", "EmailTemplateController", [
            'request_url' => $path,
            'filename' => $filename,
            'full_path' => $filePath,
            'file_exists' => file_exists($filePath),
            'is_readable' => is_readable($filePath)
            //'user_id' => $userData['user_id']
        ]);
        
        if (!file_exists($filePath)) {
            Logger::error("Email template image not found", "EmailTemplateController", [
                'request_url' => $path,
                'filename' => $filename,
                'full_path' => $filePath
                //'user_id' => $userData['user_id']
            ]);
            return Flight::json(['error' => 'File not found'], 404);
        }
        
        $mimeType = mime_content_type($filePath);
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
    }

    /**
     * Save email template
     */
    public function saveTemplate()
    {
        // Проверяем токен
        //$userData = $this->validateToken();
        //if (!$userData) {
        //    return Flight::json(['success' => false, 'error' => 'No token provided'], 401);
        //}

        Logger::info("Saving email template", "EmailTemplateController", [
            //'user_id' => $userData['user_id']
        ]);

        try {
            $data = Flight::request()->data;
            
            // Подробное логирование входящих данных
            Logger::info("Incoming data", "EmailTemplateController", [
                'template_id' => $data->template_id ?? 'NOT_SET',
                'code' => $data->code ?? 'NOT_SET',
                'subject' => $data->subject ?? 'NOT_SET',
                'body_html' => $data->body_html ?? 'NOT_SET',
                'layout_id' => $data->layout_id ?? 'NOT_SET',
                'locale' => $data->locale ?? 'NOT_SET',
                'test_data_json' => $data->test_data_json ?? 'NOT_SET'
            ]);
            
            // Валидация входных данных
            if (empty($data->code)) {
                Logger::error("Validation failed: code is empty", "EmailTemplateController");
                return Flight::json(['success' => false, 'error' => 'Code is required'], 400);
            }

            if (empty($data->subject)) {
                Logger::error("Validation failed: subject is empty", "EmailTemplateController");
                return Flight::json(['success' => false, 'error' => 'Subject is required'], 400);
            }

            if (empty($data->body_html)) {
                Logger::error("Validation failed: body_html is empty", "EmailTemplateController");
                return Flight::json(['success' => false, 'error' => 'Body HTML is required'], 400);
            }

            // Определяем template_id - если пустой, null, 0 или не число, то считаем новым
            $templateId = $data->template_id ?? '';
            $isNewTemplate = empty($templateId) || $templateId === '0' || $templateId === 0 || !is_numeric($templateId);
            
            // Устанавливаем значения по умолчанию
            $layoutId = !empty($data->layout_id) ? (int)$data->layout_id : 1;
            $locale = !empty($data->locale) ? $data->locale : 'en';
            $testDataJson = $data->test_data_json ?? null;
            
            Logger::info("Template processing", "EmailTemplateController", [
                'original_template_id' => $data->template_id ?? 'NOT_SET',
                'processed_template_id' => $templateId,
                'is_new_template' => $isNewTemplate,
                'layout_id' => $layoutId,
                'locale' => $locale,
                'test_data_json' => $testDataJson
            ]);
            
            $action = '';
            $finalTemplateId = 0;
            
            if ($isNewTemplate) {
                Logger::info("Creating NEW template", "EmailTemplateController");
                
                // Создаем новый шаблон
                $stmt = $this->db->prepare("
                    INSERT INTO i18n_email_templates (code, subject, body_html, locale, layout_id, to_translate, test_data_json) 
                    VALUES (?, ?, ?, ?, ?, 1, ?)
                ");

                $result = $stmt->execute([
                    $data->code,
                    $data->subject,
                    $data->body_html,
                    $locale,
                    $layoutId,
                    $testDataJson
                ]);

                Logger::info("INSERT result", "EmailTemplateController", [
                    'result' => $result,
                    'last_insert_id' => $this->db->lastInsertId()
                ]);

                if ($result) {
                    $finalTemplateId = $this->db->lastInsertId();
                    $action = 'created';
                } else {
                    Logger::error("Failed to create template", "EmailTemplateController");
                    return Flight::json(['success' => false, 'error' => 'Failed to create template'], 500);
                }
            } else {
                $templateId = (int)$templateId;
                Logger::info("Updating EXISTING template", "EmailTemplateController", [
                    'template_id' => $templateId
                ]);
                
                // Обновляем существующий шаблон
                $stmt = $this->db->prepare("
                    UPDATE i18n_email_templates 
                    SET code = ?, subject = ?, body_html = ?, layout_id = ?, to_translate = 1, test_data_json = ?
                    WHERE id = ?
                ");

                $result = $stmt->execute([
                    $data->code,
                    $data->subject,
                    $data->body_html,
                    $layoutId,
                    $testDataJson,
                    $templateId
                ]);

                Logger::info("UPDATE result", "EmailTemplateController", [
                    'result' => $result,
                    'affected_rows' => $stmt->rowCount(),
                    'template_id' => $templateId
                ]);

                if ($result) {
                    $finalTemplateId = $templateId;
                    $action = 'updated';
                } else {
                    Logger::error("Failed to update template", "EmailTemplateController");
                    return Flight::json(['success' => false, 'error' => 'Failed to update template'], 500);
                }
            }

            Logger::info("Template saved, retrieving data", "EmailTemplateController", [
                'final_template_id' => $finalTemplateId
            ]);

            // Получаем полные данные сохраненного шаблона
            $stmt = $this->db->prepare("SELECT * FROM petsbook_new.v_email_templates WHERE template_id = ?");
            $stmt->execute([$finalTemplateId]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$template) {
                Logger::error("Template saved but could not retrieve data", "EmailTemplateController", [
                    'final_template_id' => $finalTemplateId
                ]);
                return Flight::json(['success' => false, 'error' => 'Template saved but could not retrieve data'], 500);
            }

            Logger::info("Template operation completed", "EmailTemplateController", [
                'action' => $action,
                'template_id' => $finalTemplateId
            ]);

            return Flight::json([
                'success' => true,
                'message' => $action === 'created' ? 'New template created successfully' : 'Template updated successfully',
                'template_id' => $finalTemplateId,
                'action' => $action,
                'template' => $template
            ]);

        } catch (\Exception $e) {
            Logger::error("Error saving email template", "EmailTemplateController", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Flight::json([
                'success' => false,
                'error' => 'Save failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Translate email templates
     * 
     * @return void JSON response with translation result
     */
    public function translateTemplates()
    {
        // Проверяем токен
        $userData = $this->validateToken();
        if (!$userData) {
            return Flight::json(['success' => false, 'error' => 'No token provided'], 401);
        }

        Logger::info("Translating email templates", "EmailTemplateController", [
            'user_id' => $userData['user_id']
        ]);

        try {
            $availableLanguages = $this->getAvailableLanguages();
            Logger::info("Available languages", "EmailTemplateController", [
                'available_languages' => $availableLanguages
            ]);
            
            foreach ($availableLanguages as $language) {
                $locale = $language['code'];
                // TODO: Здесь будет логика перевода шаблонов
            }

            return Flight::json([
                'success' => true,
                'message' => 'Translation method called successfully'
            ]);

        } catch (\Exception $e) {
            Logger::error("Error in translateTemplates", "EmailTemplateController", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $userData['user_id']
            ]);

            return Flight::json([
                'success' => false,
                'error' => 'Translation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Translate email layouts
     * 
     * @return void JSON response with translation result
     */
    public function translateLayouts()
    {
        $userData = $this->validateToken();
        if (!$userData) {
            return Flight::json(['success' => false, 'error' => 'No token provided'], 401);
        }

        Logger::info("Translating email layouts", "EmailTemplateController", [
            'user_id' => $userData['user_id']
        ]);

        try {
            $availableLanguages = $this->getAvailableLanguages();
            $translatedCount = 0;
            $errors = [];

            // Получаем все английские макеты (базовые)
            $sqlEnglishLayouts = "
                SELECT * 
                FROM i18n_email_layouts   
                WHERE locale = 'en'
            ";
            $stmtEnglish = $this->db->prepare($sqlEnglishLayouts);
            $stmtEnglish->execute();
            $englishLayouts = $stmtEnglish->fetchAll(PDO::FETCH_ASSOC);

            foreach ($englishLayouts as $englishLayout) {
                $baseName = $englishLayout['name'];
                $shouldTranslateHeader = (int)($englishLayout['h_to_translate'] ?? 0) === 1;
                $shouldTranslateFooter = (int)($englishLayout['f_to_translate'] ?? 0) === 1;

                // Если оба флажка = 0 — ничего не делаем для этого макета
                if (!$shouldTranslateHeader && !$shouldTranslateFooter) {
                    continue;
                }

                foreach ($availableLanguages as $language) {
                    $locale = $language['code'];
                    if ($locale === 'en') continue;

                    // Проверяем, есть ли перевод для этого макета и языка
                    $sqlCheckExisting = "
                        SELECT * FROM i18n_email_layouts WHERE name = ? AND locale = ?
                    ";
                    $stmtCheck = $this->db->prepare($sqlCheckExisting);
                    $stmtCheck->execute([$baseName, $locale]);
                    $existingLayout = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                    // Готовим новые значения
                    $translatedHeaderHtml = $existingLayout['header_html'] ?? '';
                    $translatedFooterHtml = $existingLayout['footer_html'] ?? '';
                    $translatedSlogan = $existingLayout['slogan'] ?? '';

                    if ($shouldTranslateHeader && !empty($englishLayout['header_html'])) {
                        $headerTranslation = $this->translateService->translateHtml($englishLayout['header_html'], $locale);
                        if ($headerTranslation) {
                            $translatedHeaderHtml = $headerTranslation['text'];
                        }
                    }
                    if ($shouldTranslateFooter && !empty($englishLayout['footer_html'])) {
                        $footerTranslation = $this->translateService->translateHtml($englishLayout['footer_html'], $locale);
                        if ($footerTranslation) {
                            $translatedFooterHtml = $footerTranslation['text'];
                        }
                    }
                    if (!empty($englishLayout['slogan'])) {
                        $sloganTranslation = $this->translateService->translate($englishLayout['slogan'], $locale);
                        if ($sloganTranslation) {
                            $translatedSlogan = $sloganTranslation['text'];
                        }
                    }

                    if ($existingLayout) {
                        // UPDATE
                        $sqlUpdate = "
                            UPDATE i18n_email_layouts
                            SET header_html = ?, footer_html = ?, slogan = ?
                            WHERE name = ? AND locale = ?
                        ";
                        $stmtUpdate = $this->db->prepare($sqlUpdate);
                        $stmtUpdate->execute([
                            $translatedHeaderHtml,
                            $translatedFooterHtml,
                            $translatedSlogan,
                            $baseName,
                            $locale
                        ]);
                    } else {
                        // INSERT
                        $sqlInsert = "
                            INSERT INTO i18n_email_layouts (name, header_html, footer_html, slogan, h_to_translate, f_to_translate, locale)
                            VALUES (?, ?, ?, ?, 0, 0, ?)
                        ";
                        $stmtInsert = $this->db->prepare($sqlInsert);
                        $stmtInsert->execute([
                            $baseName,
                            $translatedHeaderHtml,
                            $translatedFooterHtml,
                            $translatedSlogan,
                            $locale
                        ]);
                    }
                }

                // После обработки всех языков — сбрасываем оба флажка в английском макете
                $sqlResetFlags = "
                    UPDATE i18n_email_layouts
                    SET h_to_translate = 0, f_to_translate = 0
                    WHERE name = ? AND locale = 'en'
                ";
                $stmtReset = $this->db->prepare($sqlResetFlags);
                $stmtReset->execute([$baseName]);
            }

            return Flight::json([
                'success' => true,
                'message' => "Layout translation completed. {$translatedCount} layouts processed.",
                'translated_count' => $translatedCount,
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            return Flight::json([
                'success' => false,
                'error' => 'Layout translation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    private function getAvailableLanguages() {
        $stmt = $this->db->prepare("
            SELECT * 
            FROM i18n_locales
            WHERE flag_icon IS NOT NULL AND flag_icon != ''
            AND already_translated = 1
            ORDER BY name
        ");

        $stmt->execute();
        $languages = $stmt->fetchAll(PDO::FETCH_ASSOC);        
        return $languages;
    }
} 