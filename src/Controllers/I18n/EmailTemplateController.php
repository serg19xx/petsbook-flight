<?php

namespace App\Controllers\I18n;

use App\Controllers\BaseController;
use App\Utils\Logger;
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

    public function __construct(PDO $db)
    {
        $this->db = $db;
        
        // Создаем директорию для изображений email шаблонов если она не существует
        $emailImagesDir = __DIR__ . '/../../public/profile-images/email-tmpl/';
        if (!is_dir($emailImagesDir)) {
            mkdir($emailImagesDir, 0755, true);
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
        Logger::info("Getting email templates", "EmailTemplateController");

        try {
            $stmt = $this->db->prepare("SELECT * FROM v_email_templates");
            $stmt->execute();
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Logger::info("Email templates retrieved successfully", "EmailTemplateController", [
                'count' => count($templates)
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
        $path = Flight::request()->url;
        $filename = basename($path);
        
        // Поддерживаем оба варианта URL
        $filePath = __DIR__ . '/../../public/profile-images/email-tmpl/' . $filename;
        
        if (!file_exists($filePath)) {
            return Flight::json(['error' => 'File not found'], 404);
        }
        
        $mimeType = mime_content_type($filePath);
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
    }
} 