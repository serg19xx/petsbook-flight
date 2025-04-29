<?php

namespace App\Middleware;

class CorsMiddleware {
    private static $logFile = __DIR__ . '/../../logs/cors.log';

    public static function handle() {
        self::log("CORS middleware called for " . ($_SERVER['REQUEST_URI'] ?? 'NO URI'));

        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
        header('Access-Control-Allow-Headers: *');

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