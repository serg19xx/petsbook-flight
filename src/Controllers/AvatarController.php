<?php
namespace App\Controllers;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Flight;

class AvatarController {
    private $db;
    private $uploadDir;
    private $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];

    public function __construct($db) {
        $this->uploadDir = __DIR__ . '/../../public/profile-images/avatars/';
        $this->db = $db;
        
        // Создаем директорию если она не существует
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    public function upload() {
        // Получаем токен только из cookie
        $token = $_COOKIE['auth_token'] ?? null;
        
        if (!$token) {
            return Flight::json(['success' => false, 'error' => 'No token provided'], 401);
        }
    
        try {
            // Decode token and get user data
            $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
            $userId = $decoded->user_id;
            $userRole = $decoded->role;
        } catch(\Exception $e) {
            return Flight::json(['success' => false, 'error' => 'Invalid token: ' . $e->getMessage()], 401);
        }
    
        $file = Flight::request()->files->photo;
        if (!$file) {
            return Flight::json(['success' => false, 'error' => 'No file uploaded'], 400);
        }
    
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedTypes)) {
            return Flight::json(['success' => false, 'error' => 'Invalid file type'], 400);
        }
    
        // Убедимся что директория существует и доступна для записи
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
        
        if (!is_writable($this->uploadDir)) {
            return Flight::json(['success' => false, 'error' => 'Upload directory is not writable'], 500);
        }
    
        // New filename format: [role]-[id].[ext]
        $filename = $userRole . '-' . $userId . '.' . $extension;
        $filePath = $this->uploadDir . $filename;
    
        // Remove old avatar if exists
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            // Добавляем timestamp для предотвращения кеширования
            $timestamp = time();
            return Flight::json([
                'success' => true,
                'filename' => $filename,
                'path' => '/profile-images/avatars/' . $filename . '?t=' . $timestamp
            ]);
        }
    
        return Flight::json(['success' => false, 'error' => 'Upload failed'], 500);
    }

    public function getImage() {
        try {
            // Получаем токен
            $headers = getallheaders();
            $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
            
            if (!$token) {
                return Flight::json(['error' => 'Unauthorized'], 401);
            }

            // Проверяем токен
            $userData = Auth::validateToken($token);
            if (!$userData) {
                return Flight::json(['error' => 'Invalid token'], 401);
            }

            // Получаем имя файла
            $filename = basename(Flight::request()->url);
            
            // Проверяем права доступа
            $parts = explode('-', $filename);
            $fileRole = $parts[0];
            $fileUserId = explode('.', $parts[1])[0];
            
            if ($userData['id'] != $fileUserId) {
                return Flight::json(['error' => 'Access denied'], 403);
            }

            $filePath = $this->uploadDir . $filename;
            
            if (!file_exists($filePath)) {
                return Flight::json(['error' => 'File not found'], 404);
            }

            // Добавляем заголовки для предотвращения кеширования
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
            header('ETag: "' . md5_file($filePath) . '"');
            
            // Отдаем файл
            header('Content-Type: ' . mime_content_type($filePath));
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            
        } catch (Exception $e) {
            return Flight::json(['error' => $e->getMessage()], 500);
        }
    }    
}