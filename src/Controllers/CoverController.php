<?php

namespace App\Controllers;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Flight;

class CoverController {
    private $db;
    private $uploadDir;
    private $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
    private $baseUrl;

    public function __construct($db) {
        $this->uploadDir = __DIR__ . '/../../public/profile-images/covers/';
        $this->baseUrl = $_ENV['APP_URL'] ?? 'http://localhost:8080';
        $this->db = $db;
    }

    public function upload() {
        $token = null;
    
        // 1. Пробуем взять из заголовка Authorization
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $header = $_SERVER['HTTP_AUTHORIZATION'];
            if (stripos($header, 'Bearer ') === 0) {
                $token = substr($header, 7);
            } else {
                $token = $header;
            }
        }
        // 2. Если не нашли — пробуем из cookie
        if (!$token && !empty($_COOKIE['auth_token'])) {
            $token = $_COOKIE['auth_token'];
        }
    
        if (!$token) {
            return Flight::json(['success' => false, 'error' => 'Invalid token'], 401);
        }
    
        try {
            $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
            
            // Используем правильные поля из JWT
            $userId = $decoded->user_id ?? $decoded->id ?? null;
            $userRole = $decoded->role ?? 'user';
            
            if (!$userId) {
                return Flight::json(['success' => false, 'error' => 'Invalid token structure'], 401);
            }
        } catch(\Exception $e) {
            return Flight::json(['success' => false, 'error' => 'Invalid token'], 401);
        }
    
        $file = Flight::request()->files->photo;
        if (!$file) {
            return Flight::json(['success' => false, 'error' => 'No file uploaded'], 400);
        }
    
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedTypes)) {
            return Flight::json(['success' => false, 'error' => 'Invalid file type'], 400);
        }
    
        $filename = $userRole . '-' . $userId . '.' . $extension;
        $filePath = $this->uploadDir . $filename;
    
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            return Flight::json([
                'success' => true,
                'url' => $this->baseUrl . '/profile-images/covers/' . $filename,
                'filename' => $filename,
                'path' => $this->baseUrl . '/profile-images/covers/' . $filename,
                'fullPath' => realpath($filePath)
            ]);
        }
    
        return Flight::json(['success' => false, 'error' => 'Upload failed'], 500);
    }

}