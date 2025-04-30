<?php

namespace App\Middleware;

class CorsMiddleware {
    private static $logFile = __DIR__ . '/../../logs/cors.log';

    public static function handle() {
        self::log("CORS middleware called for " . ($_SERVER['REQUEST_URI'] ?? 'NO URI'));

        // Получаем origin из заголовков
        $origin = $_SERVER['HTTP_ORIGIN'] ?? 'https://site.petsbook.ca';
        
        // Разрешаем только нужные домены
        $allowedOrigins = [
            'https://site.petsbook.ca',
            'http://localhost:5173',  // для локальной разработки
        ];

        if (in_array($origin, $allowedOrigins)) {
            header("Access-Control-Allow-Origin: $origin");
        } else {
            header("Access-Control-Allow-Origin: https://site.petsbook.ca");
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Allow-Credentials: true');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            self::log("Handling OPTIONS request");
            http_response_code(200);
            exit();
        }
    }

    private static function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message\n";
        error_log($logMessage, 3, self::$logFile);
    }
}