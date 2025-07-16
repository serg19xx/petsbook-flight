<?php

require_once 'vendor/autoload.php';

use App\Mail\MailProviderFactory;
use App\Mail\DTOs\PersonalizedRecipient;

// Загружаем переменные окружения
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

try {
    // Создаем Gmail SMTP провайдер
    $config = MailProviderFactory::getConfigForDriver('gmail_smtp');
    $provider = MailProviderFactory::create('gmail_smtp', $config);
    
    echo "✅ Gmail SMTP провайдер создан успешно\n";
    echo "📧 Конфигурация: " . json_encode($config, JSON_PRETTY_PRINT) . "\n\n";
    
    // Тестируем отправку email
    $recipient = new PersonalizedRecipient('test@example.com', 'Test User');
    $subject = 'Тест Gmail SMTP провайдера';
    $body = '<h1>Тест Gmail SMTP</h1><p>Это тестовое письмо от Gmail SMTP провайдера.</p>';
    
    echo "📤 Отправляем тестовое письмо...\n";
    $result = $provider->send($recipient, $subject, $body);
    
    if ($result) {
        echo "✅ Письмо отправлено успешно!\n";
        exit(0);
    } else {
        echo "❌ Ошибка отправки письма\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    echo "📋 Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
} 