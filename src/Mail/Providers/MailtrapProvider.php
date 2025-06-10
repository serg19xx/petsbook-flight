<?php

namespace App\Mail\Providers;


use App\Utils\Logger;
use App\Mail\Contracts\MailProviderInterface;
use App\Mail\DTOs\PersonalizedRecipient;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;



class MailtrapProvider implements MailProviderInterface
{
    private array $config;
    private PHPMailer $mailer;
    
    public function __construct(array $config)
    {
        Logger::info("Initializing MailtrapProvider", "MailtrapProvider", [
            'host' => $config['host'],
            'port' => $config['port']
        ]);

        // Проверяем все необходимые параметры
        $requiredConfig = [
            'host' => 'sandbox.smtp.mailtrap.io',
            'port' => '2525',
            'username' => null,
            'password' => null
        ];
        
        $missingConfig = [];
        foreach ($requiredConfig as $key => $expectedValue) {
            if (!isset($config[$key]) || empty($config[$key])) {
                $missingConfig[] = $key;
            } elseif ($expectedValue !== null && $config[$key] !== $expectedValue) {
                Logger::warning("Incorrect Mailtrap {$key}", "MailtrapProvider", [
                    'current' => $config[$key],
                    'expected' => $expectedValue
                ]);
            }
        }
        
        if (!empty($missingConfig)) {
            $error = "Missing required Mailtrap configuration: " . implode(', ', $missingConfig);
            Logger::error($error, "MailtrapProvider");
            throw new \Exception($error);
        }     


        try{
            if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                Logger::error("PHPMailer class not found", "MailtrapProvider");
                throw new \Exception("PHPMailer class not found");
            }

            Logger::info("PHPMailer class found", "MailtrapProvider");

            $this->config = $config;
            $this->mailer = new PHPMailer(true);

            Logger::info("PHPMailer instance created", "MailtrapProvider");
            
            // Настройка SMTP
            $this->mailer->isSMTP();
            $this->mailer->Host = $config['host'];
            $this->mailer->Port = $config['port'];
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $config['username'];
            $this->mailer->Password = $config['password'];
            $this->mailer->SMTPSecure = $config['encryption'];

            Logger::info("SMTP settings configured", "MailtrapProvider", [
                'host' => $this->mailer->Host,
                'port' => $this->mailer->Port,
                'username' => $this->mailer->Username ? 'set' : 'not set',
                'password' => $this->mailer->Password ? 'set' : 'not set',
                'encryption' => $this->mailer->SMTPSecure
            ]);        

            // Включаем отладку
            $this->mailer->SMTPDebug = 2;
            $this->mailer->Debugoutput = function($str, $level) {
                Logger::debug("SMTP Debug: " . $str, "MailtrapProvider");
            };

            Logger::info("MailtrapProvider initialization completed", "MailtrapProvider");
        }catch(PHPMailerException $e){
            Logger::error("PHPMailer error", "MailtrapProvider", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;    
        }catch(\Exception $e){
            Logger::error("General error", "MailtrapProvider", [
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


            Logger::info("Starting Mailtrap send process", "MailtrapProvider", [
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
            Logger::info("Set sender", "MailtrapProvider", [
                'from' => $fromAddress,
                'name' => $fromName
            ]);              

            // Обработка получателей
            if (is_array($to)) {
                foreach ($to as $recipient) {
                    if ($recipient instanceof PersonalizedRecipient) {
                        $this->mailer->addAddress($recipient->email, $recipient->name);
                        Logger::info("Added personalized recipient", "MailtrapProvider", [
                            'email' => $recipient->email,
                            'name' => $recipient->name
                        ]);
                    } else {
                        $this->mailer->addAddress($recipient);
                        Logger::info("Added recipient", "MailtrapProvider", [
                            'email' => $recipient
                        ]);
                    }
                }
            } else {
                $this->mailer->addAddress($to);
                Logger::info("Added single recipient", "MailtrapProvider", [
                    'email' => $to
                ]);                
            }

            Logger::info("Set recipients", "MailtrapProvider", ['to' => $to]);
            
            $this->mailer->Subject = $subject;
            $this->mailer->isHTML(true);
            $this->mailer->Body = $body;
            
            // Добавление вложений
            if (!empty($attachments)) {
                Logger::info("Processing attachments", "MailtrapProvider", [
                    'count' => count($attachments)
                ]);
                
                foreach ($attachments as $attachment) {
                    if (!isset($attachment['path']) || !file_exists($attachment['path'])) {
                        Logger::warning("Invalid attachment", "MailtrapProvider", [
                            'attachment' => $attachment
                        ]);
                        continue;
                    }
                    
                    try {
                        $this->mailer->addAttachment(
                            $attachment['path'],
                            $attachment['name'] ?? basename($attachment['path'])
                        );
                        Logger::info("Added attachment", "MailtrapProvider", [
                            'path' => $attachment['path'],
                            'name' => $attachment['name'] ?? basename($attachment['path'])
                        ]);
                    } catch (Exception $e) {
                        Logger::error("Failed to add attachment", "MailtrapProvider", [
                            'error' => $e->getMessage(),
                            'attachment' => $attachment
                        ]);
                    }
                }
            }
            
            Logger::info("Attempting to send email", "MailtrapProvider");
            $result = $this->mailer->send();
            if ($result) {
                Logger::info("Email sent successfully", "MailtrapProvider");
            } else {
                Logger::warning("Email send returned false", "MailtrapProvider");
            }
            
            return $result;
            
        } catch (Exception $e) {
            Logger::error("Failed to send email", "MailtrapProvider", [
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