<?php

use App\Middleware\CorsMiddleware;
use App\Controllers\AuthController;

// Enable CORS
CorsMiddleware::handle();

// Инициализация контроллера
$authController = new AuthController();

// Маршруты аутентификации
Flight::route('POST /api/auth/login', [$authController, 'login']); // Добавляем префикс /api
Flight::route('POST /api/auth/register', [$authController, 'register']);
Flight::route('POST /api/auth/logout', [$authController, 'logout']);
Flight::route('POST /api/auth/password-reset', [$authController, 'passwordReset']);
Flight::route('GET /api/auth/user', [$authController, 'getUserData']);

// Handle 404
Flight::map('notFound', function() {
    Flight::json([
        'status' => 'error',
        'message' => 'Route not found'
    ], 404);
});

