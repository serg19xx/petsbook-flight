<?php
namespace App\Controllers;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Flight;
use App\Utils\Logger;

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
    

        Logger::info("Token: " . $token, "AvatarController");

        try {
            // Decode token and get user data
            $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
            $userId = $decoded->user_id;
            $userRole = $decoded->role;
        } catch(\Exception $e) {
            Logger::error("Invalid token: " . $e->getMessage(), "AvatarController");
            return Flight::json(['success' => false, 'error' => 'Invalid token: ' . $e->getMessage()], 401);
        }

        Logger::info("User data", "AvatarController", [
            'user_id' => $userId,
            'user_role' => $userRole
        ]);

        // Добавить логи для проверки файла
        Logger::info("Files array", "AvatarController", [
            'files' => $_FILES,
            'request_files' => Flight::request()->files
        ]);

        // Работает везде:
        $file = $_FILES['photo'] ?? null;

        // Вместо (работает только локально):
        // $file = Flight::request()->files->photo;

        Logger::info("File object", "AvatarController", [
            'file_exists' => !empty($file),
            'file_name' => $file['name'] ?? 'NOT_SET',
            'file_size' => $file['size'] ?? 'NOT_SET',
            'file_type' => $file['type'] ?? 'NOT_SET'
        ]);

        if (!$file) {
            Logger::error("No file uploaded", "AvatarController");
            return Flight::json(['success' => false, 'error' => 'No file uploaded'], 400);
        }
    
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedTypes)) {
            return Flight::json(['success' => false, 'error' => 'Invalid file type'], 400);
        }
    
        Logger::info("Upload directory: " . $this->uploadDir, "AvatarController");

        // Убедимся что директория существует и доступна для записи
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }

        Logger::info("Upload directory is writable: " . is_writable($this->uploadDir), "AvatarController");

        // Добавить логи для проверки файла
        Logger::info("File tmp_name: " . $file['tmp_name'], "AvatarController");
        Logger::info("File tmp_name exists: " . file_exists($file['tmp_name']), "AvatarController");
        Logger::info("File tmp_name is readable: " . is_readable($file['tmp_name']), "AvatarController");

        // Проверить размер файла
        Logger::info("File size check: " . ($file['size'] > 0 ? 'OK' : 'ZERO'), "AvatarController");

        // Проверить ошибки загрузки
        Logger::info("File upload error: " . $file['error'], "AvatarController");
        
        if (!is_writable($this->uploadDir)) {
            return Flight::json(['success' => false, 'error' => 'Upload directory is not writable'], 500);
        }

        Logger::info("File is writable: " . is_writable($file['tmp_name']), "AvatarController");
    
        // New filename format: [role]-[id].[ext]
        $filename = $userRole . '-' . $userId . '.' . $extension;
        $filePath = $this->uploadDir . $filename;

        Logger::info("File path: " . $filePath, "AvatarController");
    
        // Remove old avatar if exists
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        Logger::info("File exists: " . file_exists($filePath), "AvatarController");
    
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            Logger::success("Avatar moved successfully", "AvatarController");
            // Добавляем timestamp для предотвращения кеширования
            $timestamp = time();
            return Flight::json([
                'success' => true,
                'filename' => $filename,
                'path' => '/profile-images/avatars/' . $filename . '?t=' . $timestamp
            ]);
        }

        Logger::success("Avatar uploaded successfully", "AvatarController");
        return Flight::json(['success' => false, 'error' => 'Upload failed'], 500);
    }

    public function serveImage() {
        $path = Flight::request()->url;
        $filename = basename($path);
        $filePath = $this->uploadDir . $filename;
        
        if (!file_exists($filePath)) {
            return Flight::json(['error' => 'File not found'], 404);
        }
        
        $mimeType = mime_content_type($filePath);
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
    }

}