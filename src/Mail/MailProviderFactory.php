<?php

namespace App\Mail;

use App\Utils\Logger;
use App\Mail\Contracts\MailProviderInterface;
use App\Mail\Providers\SendGridApiProvider;
use App\Mail\Providers\SendGridSmtpProvider;
use App\Mail\Providers\MailtrapProvider;
use App\Mail\Providers\GmailSmtpProvider;

class MailProviderFactory
{
    public static function create(string $provider, array $config): MailProviderInterface
    {
        Logger::info("Creating mail provider", "MailProviderFactory", ['provider' => $provider]);
        
        try {
            Logger::info("About to create {$provider}", "MailProviderFactory");
            return match ($provider) {
                'mailtrap' => new MailtrapProvider($config),
                'sendgrid_api' => new SendGridApiProvider($config),
                'sendgrid_smtp' => new SendGridSmtpProvider($config),
                'gmail_smtp' => new GmailSmtpProvider($config),
                default => throw new \InvalidArgumentException("Unsupported mail provider: {$provider}")
            };
        } catch (\Exception $e) {
            Logger::error("Error creating mail provider", "MailProviderFactory", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    public static function getConfigForDriver(string $driver): array
    {
        Logger::info("Getting config for driver", "MailProviderFactory", ['driver' => $driver]);
        
        try {
            $config = match($driver) {
                'sendgrid_api' => [
                    'api_key' => $_ENV['SENDGRID_API_KEY'],
                    'from_address' => $_ENV['SENDGRID_FROM_ADDRESS'],
                    'from_name' => $_ENV['SENDGRID_FROM_NAME'],
                ],
                'sendgrid_smtp' => [
                    'smtp_host' => 'smtp.sendgrid.net',
                    'smtp_port' => 587,
                    'smtp_username' => 'apikey',
                    'smtp_password' => $_ENV['SENDGRID_SMTP_PASSWORD'], // ← Отдельный SMTP ключ
                    'smtp_encryption' => 'tls',
                    'from_address' => $_ENV['SENDGRID_FROM_ADDRESS'],
                    'from_name' => $_ENV['SENDGRID_FROM_NAME'],
                ],
                'gmail_smtp' => [
                    'host' => 'smtp.gmail.com',
                    'port' => '587',
                    'username' => $_ENV['GMAIL_USERNAME'],
                    'password' => $_ENV['GMAIL_APP_PASSWORD'],
                    'encryption' => 'tls',
                    'from_address' => $_ENV['GMAIL_FROM_ADDRESS'] ?? $_ENV['GMAIL_USERNAME'],
                    'from_name' => $_ENV['GMAIL_FROM_NAME'] ?? 'PetsBook',
                ],
                'mailtrap' => [
                    'host' => $_ENV['MAILTRAP_HOST'],
                    'port' => $_ENV['MAILTRAP_PORT'],
                    'username' => $_ENV['MAILTRAP_USERNAME'],
                    'password' => $_ENV['MAILTRAP_PASSWORD'],
                    'encryption' => $_ENV['MAILTRAP_ENCRYPTION'] ?? 'tls',
                    'from_address' => $_ENV['MAILTRAP_FROM_ADDRESS'] ?? 'noreply@petsbook.com',
                    'from_name' => $_ENV['MAILTRAP_FROM_NAME'] ?? 'PetsBook',
                ],
                default => throw new \InvalidArgumentException("Unsupported mail driver: {$driver}")
            };
            
            Logger::info("Config loaded successfully", "MailProviderFactory");
            return $config;
            
        } catch (\Exception $e) {
            Logger::error("Failed to load config", "MailProviderFactory", [
                'error' => $e->getMessage(),
                'driver' => $driver
            ]);
            throw $e;
        }
    }
}