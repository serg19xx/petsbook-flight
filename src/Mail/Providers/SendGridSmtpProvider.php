<?php

namespace App\Mail\Providers;

use App\Mail\Contracts\MailProviderInterface;
use App\Mail\DTOs\PersonalizedRecipient;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class SendGridSmtpProvider implements MailProviderInterface
{
    private PHPMailer $mailer;
    private string $smtpHost = 'smtp.sendgrid.net';
    private int $smtpPort = 587;
    private string $smtpUsername = 'apikey';
    private string $smtpPassword;
    private string $fromEmail;
    private string $fromName;

    public function __construct(array $config)
    {
        try {
            \App\Utils\Logger::info('SendGridSmtpProvider constructor called', 'SendGridSmtpProvider', ['config' => $config]);
            
            $this->smtpPassword = $config['smtp_password'] ?? '';
            $this->fromEmail = $config['from_address'] ?? 'noreply@petsbook.ca';
            $this->fromName = $config['from_name'] ?? 'PetsBook';
            
            \App\Utils\Logger::info('SendGridSmtpProvider config set', 'SendGridSmtpProvider');
            
            $this->initializeMailer();
            
            \App\Utils\Logger::info('SendGridSmtpProvider initialized', 'SendGridSmtpProvider');
        } catch (\Exception $e) {
            \App\Utils\Logger::error('SendGridSmtpProvider constructor failed', 'SendGridSmtpProvider', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function initializeMailer(): void
    {
        $this->mailer = new PHPMailer(true);
        
        // Server settings
        $this->mailer->isSMTP();
        $this->mailer->Host = $this->smtpHost;
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = $this->smtpUsername;
        $this->mailer->Password = $this->smtpPassword;
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Port = $this->smtpPort;
        
        // Default settings
        $this->mailer->setFrom($this->fromEmail, $this->fromName);
        $this->mailer->isHTML(true);
        $this->mailer->CharSet = 'UTF-8';
    }

    public function send(PersonalizedRecipient $recipient, string $subject, string $htmlContent, string $textContent = ''): bool
    {
        try {
            // Clear previous recipients
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            
            // Set recipient
            $this->mailer->addAddress($recipient->email, $recipient->name);
            
            // Set content
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $htmlContent;
            $this->mailer->AltBody = $textContent ?: strip_tags($htmlContent);
            
            // Send email
            $result = $this->mailer->send();
            
            if ($result) {
                \PetsBook\Utils\Logger::info('Email sent successfully via SendGrid SMTP', [
                    'to' => $recipient->email,
                    'subject' => $subject,
                    'provider' => 'SendGridSMTP'
                ]);
            }
            
            return $result;
            
        } catch (Exception $e) {
            \PetsBook\Utils\Logger::error('Failed to send email via SendGrid SMTP', [
                'to' => $recipient->email,
                'subject' => $subject,
                'error' => $e->getMessage(),
                'provider' => 'SendGridSMTP'
            ]);
            
            return false;
        }
    }

    public function sendBulk(array $recipients, string $subject, string $htmlContent, string $textContent = ''): array
    {
        $results = [];
        
        foreach ($recipients as $recipient) {
            $results[] = [
                'recipient' => $recipient,
                'success' => $this->send($recipient, $subject, $htmlContent, $textContent)
            ];
        }
        
        return $results;
    }

    public function getProviderName(): string
    {
        return 'SendGridSMTP';
    }
}