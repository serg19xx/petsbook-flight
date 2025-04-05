<?php

use App\Middleware\CorsMiddleware;
use App\Controllers\AuthController;
use App\Controllers\UserController;

// Enable CORS
CorsMiddleware::handle();

// Инициализация контроллеров
$authController = new AuthController();
$userController = new UserController();

// Маршруты аутентификации
Flight::route('POST /api/auth/login', [$authController, 'login']);
Flight::route('POST /api/auth/register', [$authController, 'register']);
Flight::route('POST /api/auth/logout', [$authController, 'logout']);
Flight::route('POST /api/auth/password-reset', [$authController, 'passwordReset']);
// Маршрут верификации email - исправляем маршрут
Flight::route('GET /api/auth/verify-email/@token', [$authController, 'verifyEmail']);

// Маршруты пользователя
Flight::route('GET /api/user/getuser', [$userController, 'getUserData']);

// Handle 404
Flight::map('notFound', function() {
    Flight::json([
        'status' => 'error',
        'message' => 'Route not found'
    ], 404);
});

