<?php

namespace App\Middleware;

use App\Utils\Logger;

class CorsMiddleware {
    //private static $logFile = __DIR__ . '/../../logs/cors.log';

    public static function handle() {
        Logger::info("CORS middleware called for " . ($_SERVER['REQUEST_URI'] ?? 'NO URI'),'CorsMiddleware');

        // Получаем origin из заголовков
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // Читаем список разрешённых origin из переменной окружения
        $envOrigins = $_ENV['CORS_ALLOWED_ORIGINS'] ?? getenv('CORS_ALLOWED_ORIGINS') ?? '';
        $allowedOrigins = array_filter(array_map('trim', explode(',', $envOrigins)));

        // Если список пуст — fallback на localhost для dev
        if (empty($allowedOrigins)) {
            $allowedOrigins = [
                'http://localhost:5173',
                'http://localhost:8080'
            ];
        }

        if ($origin && in_array($origin, $allowedOrigins, true)) {
            header("Access-Control-Allow-Origin: $origin");
            header("Access-Control-Allow-Credentials: true");
            header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH");
            header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
            header("Access-Control-Max-Age: 86400");
            header("X-CORS-Debug: Origin $origin allowed");
        } else {
            // Если origin не разрешён — ставим первый из списка (обычно продакшн)
            $defaultOrigin = $allowedOrigins[0] ?? 'http://localhost:5173';
            header("Access-Control-Allow-Origin: $defaultOrigin");
            header("Access-Control-Allow-Credentials: true");
            header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH");
            header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
            header("Access-Control-Max-Age: 86400");
            header("X-CORS-Debug: Origin $origin not in allowed list, defaulting to $defaultOrigin");
        }

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            Logger::info("Handling OPTIONS request", "CorsMiddleware");
            http_response_code(200);
            exit();
        }
    }

}