<?php

namespace App\Mail\Providers;

use App\Mail\Contracts\MailProviderInterface;
use App\Mail\DTOs\PersonalizedRecipient;
use App\Utils\Logger;
use SendGrid\Mail\Mail;
use SendGrid\Mail\Attachment;
use SendGrid\Mail\TypeException;

class SendGridApiProvider implements MailProviderInterface
{
    private array $config;
    private \SendGrid $sendgrid;
    
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->sendgrid = new \SendGrid($config['api_key']);
    }
    
    public function send(
        string|array $to, 
        string $subject, 
        string $body, 
        array $attachments = [], 
        array $templateData = [],
        ?string $templateId = null
    ): bool {
        try {
            Logger::info("SendGrid: Starting email send", "SendGridApiProvider", [
                'to' => $to,
                'subject' => $subject,
                'templateId' => $templateId
            ]);
            
            $email = new Mail();
            $email->setFrom($this->config['from_address'], $this->config['from_name']);
            $email->setSubject($subject);
            
            // Обработка множественных получателей с персонализацией
            if (is_array($to)) {
                foreach ($to as $recipient) {
                    if ($recipient instanceof PersonalizedRecipient) {
                        $email->addTo($recipient->email, $recipient->name);
                        
                        // Добавляем персонализированные данные для шаблона
                        if (!empty($recipient->templateData)) {
                            $email->addDynamicTemplateDatas($recipient->templateData);
                        }
                    } else {
                        $email->addTo($recipient);
                    }
                }
            } else {
                $email->addTo($to);
            }
            
            if ($templateId !== null) {
                $email->setTemplateId($templateId);
                // Глобальные данные шаблона
                foreach ($templateData as $key => $value) {
                    $email->addDynamicTemplateDatas([$key => $value]);
                }
            } else {
                // SendGrid API всегда использует UTF-8 для контента
                $email->addContent("text/html", $body);
            }
            
            // Добавляем вложения
            foreach ($attachments as $attachment) {
                $attachmentObj = new Attachment();
                $attachmentObj->setContent(base64_encode(file_get_contents($attachment['path'])));
                $attachmentObj->setType($attachment['mime_type']);
                $attachmentObj->setFilename($attachment['name']);
                $attachmentObj->setDisposition("attachment");
                $email->addAttachment($attachmentObj);
            }
            
            Logger::info("SendGrid: Sending email", "SendGridApiProvider");
            $response = $this->sendgrid->send($email);
            
            $statusCode = $response->statusCode();
            $responseBody = $response->body();
            
            Logger::info("SendGrid: Response received", "SendGridApiProvider", [
                'statusCode' => $statusCode,
                'responseBody' => $responseBody
            ]);
            
            if ($statusCode >= 200 && $statusCode < 300) {
                Logger::info("SendGrid: Email sent successfully", "SendGridApiProvider");
                return true;
            } else {
                Logger::error("SendGrid: Failed to send email", "SendGridApiProvider", [
                    'statusCode' => $statusCode,
                    'responseBody' => $responseBody,
                    'to' => $to,
                    'subject' => $subject
                ]);
                return false;
            }
            
        } catch (TypeException $e) {
            Logger::error("SendGrid: TypeException occurred", "SendGridApiProvider", [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'to' => $to,
                'subject' => $subject
            ]);
            return false;
        } catch (\Exception $e) {
            Logger::error("SendGrid: Exception occurred", "SendGridApiProvider", [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'to' => $to,
                'subject' => $subject
            ]);
            return false;
        }
    }
    
    public function getConfig(): array
    {
        return $this->config;
    }
    
    public function supportsPersonalization(): bool
    {
        return true;
    }
}