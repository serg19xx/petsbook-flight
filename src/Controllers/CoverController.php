<?php

namespace App\Controllers;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Flight;
use App\Utils\Logger;

class CoverController {
    private $db;
    private $uploadDir;
    private $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
    private $baseUrl;

    public function __construct($db) {
        $this->uploadDir = __DIR__ . '/../../public/profile-images/covers/';
        $this->baseUrl = $_ENV['APP_URL'] ?? 'http://localhost:8080';
        $this->db = $db;
        
        // Создаем директорию если она не существует
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        }
        
        // Принудительно устанавливаем права доступа при каждом запуске
        chmod($this->uploadDir, 0777);
        
        Logger::info("CoverController initialized", "CoverController", [
            'upload_dir' => $this->uploadDir,
            'dir_exists' => is_dir($this->uploadDir) ? 'YES' : 'NO',
            'dir_writable' => is_writable($this->uploadDir) ? 'YES' : 'NO',
            'dir_permissions' => substr(sprintf('%o', fileperms($this->uploadDir)), -4)
        ]);
    }

    public function upload() {
        // Получаем токен только из cookie
        $token = $_COOKIE['auth_token'] ?? null;
        
        if (!$token) {
            return Flight::json(['success' => false, 'error' => 'No token provided'], 401);
        }

        Logger::info("Token: " . $token, "CoverController");

        try {
            // Decode token and get user data
            $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
            $userId = $decoded->user_id;
            $userRole = $decoded->role;
        } catch(\Exception $e) {
            Logger::error("Invalid token: " . $e->getMessage(), "CoverController");
            return Flight::json(['success' => false, 'error' => 'Invalid token: ' . $e->getMessage()], 401);
        }

        Logger::info("User data", "CoverController", [
            'user_id' => $userId,
            'user_role' => $userRole
        ]);

        // Добавить логи для проверки файла
        Logger::info("Files array", "CoverController", [
            'files' => $_FILES,
            'request_files' => Flight::request()->files
        ]);

        // Работает везде:
        $file = $_FILES['photo'] ?? null;

        Logger::info("File object", "CoverController", [
            'file_exists' => !empty($file),
            'file_name' => $file['name'] ?? 'NOT_SET',
            'file_size' => $file['size'] ?? 'NOT_SET',
            'file_type' => $file['type'] ?? 'NOT_SET'
        ]);

        if (!$file) {
            Logger::error("No file uploaded", "CoverController");
            return Flight::json(['success' => false, 'error' => 'No file uploaded'], 400);
        }

        // После проверки файла добавить:
        Logger::info("File validation passed", "CoverController");

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        Logger::info("File extension: " . $extension, "CoverController");

        if (!in_array($extension, $this->allowedTypes)) {
            Logger::error("Invalid file type: " . $extension, "CoverController");
            return Flight::json(['success' => false, 'error' => 'Invalid file type'], 400);
        }

        Logger::info("File type validation passed", "CoverController");

        // Убедимся что директория существует и доступна для записи
        if (!is_dir($this->uploadDir)) {
            Logger::info("Creating upload directory", "CoverController");
            mkdir($this->uploadDir, 0777, true);
        }

        // Directory check before upload
        Logger::info("Directory check before upload", "CoverController", [
            'upload_dir' => $this->uploadDir,
            'dir_exists' => is_dir($this->uploadDir) ? 'YES' : 'NO',
            'dir_writable' => is_writable($this->uploadDir) ? 'YES' : 'NO',
            'dir_permissions' => substr(sprintf('%o', fileperms($this->uploadDir)), -4)
        ]);

        Logger::info("Upload directory is writable: " . is_writable($this->uploadDir), "CoverController");

        if (!is_writable($this->uploadDir)) {
            Logger::error("Upload directory is not writable", "CoverController");
            return Flight::json(['success' => false, 'error' => 'Upload directory is not writable'], 500);
        }

        Logger::info("Directory permissions check passed", "CoverController");

        // Добавить логи для проверки файла
        Logger::info("File tmp_name: " . $file['tmp_name'], "CoverController");
        Logger::info("File tmp_name exists: " . file_exists($file['tmp_name']), "CoverController");
        Logger::info("File tmp_name is readable: " . is_readable($file['tmp_name']), "CoverController");

        // Проверить размер файла
        Logger::info("File size check: " . ($file['size'] > 0 ? 'OK' : 'ZERO'), "CoverController");

        // Проверить ошибки загрузки
        Logger::info("File upload error: " . $file['error'], "CoverController");
        
        Logger::info("File is writable: " . is_writable($file['tmp_name']), "CoverController");

        // New filename format: [role]-[id].[ext]
        $filename = $userRole . '-' . $userId . '.' . $extension;
        $filePath = $this->uploadDir . $filename;

        Logger::info("File path: " . $filePath, "CoverController");

        // Remove old cover if exists
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        Logger::info("File exists: " . file_exists($filePath), "CoverController");

        // Starting file move
        Logger::info("Starting file move", "CoverController", [
            'tmp_name' => $file['tmp_name'],
            'target_path' => $filePath,
            'target_dir_exists' => is_dir(dirname($filePath)),
            'target_dir_writable' => is_writable(dirname($filePath))
        ]);

        // Final file path check
        Logger::info("Final file path check", "CoverController", [
            'upload_dir' => $this->uploadDir,
            'upload_dir_realpath' => realpath($this->uploadDir),
            'upload_dir_writable' => is_writable($this->uploadDir),
            'php_user' => get_current_user(),
            'php_process_user' => posix_getpwuid(posix_geteuid())['name'] ?? 'unknown'
        ]);

        // File path details
        Logger::info("File path details", "CoverController", [
            'filename' => $filename,
            'filepath' => $filePath,
            'filepath_exists' => file_exists($filePath)
        ]);

        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            Logger::info("File moved successfully", "CoverController", [
                'target_path' => $filePath,
                'file_exists' => file_exists($filePath),
                'file_size' => filesize($filePath)
            ]);
            // Добавляем timestamp для предотвращения кеширования
            $timestamp = time();
            return Flight::json([
                'success' => true,
                'filename' => $filename,
                'path' => '/profile-images/covers/' . $filename . '?t=' . $timestamp
            ]);
        } else {
            Logger::error("Failed to move file", "CoverController", [
                'tmp_name' => $file['tmp_name'],
                'target_path' => $filePath,
                'error' => error_get_last()
            ]);
            return Flight::json(['success' => false, 'error' => 'Upload failed'], 500);
        }
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