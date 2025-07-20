<?php

namespace App\Controllers;
require __DIR__ . '/../../vendor/autoload.php';

use App\Utils\Logger;
use PDO;
use Flight;
use App\Mail\MailService;
use App\Services\EmailTemplateRenderer;
use App\Exceptions\EmailException;
use App\Exceptions\ValidationException;
use App\Exceptions\DatabaseException;
use App\Exceptions\TokenException;

/**
 * Auth Unverified Email Controller
 * 
 * Handles operations for unverified email addresses including resending,
 * deleting, and updating verification emails.
 */
class AuthUnverifiedEmailController extends BaseController {
    /** @var PDO Database connection instance */
    private $db;
    private $request;
    private $renderer;

    /**
     * Constructor initializes database connection
     * 
     * @throws \Exception When database connection fails
     */
    public function __construct($db) {
        $this->request = Flight::request();
        $this->db = $db;
        $this->renderer = new EmailTemplateRenderer();

        Logger::info("AuthUnverifiedEmailController initialized", "AuthUnverifiedEmailController");
    }

    /**
     * Resends verification email for unverified email address
     * 
     * @return void JSON response with status
     * 
     * @api {post} /api/auth/resend-unverified-email Resend verification email
     * @apiBody {String} email User's email address
     * 
     * @apiSuccess {String} status Success status
     * @apiSuccess {String} error_code Response code
     */
    public function resendUnverifiedEmail() {
        try {
            $requestBody = Flight::request()->getBody();
            $data = json_decode($requestBody, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new ValidationException("Invalid JSON format");
            }
            
            $email = $data['email'] ?? null;

            if (empty($email)) {
                throw new ValidationException("Email is required");
            }

            Logger::info("Resend verification email request", "AuthUnverifiedEmailController", [
                'email' => $email
            ]);

            // Check if user exists and email is not verified
            $stmt = $this->db->prepare("
                SELECT id, email, email_verified 
                FROM logins 
                WHERE email = ? 
                AND is_active = 1
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                return Flight::json([
                    'status' => 400,
                    'error_code' => 'USER_NOT_FOUND',
                    'data' => null
                ], 400);
            }

            if ($user['email_verified']) {
                return Flight::json([
                    'status' => 400,
                    'error_code' => 'EMAIL_ALREADY_VERIFIED',
                    'data' => null
                ], 400);
            }

            // Get user name
            $name = $this->getUserName($user['id']);

            // Create new verification token
            $token = $this->createVerificationToken($user['id']);

            // Send verification email
            $this->sendVerificationEmail($email, $name, $token);

            Logger::info("Verification email resent successfully", "AuthUnverifiedEmailController", [
                'email' => $email,
                'userId' => $user['id']
            ]);

            return Flight::json([
                'status' => 200,
                'error_code' => 'VERIFICATION_EMAIL_SENT',
                'data' => null
            ], 200);

        } catch (ValidationException $e) {
            Logger::warning("Validation error in resend verification email", "AuthUnverifiedEmailController", [
                'error' => $e->getMessage()
            ]);
            return Flight::json([
                'status' => 400,
                'error_code' => 'INVALID_REQUEST',
                'data' => null
            ], 400);
        } catch (EmailException $e) {
            Logger::error("Email sending error in resend verification email", "AuthUnverifiedEmailController", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Flight::json([
                'status' => 500,
                'error_code' => 'EMAIL_SEND_ERROR',
                'data' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        } catch (DatabaseException $e) {
            Logger::error("Database error in resend verification email", "AuthUnverifiedEmailController", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Flight::json([
                'status' => 500,
                'error_code' => 'DATABASE_ERROR',
                'data' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        } catch (\Exception $e) {
            Logger::error("System error in resend verification email", "AuthUnverifiedEmailController", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Flight::json([
                'status' => 500,
                'error_code' => 'SYSTEM_ERROR',
                'data' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        }
    }

    /**
     * Deletes unverified email account
     * 
     * @return void JSON response with status
     * 
     * @api {delete} /api/auth/delete-unverified-email Delete unverified email account
     * @apiBody {String} email User's email address
     * 
     * @apiSuccess {String} status Success status
     * @apiSuccess {String} error_code Response code
     */
    public function deleteUnverifiedEmail() {
        try {
            $requestBody = Flight::request()->getBody();
            $data = json_decode($requestBody, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new ValidationException("Invalid JSON format");
            }
            
            $email = $data['email'] ?? null;

            if (empty($email)) {
                throw new ValidationException("Email is required");
            }

            Logger::info("Delete unverified email request", "AuthUnverifiedEmailController", [
                'email' => $email
            ]);

            // Check if user exists and email is not verified
            $stmt = $this->db->prepare("
                SELECT id, email, email_verified 
                FROM logins 
                WHERE email = ? 
                AND is_active = 1
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                return Flight::json([
                    'status' => 400,
                    'error_code' => 'USER_NOT_FOUND',
                    'data' => null
                ], 400);
            }

            if ($user['email_verified']) {
                return Flight::json([
                    'status' => 400,
                    'error_code' => 'EMAIL_ALREADY_VERIFIED',
                    'data' => null
                ], 400);
            }

            // Begin transaction
            $this->db->beginTransaction();

            try {
                // Delete verification tokens
                $stmt = $this->db->prepare("
                    DELETE FROM email_verification_tokens 
                    WHERE user_id = ?
                ");
                $stmt->execute([$user['id']]);

                // Delete password reset tokens
                $stmt = $this->db->prepare("
                    DELETE FROM password_reset_tokens 
                    WHERE login_id = ?
                ");
                $stmt->execute([$user['id']]);

                // Delete user profile if exists
                $stmt = $this->db->prepare("
                    DELETE FROM user_profiles 
                    WHERE login_id = ?
                ");
                $stmt->execute([$user['id']]);

                // Delete login record
                $stmt = $this->db->prepare("
                    DELETE FROM logins 
                    WHERE id = ?
                ");
                $stmt->execute([$user['id']]);

                $this->db->commit();

                Logger::info("Unverified email account deleted successfully", "AuthUnverifiedEmailController", [
                    'email' => $email,
                    'userId' => $user['id']
                ]);

                return Flight::json([
                    'status' => 200,
                    'error_code' => 'ACCOUNT_DELETED',
                    'data' => null
                ], 200);

            } catch (\Exception $e) {
                $this->db->rollBack();
                throw $e;
            }

        } catch (ValidationException $e) {
            Logger::warning("Validation error in delete unverified email", "AuthUnverifiedEmailController", [
                'error' => $e->getMessage()
            ]);
            return Flight::json([
                'status' => 400,
                'error_code' => 'INVALID_REQUEST',
                'data' => null
            ], 400);
        } catch (DatabaseException $e) {
            Logger::error("Database error in delete unverified email", "AuthUnverifiedEmailController", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Flight::json([
                'status' => 500,
                'error_code' => 'DATABASE_ERROR',
                'data' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        } catch (\Exception $e) {
            Logger::error("System error in delete unverified email", "AuthUnverifiedEmailController", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Flight::json([
                'status' => 500,
                'error_code' => 'SYSTEM_ERROR',
                'data' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        }
    }

    /**
     * Updates unverified email address
     * 
     * @return void JSON response with status
     * 
     * @api {patch} /api/auth/update-unverified-email Update unverified email address
     * @apiBody {String} oldEmail Current email address
     * @apiBody {String} newEmail New email address
     * 
     * @apiSuccess {String} status Success status
     * @apiSuccess {String} error_code Response code
     */
    public function updateUnverifiedEmail() {
        try {
            Logger::info("=== UPDATE UNVERIFIED EMAIL START ===", "AuthUnverifiedEmailController");
            
            $requestBody = Flight::request()->getBody();
            $data = json_decode($requestBody, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new ValidationException("Invalid JSON format");
            }
            
            $oldEmail = $data['oldEmail'] ?? null;
            $newEmail = $data['newEmail'] ?? null;

            if (empty($oldEmail) || empty($newEmail)) {
                throw new ValidationException("Both oldEmail and newEmail are required");
            }

            if ($oldEmail === $newEmail) {
                return Flight::json([
                    'status' => 400,
                    'error_code' => 'EMAILS_ARE_IDENTICAL',
                    'data' => null
                ], 400);
            }

            Logger::info("Update unverified email request", "AuthUnverifiedEmailController", [
                'oldEmail' => $oldEmail,
                'newEmail' => $newEmail
            ]);

            // Check if old email exists and is not verified
            $stmt = $this->db->prepare("
                SELECT id, email, email_verified 
                FROM logins 
                WHERE email = ? 
                AND is_active = 1
            ");
            $stmt->execute([$oldEmail]);
            $user = $stmt->fetch();

            if (!$user) {
                return Flight::json([
                    'status' => 400,
                    'error_code' => 'USER_NOT_FOUND',
                    'data' => null
                ], 400);
            }

            if ($user['email_verified']) {
                return Flight::json([
                    'status' => 400,
                    'error_code' => 'EMAIL_ALREADY_VERIFIED',
                    'data' => null
                ], 400);
            }

            // Вызываем хранимую процедуру
            $stmt = $this->db->prepare("CALL sp_UpdateUnverifiedEmail(?, ?)");
            $stmt->execute([$oldEmail, $newEmail]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            if (!$result['success']) {
                return Flight::json([
                    'status' => 400,
                    'error_code' => 'UPDATE_FAILED',
                    'data' => null
                ], 400);
            }

            // Get user name
            $name = $this->getUserName($user['id']);

            // Create new verification token
            $token = $this->createVerificationToken($user['id']);

            // Send verification email to new address
            $this->sendVerificationEmail($newEmail, $name, $token);

            Logger::info("Unverified email updated successfully", "AuthUnverifiedEmailController", [
                'oldEmail' => $oldEmail,
                'newEmail' => $newEmail,
                'userId' => $user['id']
            ]);

            return Flight::json([
                'status' => 200,
                'error_code' => 'EMAIL_UPDATED',
                'data' => null
            ], 200);

        } catch (ValidationException $e) {
            Logger::warning("Validation error in update unverified email", "AuthUnverifiedEmailController", [
                'error' => $e->getMessage()
            ]);
            return Flight::json([
                'status' => 400,
                'error_code' => 'INVALID_REQUEST',
                'data' => null
            ], 400);
        } catch (EmailException $e) {
            Logger::error("Email sending error in update unverified email", "AuthUnverifiedEmailController", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Flight::json([
                'status' => 500,
                'error_code' => 'EMAIL_SEND_ERROR',
                'data' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        } catch (DatabaseException $e) {
            Logger::error("Database error in update unverified email", "AuthUnverifiedEmailController", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Flight::json([
                'status' => 500,
                'error_code' => 'DATABASE_ERROR',
                'data' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        } catch (\Exception $e) {
            Logger::error("System error in update unverified email", "AuthUnverifiedEmailController", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Flight::json([
                'status' => 500,
                'error_code' => 'SYSTEM_ERROR',
                'data' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        }
    }

    /**
     * Get user name by user ID
     * 
     * @param int $userId User ID
     * @return string User name or email
     */
    private function getUserName($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT full_name 
                FROM user_profiles 
                WHERE login_id = ?
            ");
            $stmt->execute([$userId]);
            $profile = $stmt->fetch();
            
            return $profile['full_name'] ?? null;
        } catch (\Exception $e) {
            Logger::error("Error getting user name", "AuthUnverifiedEmailController", [
                'error' => $e->getMessage(),
                'userId' => $userId
            ]);
            return null;
        }
    }

    /**
     * Clean old verification tokens for user
     * 
     * @param int $userId User ID
     */
    private function cleanOldVerificationTokens($userId) {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM email_verification_tokens 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            
            Logger::info("Old verification tokens cleaned", "AuthUnverifiedEmailController", [
                'userId' => $userId
            ]);
        } catch (\Exception $e) {
            Logger::error("Error cleaning old verification tokens", "AuthUnverifiedEmailController", [
                'error' => $e->getMessage(),
                'userId' => $userId
            ]);
            throw $e;
        }
    }

    /**
     * Create verification token for user
     * 
     * @param int $userId User ID
     * @return string Generated token
     */
    private function createVerificationToken($userId) {
        // Сначала очищаем старые токены
        $this->cleanOldVerificationTokens($userId);
        
        // Создаем новый токен
        $token = bin2hex(random_bytes(32));
        $stmt = $this->db->prepare("
            INSERT INTO email_verification_tokens (user_id, token, expires_at)
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))
        ");
        $stmt->execute([$userId, $token]);
        $stmt->closeCursor();
        
        return $token;
    }

    /**
     * Send verification email
     * 
     * @param string $email Email address
     * @param string $name User name
     * @param string $token Verification token
     */
    private function sendVerificationEmail($email, $name, $token) {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? 'http://localhost:5173';
        $verifyUrl = $origin . '/verify-email/' . $token;

        // Get email template
        $stmt = $this->db->prepare("
            SELECT * FROM v_email_templates WHERE code='auth.registration.welcome' AND locale='en'
        ");
        $stmt->execute();
        $template = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$template) {
            throw new EmailException("Email template not found");
        }

        $tmplSubj = $template['subject'];
        $tmplBody = $template['header_html'] . $template['body_html'] . $template['footer_html'];
        $utf8Name = mb_convert_encoding($name ?? $email, 'UTF-8', 'auto');

        // Render email
        $rendered = $this->renderer->render(
            $tmplBody,
            $tmplSubj,
            [
                'clientName' => $utf8Name,
                'verifyUrl' => $verifyUrl,
                'Sender_Phone' => $_ENV['MAIL_SENDER_PHONE'],
                'Sender_Email' => $_ENV['MAIL_SENDER_EMAIL'],
                'now' => date('Y')
            ]
        );

        $mailService = new MailService();
        $mailService->sendMail(
            $email,
            $rendered['subject'],
            $rendered['body']
        );
    }
} 