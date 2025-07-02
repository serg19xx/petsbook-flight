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

        Logger::info("Using mail driver: " . $_ENV['MAIL_DRIVER'], "MailService", [
            'driver' => $_ENV['MAIL_DRIVER'],
            'env' => [
                'MAIL_DRIVER' => $_ENV['MAIL_DRIVER'],
                'MAILTRAP_HOST' => $_ENV['MAILTRAP_HOST'] ?? 'not set',
                'MAILTRAP_PORT' => $_ENV['MAILTRAP_PORT'] ?? 'not set',
                'MAILTRAP_USERNAME' => isset($_ENV['MAILTRAP_USERNAME']) ? 'set' : 'not set',
                'MAILTRAP_PASSWORD' => isset($_ENV['MAILTRAP_PASSWORD']) ? 'set' : 'not set'
            ]
        ]);

        Logger::info("Creating mail provider", "MailService");
        $this->provider = MailProviderFactory::create($_ENV['MAIL_DRIVER']);
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

            if ($_ENV['MAIL_DRIVER'] === 'sendgrid_api') {
                $this->provider->send(
                    $recipient,
                    $subject,
                    $body,
                    [], // attachments
                    [], // templateData
                    $templateId
                );
            } else {
                $this->provider->send(
                    $recipient,
                    $subject,
                    $body
                );
            }

            Logger::info("Email send result: success", "MailService");
        } catch (\Exception $e) {
            Logger::error("Failed to send email", "MailService", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}