<?php

use App\Middleware\CorsMiddleware;
use App\Controllers\AuthController;
use App\Controllers\UserController;

/**
 * API Routes configuration
 * 
 * This file contains all API route definitions and their corresponding controller methods.
 * Routes are grouped by functionality (auth, user, etc.)
 */

// Enable CORS
CorsMiddleware::handle();

$authController = new AuthController();
$userController = new UserController();

/**
 * Authentication Routes
 * 
 * @route POST /api/auth/login - User login
 * @route POST /api/auth/register - New user registration
 * @route POST /api/auth/logout - User logout
 * @route POST /api/auth/password-reset - Password reset request
 * @route POST /api/auth/set-new-password - Set new password
 * @route GET /api/auth/verify-email/@token - Email verification
 */
Flight::route('POST /api/auth/login', [$authController, 'login']);
Flight::route('POST /api/auth/register', [$authController, 'register']);
Flight::route('POST /api/auth/logout', [$authController, 'logout']);
Flight::route('POST /api/auth/password-reset', [$authController, 'passwordReset']);
Flight::route('POST /api/auth/set-new-password', [$authController, 'setNewPassword']);
Flight::route('GET /api/auth/verify-email/@token', [$authController, 'verifyEmail']);

/**
 * User Routes
 * 
 * @route GET /api/user/getuser - Get current user data
 */
Flight::route('GET /api/user/getuser', [$userController, 'getUserData']);

// Handle 404
Flight::map('notFound', function() {
    Flight::json([
        'status' => 'error',
        'message' => 'Route not found'
    ], 404);
});

