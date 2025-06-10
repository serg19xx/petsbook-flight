<?php

namespace App\Mail;

use App\Utils\Logger;
use App\Mail\Contracts\MailProviderInterface;
use App\Mail\DTOs\PersonalizedRecipient;
use Twig\Loader\FilesystemLoader;
use Twig\Environment;


class MailService
{
    private Environment $twig;
    private MailProviderInterface $provider;
    private string $templatesPath;

    private array $headerContacts;
    
    public function __construct()
    {
        Logger::info("Starting MailService initialization", "MailService");
        
        // Исправляем путь к шаблонам только для Mailtrap
        if ($_ENV['MAIL_DRIVER'] === 'mailtrap') {
            // Используем путь относительно src
            $this->templatesPath = realpath(__DIR__ . '/../templates');
            
            Logger::info("Templates path", "MailService", [
                'path' => $this->templatesPath,
                'original_path' => __DIR__ . '/../templates',
                'exists' => is_dir($this->templatesPath)
            ]);

            if (!$this->templatesPath || !is_dir($this->templatesPath)) {
                Logger::error("Templates directory not found", "MailService", [
                    'path' => $this->templatesPath,
                    'original_path' => __DIR__ . '/../templates',
                    'current_dir' => __DIR__,
                    'root_dir' => dirname(__DIR__, 2)
                ]);
                throw new \Exception("Templates directory not found: {$this->templatesPath}");
            }

            $loader = new FilesystemLoader($this->templatesPath);
            $this->twig = new Environment($loader);
            Logger::info("Twig environment initialized", "MailService");
        }

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
     * @param PersonalizedRecipient $recipient
     * @param string $subject
     * @param string $template
     * @param ?string $templateId
     * @return void
     */
    public function sendMail(
        PersonalizedRecipient $recipient,
        string $subject,
        string $template,
        ?string $templateId = null
    ): void {
        Logger::info("Starting mail send process", "MailService", [
            'to' => $recipient->getEmail(),
            'subject' => $subject,
            'template' => $template,
            'templateId' => $templateId
        ]);

        try {
            $body = '';
            
            // Используем Twig только для Mailtrap
            if ($_ENV['MAIL_DRIVER'] === 'mailtrap') {
                Logger::info("Rendering local template", "MailService");
                $body = $this->twig->render($template, $recipient->getPersonalizedVars());
                Logger::info("Template rendered", "MailService", [
                    'body' => $body
                ]);
            }

            Logger::info("Attempting to send email via provider", "MailService", [
                'provider' => get_class($this->provider),
                'to' => $recipient->getEmail(),
                'subject' => $subject
            ]);

            // Для SendGrid используем templateId
            if ($_ENV['MAIL_DRIVER'] === 'sendgrid_api') {
                $this->provider->send(
                    $recipient->getEmail(),
                    $subject,
                    $body,
                    [], // attachments
                    $recipient->getPersonalizedVars(), // templateData
                    $templateId // templateId
                );
            } else {
                $this->provider->send(
                    $recipient->getEmail(),
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