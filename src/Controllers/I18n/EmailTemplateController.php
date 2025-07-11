<?php

namespace App\Controllers\I18n;

use App\Controllers\BaseController;
use App\Utils\Logger;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use \PDO;
use \Flight;
use App\Services\GoogleTranslateService;
use App\Controllers\I18n\Exception;

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
        $this->uploadDir = __DIR__ . '/../../../public/profile-images/email-tmpl/';
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
        $filePath = __DIR__ . '/../../../public/profile-images/email-tmpl/' . $filename;
        
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
                
                // Получаем id лейаута для нужного языка
                $sqlLayout = "SELECT id FROM i18n_email_layouts WHERE locale = ? AND id = ?";
                $stmtLayout = $this->db->prepare($sqlLayout);
                $stmtLayout->execute([$locale, $layoutId]);
                $layoutRow = $stmtLayout->fetch(PDO::FETCH_ASSOC);

                if (!$layoutRow) {
                    // Можно кинуть ошибку или пропустить
                    Logger::error("Layout not found for locale $locale and name $layoutId", "EmailTemplateController");
                    return Flight::json(['success' => false, 'error' => 'Layout not found'], 500);
                }

                $layoutId = $layoutRow['id'];
                
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
                
                // Получаем id лейаута для нужного языка
                $sqlLayout = "SELECT id FROM i18n_email_layouts WHERE locale = ? AND id = ?";
                $stmtLayout = $this->db->prepare($sqlLayout);
                $stmtLayout->execute([$locale, $layoutId]);
                $layoutRow = $stmtLayout->fetch(PDO::FETCH_ASSOC);

                if (!$layoutRow) {
                    // Можно кинуть ошибку или пропустить
                    Logger::error("Layout not found for locale $locale and name $layoutId", "EmailTemplateController");
                    return Flight::json(['success' => false, 'error' => 'Layout not found'], 500);
                }

                $layoutId = $layoutRow['id'];
                
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
        // Убрать эти строки если есть:
        // $this->checkAuth();
        // $this->requireAuth();
        
        // Оставить только логику перевода
        try {
            $availableLanguages = $this->getAvailableLanguages();
            $translatedCount = 0;
            $errors = [];

            // Получаем все английские шаблоны, которые нужно переводить
            $sqlEnglishTemplates = "
                SELECT * FROM i18n_email_templates
                WHERE locale = 'en' AND to_translate = 0
            ";
            $stmtEnglish = $this->db->prepare($sqlEnglishTemplates);
            $stmtEnglish->execute();
            $englishTemplates = $stmtEnglish->fetchAll(PDO::FETCH_ASSOC);

            Logger::info("Found " . count($englishTemplates) . " English templates to translate", "EmailTemplateController");

            // Найти все лейауты заранее (один запрос)
            $sqlAllLayouts = "SELECT id, locale FROM i18n_email_layouts";
            $stmtAllLayouts = $this->db->prepare($sqlAllLayouts);
            $stmtAllLayouts->execute();
            $layouts = [];
            while ($row = $stmtAllLayouts->fetch(PDO::FETCH_ASSOC)) {
                $layouts[$row['locale']] = $row['id'];
            }

            Logger::info("Found " . count($layouts) . " layouts", "EmailTemplateController");

            foreach ($englishTemplates as $englishTemplate) {
                foreach ($availableLanguages as $language) {
                    $targetLocale = $language['code'];
                    if ($targetLocale === 'en') continue;

                    try {
                        // Получаем правильный layout_id для этого языка
                        $correctLayoutId = $layouts[$targetLocale] ?? $englishTemplate['layout_id'];

                        // Проверяем, существует ли перевод для этого шаблона и языка
                        $sqlCheckExisting = "
                            SELECT * FROM i18n_email_templates
                            WHERE code = ? AND locale = ?
                        ";
                        $stmtCheck = $this->db->prepare($sqlCheckExisting);
                        $stmtCheck->execute([$englishTemplate['code'], $targetLocale]);
                        $existingTemplate = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                        // Защищаем переменные в body_html
                        $protectedBody = $this->protectVariables($englishTemplate['body_html']);
                        
                        // Переводим body_html
                        $translatedBody = $this->translateService->translateHtml($protectedBody, $targetLocale);

                        // Отладочная информация
                        Logger::info("Translation result", "EmailTemplateController", [
                            'template' => $englishTemplate['code'],
                            'locale' => $targetLocale,
                            'translatedBody_type' => gettype($translatedBody),
                            'translatedBody' => $translatedBody
                        ]);

                        // Исправленная логика обработки результата перевода
                        $translatedText = null;
                        if ($translatedBody) {
                            if (is_object($translatedBody) && isset($translatedBody->text)) {
                                $translatedText = $translatedBody->text;
                            } elseif (is_array($translatedBody) && isset($translatedBody['text'])) {
                                $translatedText = $translatedBody['text'];
                            } else {
                                $errors[] = "Translation failed for template {$englishTemplate['code']} to locale $targetLocale - invalid response format";
                                continue;
                            }
                            
                            if ($translatedText) {
                                $translatedBody = $this->restoreVariables($translatedText);
                            } else {
                                $errors[] = "Translation failed for template {$englishTemplate['code']} to locale $targetLocale - empty text";
                                continue;
                            }
                        } else {
                            $errors[] = "Translation failed for template {$englishTemplate['code']} to locale $targetLocale - no response";
                            continue;
                        }

                        // Переводим subject с защитой переменных
                        $translatedSubject = $englishTemplate['subject'];
                        if (!empty($englishTemplate['subject'])) {
                            $protectedSubject = $this->protectVariables($englishTemplate['subject']);
                            $subjectTranslation = $this->translateService->translate($protectedSubject, $targetLocale);
                            
                            if ($subjectTranslation) {
                                if (is_object($subjectTranslation) && isset($subjectTranslation->text)) {
                                    $translatedSubject = $this->restoreVariables($subjectTranslation->text);
                                } elseif (is_array($subjectTranslation) && isset($subjectTranslation['text'])) {
                                    $translatedSubject = $this->restoreVariables($subjectTranslation['text']);
                                }
                            }
                        }

                        if ($existingTemplate) {
                            // Обновляем существующий перевод
                            $sqlUpdate = "
                                UPDATE i18n_email_templates 
                                SET subject = ?, body_html = ?, layout_id = ?, updated_at = NOW()
                                WHERE code = ? AND locale = ?
                            ";
                            $stmtUpdate = $this->db->prepare($sqlUpdate);
                            $stmtUpdate->execute([
                                $translatedSubject,
                                $translatedBody,
                                $correctLayoutId,
                                $englishTemplate['code'],
                                $targetLocale
                            ]);
                        } else {
                            // Создаем новый перевод
                            $sqlInsert = "
                                INSERT INTO i18n_email_templates 
                                (code, locale, layout_id, subject, body_html, is_auto_translated, to_translate, test_data_json)
                                VALUES (?, ?, ?, ?, ?, 1, 0, ?)
                            ";
                            $stmtInsert = $this->db->prepare($sqlInsert);
                            $stmtInsert->execute([
                                $englishTemplate['code'],
                                $targetLocale,
                                $correctLayoutId,
                                $translatedSubject,
                                $translatedBody,
                                $englishTemplate['test_data_json']
                            ]);
                        }

                        $translatedCount++;

                    } catch (Exception $e) {
                        $errors[] = "Error processing template {$englishTemplate['code']} for locale $targetLocale: " . $e->getMessage();
                    }
                }

                // Сбрасываем флажок to_translate для английского шаблона
                $sqlResetFlag = "
                    UPDATE i18n_email_templates 
                    SET to_translate = 0 
                    WHERE id = ?
                ";
                $stmtReset = $this->db->prepare($sqlResetFlag);
                $stmtReset->execute([$englishTemplate['id']]);
            }

            return Flight::json([
                'success' => true,
                'message' => "Template translation completed. $translatedCount templates processed.",
                'translated_count' => $translatedCount,
                'errors' => $errors
            ]);

        } catch (Exception $e) {
            Logger::error("Template translation failed: " . $e->getMessage(), "EmailTemplateController");
            return Flight::json([
                'success' => false,
                'error' => "Template translation failed: " . $e->getMessage()
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
        // Убираем проверку токена
        // $userData = $this->validateToken();
        // if (!$userData) {
        //     return Flight::json(['success' => false, 'error' => 'No token provided'], 401);
        // }

        Logger::info("Translating email layouts", "EmailTemplateController", [
            // 'user_id' => $userData['user_id']  // убираем эту строку
        ]);

        try {
            $availableLanguages = $this->getAvailableLanguages();
            $translatedCount = 0;
            $errors = [];

            // Получаем все английские макеты с флажками перевода
            $sqlEnglishLayouts = "
                SELECT * FROM i18n_email_layouts
                WHERE locale = 'en' AND (h_to_translate = 1 OR f_to_translate = 1)
            ";
            $stmtEnglish = $this->db->prepare($sqlEnglishLayouts);
            $stmtEnglish->execute();
            $englishLayouts = $stmtEnglish->fetchAll(PDO::FETCH_ASSOC);

            Logger::info("Found " . count($englishLayouts) . " English layouts to translate", "EmailTemplateController");

            foreach ($englishLayouts as $englishLayout) {
                $layoutId = $englishLayout['id'];
                $layoutName = is_string($englishLayout['name']) ? $englishLayout['name'] : 'Layout name 1';
                
                if (!$layoutId) continue;

                foreach ($availableLanguages as $language) {
                    $locale = $language['code'];
                    if ($locale === 'en') continue;

                    try {
                        // Проверяем, существует ли перевод для этого макета и языка
                        $sqlCheckExisting = "
                            SELECT * FROM i18n_email_layouts
                            WHERE locale = ? AND name = ?
                        ";
                        $stmtCheck = $this->db->prepare($sqlCheckExisting);
                        
                        // Отладочная информация
                        Logger::info("Checking existing layout", "EmailTemplateController", [
                            'locale' => $locale,
                            'name' => $layoutName,
                            'name_type' => gettype($layoutName)
                        ]);
                        
                        $stmtCheck->execute([$locale, $layoutName]);
                        $existingLayout = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                        $translatedName = $layoutName;
                        $translatedHeader = is_string($englishLayout['header_html']) ? $englishLayout['header_html'] : '';
                        $translatedFooter = is_string($englishLayout['footer_html']) ? $englishLayout['footer_html'] : '';
                        $translatedSlogan = is_string($englishLayout['slogan']) ? $englishLayout['slogan'] : '';

                        // Переводим только те части, которые помечены для перевода
                        if ($englishLayout['h_to_translate'] && !empty($translatedHeader)) {
                            $protectedHeader = $this->protectVariables($translatedHeader);
                            $headerTranslation = $this->translateService->translateHtml($protectedHeader, $locale);
                            if ($headerTranslation && is_array($headerTranslation) && isset($headerTranslation[0]['text'])) {
                                $translatedHeader = $this->restoreVariables($headerTranslation[0]['text']);
                            }
                        }

                        if ($englishLayout['f_to_translate'] && !empty($translatedFooter)) {
                            $protectedFooter = $this->protectVariables($translatedFooter);
                            $footerTranslation = $this->translateService->translateHtml($protectedFooter, $locale);
                            if ($footerTranslation && is_array($footerTranslation) && isset($footerTranslation[0]['text'])) {
                                $translatedFooter = $this->restoreVariables($footerTranslation[0]['text']);
                            }
                        }

                        // Переводим слоган
                        if (!empty($translatedSlogan)) {
                            $sloganTranslation = $this->translateService->translate($translatedSlogan, $locale);
                            if ($sloganTranslation && is_array($sloganTranslation) && isset($sloganTranslation[0]['text'])) {
                                $translatedSlogan = $sloganTranslation[0]['text'];
                            }
                        }

                        if ($existingLayout) {
                            // Обновляем существующий перевод
                            $sqlUpdate = "
                                UPDATE i18n_email_layouts 
                                SET name = ?, header_html = ?, footer_html = ?, slogan = ?, h_to_translate = 0, f_to_translate = 0
                                WHERE locale = ? AND name = ?
                            ";
                            $stmtUpdate = $this->db->prepare($sqlUpdate);
                            
                            $updateParams = [
                                $translatedName,
                                $translatedHeader,
                                $translatedFooter,
                                $translatedSlogan,
                                $locale,
                                $layoutName
                            ];
                            
                            // Отладочная информация
                            Logger::info("Update params", "EmailTemplateController", [
                                'params' => $updateParams
                            ]);
                            
                            $stmtUpdate->execute($updateParams);
                        } else {
                            // Создаем новый перевод
                            $sqlInsert = "
                                INSERT INTO i18n_email_layouts 
                                (locale, name, header_html, footer_html, slogan, h_to_translate, f_to_translate)
                                VALUES (?, ?, ?, ?, ?, 0, 0)
                            ";
                            $stmtInsert = $this->db->prepare($sqlInsert);
                            
                            $insertParams = [
                                $locale,
                                $translatedName,
                                $translatedHeader,
                                $translatedFooter,
                                $translatedSlogan
                            ];
                            
                            // Отладочная информация
                            Logger::info("Insert params", "EmailTemplateController", [
                                'params' => $insertParams
                            ]);
                            
                            $stmtInsert->execute($insertParams);
                        }

                        $translatedCount++;

                    } catch (Exception $e) {
                        $errors[] = "Error processing layout $layoutId for locale $locale: " . $e->getMessage();
                    }
                }

                // Сбрасываем флажки для английского макета
                $sqlResetFlags = "
                    UPDATE i18n_email_layouts 
                    SET h_to_translate = 0, f_to_translate = 0
                    WHERE id = ?
                ";
                $stmtReset = $this->db->prepare($sqlResetFlags);
                $stmtReset->execute([$layoutId]);
            }

            return Flight::json([
                'success' => true,
                'message' => "Layout translation completed. $translatedCount layouts processed.",
                'translated_count' => $translatedCount,
                'errors' => $errors
            ]);

        } catch (Exception $e) {
            Logger::error("Layout translation failed: " . $e->getMessage(), "EmailTemplateController");
            return Flight::json([
                'success' => false,
                'error' => "Layout translation failed: " . $e->getMessage()
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

    /**
     * Защищает переменные в HTML от перевода
     */
    private function protectVariables($html)
    {
        // Заменяем {{ variable }} на специальные маркеры
        $html = preg_replace('/\{\{\s*([^}]+)\s*\}\}/', '___VAR___$1___VAR___', $html);
        return $html;
    }

    /**
     * Восстанавливает переменные после перевода
     */
    private function restoreVariables($html)
    {
        // Восстанавливаем {{ variable }} из маркеров
        $html = preg_replace('/___VAR___([^_]+)___VAR___/', '{{ $1 }}', $html);
        return $html;
    }
} 