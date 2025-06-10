<?php

namespace App\Utils;

class Logger
{
    private static string $logDir;
    private static array $logLevels = [
        'DEBUG' => 0,
        'INFO' => 1,
        'WARNING' => 2,
        'ERROR' => 3,
        'CRITICAL' => 4
    ];
    
    public static function init(string $logDir = null): void
    {
        self::$logDir = $logDir ?? __DIR__ . '/../../logs';
        
        // Создаем директорию для логов, если она не существует
        if (!file_exists(self::$logDir)) {
            mkdir(self::$logDir, 0777, true);
        }
    }
    
    public static function log(
        string $message, 
        string $level = 'INFO', 
        string $context = '', 
        array $data = []
    ): void {
        try {
            if (!isset(self::$logDir)) {
                self::init();
            }
            
            $timestamp = date('Y-m-d H:i:s');
            $logFile = self::$logDir . "/" . date('Y-m-d') . ".log";
            
            // Форматируем сообщение
            $logMessage = "[$timestamp][$level]";
            if ($context) {
                $logMessage .= "[$context]";
            }
            $logMessage .= " $message";
            
            // Добавляем данные, если они есть
            if (!empty($data)) {
                $logMessage .= " Data: " . json_encode($data);
            }
            
            $logMessage .= PHP_EOL;
            
            // Записываем в файл
            file_put_contents($logFile, $logMessage, FILE_APPEND);
            
            // Для ошибок и критических сообщений также выводим в error_log
            if (self::$logLevels[$level] >= self::$logLevels['ERROR']) {
                error_log($logMessage);
            }
            
        } catch (\Exception $e) {
            error_log("Logger Error: " . $e->getMessage());
        }
    }
    
    // Удобные методы для разных уровней логирования
    public static function debug(string $message, string $context = '', array $data = []): void
    {
        self::log($message, 'DEBUG', $context, $data);
    }
    
    public static function info(string $message, string $context = '', array $data = []): void
    {
        self::log($message, 'INFO', $context, $data);
    }
    
    public static function warning(string $message, string $context = '', array $data = []): void
    {
        self::log($message, 'WARNING', $context, $data);
    }
    
    public static function error(string $message, string $context = '', array $data = []): void
    {
        self::log($message, 'ERROR', $context, $data);
    }
    
    public static function critical(string $message, string $context = '', array $data = []): void
    {
        self::log($message, 'CRITICAL', $context, $data);
    }
}