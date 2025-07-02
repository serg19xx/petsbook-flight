<?php

namespace App\Mail\Providers;

use App\Mail\Contracts\MailProviderInterface;
use App\Mail\DTOs\PersonalizedRecipient;
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
            
            $response = $this->sendgrid->send($email);
            return $response->statusCode() >= 200 && $response->statusCode() < 300;
            
        } catch (TypeException $e) {
            error_log("SendGrid API Error: " . $e->getMessage());
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