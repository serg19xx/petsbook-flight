<?php
// Load environment variables
require_once __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/middleware/CorsMiddleware.php';
\App\Middleware\CorsMiddleware::handle();

$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

require  __DIR__ . '/../src/routes/api.php';

Flight::start();

