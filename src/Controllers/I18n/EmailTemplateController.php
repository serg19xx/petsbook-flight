<?php

namespace App\Controllers\I18n;

use App\Controllers\BaseController;
use App\Utils\Logger;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use \PDO;
use \Flight;

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

    public function __construct(PDO $db)
    {
        $this->db = $db;
        
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
        $userData = $this->validateToken();
        if (!$userData) {
            return Flight::json(['success' => false, 'error' => 'No token provided'], 401);
        }

        Logger::info("Getting email templates", "EmailTemplateController", [
            'user_id' => $userData['user_id']
        ]);

        try {
            $stmt = $this->db->prepare("SELECT * FROM petsbook_new.v_email_templates");
            $stmt->execute();
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Logger::info("Email templates retrieved successfully", "EmailTemplateController", [
                'count' => count($templates),
                'user_id' => $userData['user_id']
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
                'trace' => $e->getTraceAsString(),
                'user_id' => $userData['user_id']
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
        $userData = $this->validateToken();
        if (!$userData) {
            return Flight::json(['error' => 'Unauthorized'], 401);
        }

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
            'is_readable' => is_readable($filePath),
            'user_id' => $userData['user_id']
        ]);
        
        if (!file_exists($filePath)) {
            Logger::error("Email template image not found", "EmailTemplateController", [
                'request_url' => $path,
                'filename' => $filename,
                'full_path' => $filePath,
                'user_id' => $userData['user_id']
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
     * 
     * @return void JSON response with save result
     */
    public function saveTemplate()
    {
        // Проверяем токен
        $userData = $this->validateToken();
        if (!$userData) {
            return Flight::json(['success' => false, 'error' => 'No token provided'], 401);
        }

        Logger::info("Saving email template", "EmailTemplateController", [
            'user_id' => $userData['user_id']
        ]);

        try {
            $data = Flight::request()->data;
            
            // Валидация входных данных
            if (empty($data->code)) {
                return Flight::json(['success' => false, 'error' => 'Code is required'], 400);
            }

            if (empty($data->subject)) {
                return Flight::json(['success' => false, 'error' => 'Subject is required'], 400);
            }

            if (empty($data->body_html)) {
                return Flight::json(['success' => false, 'error' => 'Body HTML is required'], 400);
            }

            $templateId = (int)($data->template_id ?? 0);
            $action = '';
            $finalTemplateId = 0;
            
            if ($templateId === 0) {
                // Создаем новый шаблон (INSERT)
                $stmt = $this->db->prepare("
                    INSERT INTO i18n_email_templates (code, subject, body_html, locale, layout_id) 
                    VALUES (?, ?, ?, 'en', 1)
                ");

                $result = $stmt->execute([
                    $data->code,
                    $data->subject,
                    $data->body_html
                ]);

                if ($result) {
                    $finalTemplateId = $this->db->lastInsertId();
                    $action = 'created';
                    
                    Logger::info("New email template created", "EmailTemplateController", [
                        'code' => $data->code,
                        'template_id' => $finalTemplateId,
                        'user_id' => $userData['user_id']
                    ]);
                } else {
                    return Flight::json(['success' => false, 'error' => 'Failed to create template'], 500);
                }
            } else {
                // Обновляем существующий шаблон (UPDATE)
                $stmt = $this->db->prepare("
                    UPDATE i18n_email_templates 
                    SET code = ?, subject = ?, body_html = ?
                    WHERE id = ?
                ");

                $result = $stmt->execute([
                    $data->code,
                    $data->subject,
                    $data->body_html,
                    $templateId
                ]);

                if ($result && $stmt->rowCount() > 0) {
                    $finalTemplateId = $templateId;
                    $action = 'updated';
                    
                    Logger::info("Email template updated", "EmailTemplateController", [
                        'code' => $data->code,
                        'template_id' => $finalTemplateId,
                        'user_id' => $userData['user_id']
                    ]);
                } else {
                    return Flight::json(['success' => false, 'error' => 'Template not found or no changes made'], 404);
                }
            }

            // Получаем полные данные сохраненного шаблона
            $stmt = $this->db->prepare("SELECT * FROM petsbook_new.v_email_templates WHERE template_id = ?");
            $stmt->execute([$finalTemplateId]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$template) {
                return Flight::json(['success' => false, 'error' => 'Template saved but could not retrieve data'], 500);
            }

            return Flight::json([
                'success' => true,
                'message' => $action === 'created' ? 'New template created/updated successfully' : 'Template updated successfully',
                'template_id' => $finalTemplateId,
                'action' => $action,
                'template' => $template
            ]);

        } catch (\Exception $e) {
            Logger::error("Error saving email template", "EmailTemplateController", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $userData['user_id']
            ]);

            return Flight::json([
                'success' => false,
                'error' => 'Save failed: ' . $e->getMessage()
            ], 500);
        }
    }
} 