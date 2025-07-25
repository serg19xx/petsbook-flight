<?php

namespace App\Mail\Providers;

use App\Utils\Logger;
use App\Mail\Contracts\MailProviderInterface;
use App\Mail\DTOs\PersonalizedRecipient;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class GmailSmtpProvider implements MailProviderInterface
{
    private array $config;
    private PHPMailer $mailer;
    
    public function __construct(array $config)
    {
        Logger::info("Initializing GmailSmtpProvider", "GmailSmtpProvider", [
            'host' => $config['host'] ?? 'smtp.gmail.com',
            'port' => $config['port'] ?? '587'
        ]);

        // Проверяем все необходимые параметры
        $requiredConfig = [
            'host' => 'smtp.gmail.com',
            'port' => '587',
            'username' => null,
            'password' => null
        ];
        
        $missingConfig = [];
        foreach ($requiredConfig as $key => $expectedValue) {
            if (!isset($config[$key]) || empty($config[$key])) {
                $missingConfig[] = $key;
            } elseif ($expectedValue !== null && $config[$key] !== $expectedValue) {
                Logger::warning("Incorrect Gmail {$key}", "GmailSmtpProvider", [
                    'current' => $config[$key],
                    'expected' => $expectedValue
                ]);
            }
        }
        
        if (!empty($missingConfig)) {
            $error = "Missing required Gmail configuration: " . implode(', ', $missingConfig);
            Logger::error($error, "GmailSmtpProvider");
            throw new \Exception($error);
        }     

        try {
            if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                Logger::error("PHPMailer class not found", "GmailSmtpProvider");
                throw new \Exception("PHPMailer class not found");
            }

            Logger::info("PHPMailer class found", "GmailSmtpProvider");

            $this->config = $config;
            $this->mailer = new PHPMailer(true);
            $this->mailer->CharSet = 'UTF-8';
            $this->mailer->Encoding = 'base64';

            Logger::info("PHPMailer instance created", "GmailSmtpProvider");
            
            // Настройка SMTP для Gmail
            $this->mailer->isSMTP();
            $this->mailer->Host = $config['host'];
            $this->mailer->Port = $config['port'];
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $config['username'];
            $this->mailer->Password = $config['password'];
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

            // Явно указываем кодировку UTF-8 для корректной работы с кириллицей
            $this->mailer->CharSet = 'UTF-8';
            $this->mailer->Encoding = 'base64';

            Logger::info("SMTP settings configured", "GmailSmtpProvider", [
                'host' => $this->mailer->Host,
                'port' => $this->mailer->Port,
                'username' => $this->mailer->Username ? 'set' : 'not set',
                'password' => $this->mailer->Password ? 'set' : 'not set',
                'encryption' => $this->mailer->SMTPSecure,
                'charset' => $this->mailer->CharSet,
                'encoding' => $this->mailer->Encoding
            ]);        

            // Включаем отладку
            $this->mailer->SMTPDebug = 2;
            $this->mailer->Debugoutput = function($str, $level) {
                Logger::debug("SMTP Debug: " . $str, "GmailSmtpProvider");
            };

            Logger::info("GmailSmtpProvider initialization completed", "GmailSmtpProvider");
        } catch (PHPMailerException $e) {
            Logger::error("PHPMailer error", "GmailSmtpProvider", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;    
        } catch (\Exception $e) {
            Logger::error("General error", "GmailSmtpProvider", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
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
            Logger::info("Starting Gmail send process", "GmailSmtpProvider", [
                'to' => $to,
                'subject' => $subject,
                'hasAttachments' => !empty($attachments),
                'templateId' => $templateId
            ]);

            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();

            // Проверка и настройка отправителя
            $fromAddress = $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@petsbook.com';
            $fromName = $_ENV['MAIL_FROM_NAME'] ?? 'PetsBook';
            
            $this->mailer->setFrom($fromAddress, $fromName);
            Logger::info("Set sender", "GmailSmtpProvider", [
                'from' => $fromAddress,
                'name' => $fromName
            ]);              

            // Обработка получателей
            if (is_array($to)) {
                foreach ($to as $recipient) {
                    if ($recipient instanceof PersonalizedRecipient) {
                        $this->mailer->addAddress($recipient->email, $recipient->name);
                        Logger::info("Added personalized recipient", "GmailSmtpProvider", [
                            'email' => $recipient->email,
                            'name' => $recipient->name
                        ]);
                    } else {
                        $this->mailer->addAddress($recipient);
                        Logger::info("Added recipient", "GmailSmtpProvider", [
                            'email' => $recipient
                        ]);
                    }
                }
            } else {
                $this->mailer->addAddress($to);
                Logger::info("Added single recipient", "GmailSmtpProvider", [
                    'email' => $to
                ]);                
            }

            Logger::info("Set recipients", "GmailSmtpProvider", ['to' => $to]);
            
            // Принудительно конвертируем тему и тело письма в UTF-8
            $subject = mb_convert_encoding($subject, 'UTF-8', 'auto');
            $body = mb_convert_encoding($body, 'UTF-8', 'auto');

            $this->mailer->Subject = $subject;
            $this->mailer->isHTML(true);
            $this->mailer->Body = $body;
            $this->mailer->CharSet = 'UTF-8'; // гарантируем кодировку для каждого письма
            $this->mailer->Encoding = 'base64';
            
            // Добавление вложений
            if (!empty($attachments)) {
                Logger::info("Processing attachments", "GmailSmtpProvider", [
                    'count' => count($attachments)
                ]);
                
                foreach ($attachments as $attachment) {
                    if (!isset($attachment['path']) || !file_exists($attachment['path'])) {
                        Logger::warning("Invalid attachment", "GmailSmtpProvider", [
                            'attachment' => $attachment
                        ]);
                        continue;
                    }
                    
                    try {
                        $this->mailer->addAttachment(
                            $attachment['path'],
                            $attachment['name'] ?? basename($attachment['path'])
                        );
                        Logger::info("Added attachment", "GmailSmtpProvider", [
                            'path' => $attachment['path'],
                            'name' => $attachment['name'] ?? basename($attachment['path'])
                        ]);
                    } catch (Exception $e) {
                        Logger::error("Failed to add attachment", "GmailSmtpProvider", [
                            'error' => $e->getMessage(),
                            'attachment' => $attachment
                        ]);
                    }
                }
            }
            
            Logger::info("Attempting to send email", "GmailSmtpProvider");
            $result = $this->mailer->send();
            if ($result) {
                Logger::info("Email sent successfully", "GmailSmtpProvider");
            } else {
                Logger::warning("Email send returned false", "GmailSmtpProvider");
            }
            
            return $result;
            
        } catch (Exception $e) {
            Logger::error("Failed to send email", "GmailSmtpProvider", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
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