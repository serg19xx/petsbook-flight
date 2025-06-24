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
} 