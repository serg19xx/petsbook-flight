<?php

namespace App\Mail;

use App\Utils\Logger;
use App\Mail\Contracts\MailProviderInterface;
use App\Mail\DTOs\PersonalizedRecipient;


class MailService
{
    private MailProviderInterface $provider;

    public function __construct()
    {
        Logger::info("Starting MailService initialization", "MailService");

        Logger::info("Creating mail provider", "MailService");
        $config = MailProviderFactory::getConfigForDriver($_ENV['MAIL_DRIVER']);
        Logger::info("Config loaded successfully", "MailService", [
            'config' => $config
        ]);
        $this->provider = MailProviderFactory::create($_ENV['MAIL_DRIVER'], $config);
        Logger::info("Mail provider created successfully", "MailService", [
            'provider' => get_class($this->provider)
        ]);

        Logger::info("MailService initialization completed", "MailService");
    }

    /**
     * @param string $recipient
     * @param string $subject
     * @param string $body
     * @param ?string $templateId
     * @return void
     */
    public function sendMail(
        $recipient,
        string $subject,
        string $body,
        ?string $templateId = null
    ): void {
        Logger::info("Starting mail send process", "MailService", [
            'to' => $recipient,
            'subject' => $subject,
            'templateId' => $templateId
        ]);

        try {
            Logger::info("Attempting to send email via provider", "MailService", [
                'provider' => get_class($this->provider),
                'to' => $recipient,
                'subject' => $subject
            ]);

            $result = false;
            
            if ($_ENV['MAIL_DRIVER'] === 'sendgrid_api') {
                $result = $this->provider->send(
                    $recipient,
                    $subject,
                    $body,
                    [], // attachments
                    [], // templateData
                    $templateId
                );
            } else {
                $result = $this->provider->send(
                    $recipient,
                    $subject,
                    $body
                );
            }

            if ($result) {
                Logger::info("Email send result: success", "MailService");
            } else {
                Logger::error("Email send result: failed", "MailService", [
                    'to' => $recipient,
                    'subject' => $subject,
                    'provider' => get_class($this->provider)
                ]);
                throw new \Exception("Failed to send email via " . get_class($this->provider));
            }
        } catch (\Exception $e) {
            Logger::error("Failed to send email", "MailService", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}