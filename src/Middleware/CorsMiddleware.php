<?php

namespace App\Middleware;

use App\Utils\Logger;

class CorsMiddleware {
    //private static $logFile = __DIR__ . '/../../logs/cors.log';

    public static function handle() {
        if (headers_sent()) {
            Logger::warning("Headers already sent, skipping CORS middleware", 'CorsMiddleware');
            return;
        }

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
                'http://localhost:8080',
                'http://localhost:3000',
                'https://64.188.10.53'
            ];
        }

        if ($origin && in_array($origin, $allowedOrigins, true)) {
            header("Access-Control-Allow-Origin: $origin");
            header("Access-Control-Allow-Credentials: true");
            header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH");
            header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Cache-Control");
            header("Access-Control-Max-Age: 86400");
            header("X-CORS-Debug: Origin $origin allowed");
            
            // Добавляем заголовки для длительных запросов и SSE
            header("Connection: keep-alive");
            header("Keep-Alive: timeout=300");
            header("X-Accel-Buffering: no"); // Отключаем буферизацию в nginx
        } else {
            // Если origin не разрешён — ставим первый из списка (обычно продакшн)
            $defaultOrigin = $allowedOrigins[0] ?? 'http://localhost:5173';
            header("Access-Control-Allow-Origin: $defaultOrigin");
            header("Access-Control-Allow-Credentials: true");
            header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH");
            header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Cache-Control");
            header("Access-Control-Max-Age: 86400");
            header("X-CORS-Debug: Origin $origin not in allowed list, defaulting to $defaultOrigin");
        }

        // Обработка preflight запросов
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit();
        }
    }

}