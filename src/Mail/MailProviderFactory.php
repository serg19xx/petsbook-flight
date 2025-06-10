<?php

namespace App\Mail;

use App\Utils\Logger;
use App\Mail\Contracts\MailProviderInterface;
use App\Mail\Providers\SendGridApiProvider;
use App\Mail\Providers\SendGridSmtpProvider;
use App\Mail\Providers\MailtrapProvider;


class MailProviderFactory
{
    public static function create(string $driver): MailProviderInterface
    {
        Logger::info("Creating mail provider", "MailProviderFactory", ['driver' => $driver]);
        
        try {
            switch ($driver) {
                case 'sendgrid_api':
                    Logger::info("Creating SendGridApiProvider", "MailProviderFactory");
                    $config = self::getConfigForDriver($driver);
                    return new SendGridApiProvider($config);
                    
                case 'sendgrid_smtp':
                    Logger::info("Creating SendGridSmtpProvider", "MailProviderFactory");
                    $config = self::getConfigForDriver($driver);
                    return new SendGridSmtpProvider($config);
                    
                case 'mailtrap':
                    Logger::info("Creating MailtrapProvider", "MailProviderFactory");
                    
                    // Проверяем существование файла
                    $providerFile = __DIR__ . '/Providers/MailtrapProvider.php';
                    if (!file_exists($providerFile)) {
                        Logger::error("MailtrapProvider file not found", "MailProviderFactory", [
                            'file' => $providerFile
                        ]);
                        throw new \Exception("MailtrapProvider file not found");
                    }
                    
                    Logger::info("MailtrapProvider file found", "MailProviderFactory");
                    
                    // Проверяем существование класса
                    if (!class_exists(MailtrapProvider::class)) {
                        Logger::error("MailtrapProvider class not found", "MailProviderFactory", [
                            'class' => MailtrapProvider::class,
                            'autoload' => [
                                'composer_autoload' => file_exists(__DIR__ . '/../../vendor/autoload.php'),
                                'class_file' => file_exists($providerFile)
                            ]
                        ]);
                        throw new \Exception("MailtrapProvider class not found");
                    }
                    
                    Logger::info("MailtrapProvider class found", "MailProviderFactory");
                    
                    try {
                        $config = self::getConfigForDriver($driver);
                        Logger::info("Mailtrap config loaded", "MailProviderFactory", [
                            'host' => $config['host'],
                            'port' => $config['port'],
                            'username' => $config['username'] ? 'set' : 'not set',
                            'password' => $config['password'] ? 'set' : 'not set',
                            'encryption' => $config['encryption'],
                            'from_address' => $config['from_address'] ?? 'not set',
                            'from_name' => $config['from_name'] ?? 'not set'
                        ]);
                        
                        Logger::info("Creating MailtrapProvider instance", "MailProviderFactory");
                        $provider = new MailtrapProvider($config);
                        Logger::info("MailtrapProvider created successfully", "MailProviderFactory");
                        return $provider;
                    } catch (\Exception $e) {
                        Logger::error("Failed to create MailtrapProvider", "MailProviderFactory", [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine()
                        ]);
                        throw $e;
                    }
                    
                default:
                    Logger::error("Unknown mail driver", "MailProviderFactory", ['driver' => $driver]);
                    throw new \Exception("Unknown mail driver: " . $driver);
            }
        } catch (\Exception $e) {
            Logger::error("Error creating mail provider", "MailProviderFactory", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            throw $e;
        }
    }
    
    private static function getConfigForDriver(string $driver): array
    {
        Logger::info("Getting config for driver", "MailProviderFactory", ['driver' => $driver]);
        
        try {
            $config = match($driver) {
                'sendgrid_api', 'sendgrid_smtp' => [
                    'api_key' => $_ENV['SENDGRID_API_KEY'],
                    'from_address' => $_ENV['SENDGRID_FROM_ADDRESS'],
                    'from_name' => $_ENV['SENDGRID_FROM_NAME'],
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