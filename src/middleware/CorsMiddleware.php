<?php

namespace App\Middleware;

class CorsMiddleware {
    public static function handle() {
        \Flight::after('start', function() {
            // Указываем конкретный домен вместо *
            header("Access-Control-Allow-Origin: http://localhost:5173");
            header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
            header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
            header("Access-Control-Allow-Credentials: true");
            header("Access-Control-Max-Age: 3600");
            
            if (\Flight::request()->method === 'OPTIONS') {
                \Flight::stop();
            }
        });
    }
}
