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
            mkdir($this->uploadDir, 0777, true);
        }
        
        // Принудительно устанавливаем права доступа при каждом запуске
        chmod($this->uploadDir, 0777);
        
        Logger::info("AvatarController initialized", "AvatarController", [
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
    
        // После проверки файла добавить:
        Logger::info("File validation passed", "AvatarController");

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        Logger::info("File extension: " . $extension, "AvatarController");

        if (!in_array($extension, $this->allowedTypes)) {
            Logger::error("Invalid file type: " . $extension, "AvatarController");
            return Flight::json(['success' => false, 'error' => 'Invalid file type'], 400);
        }

        Logger::info("File type validation passed", "AvatarController");

        // Убедимся что директория существует и доступна для записи
        if (!is_dir($this->uploadDir)) {
            Logger::info("Creating upload directory", "AvatarController");
            mkdir($this->uploadDir, 0755, true);
        }

        // В методе upload() перед проверкой is_writable добавить:
        Logger::info("Directory check before upload", "AvatarController", [
            'upload_dir' => $this->uploadDir,
            'dir_exists' => is_dir($this->uploadDir) ? 'YES' : 'NO',
            'dir_writable' => is_writable($this->uploadDir) ? 'YES' : 'NO',
            'dir_permissions' => substr(sprintf('%o', fileperms($this->uploadDir)), -4)
        ]);

        Logger::info("Upload directory is writable: " . is_writable($this->uploadDir), "AvatarController");

        if (!is_writable($this->uploadDir)) {
            Logger::error("Upload directory is not writable", "AvatarController");
            return Flight::json(['success' => false, 'error' => 'Upload directory is not writable'], 500);
        }

        Logger::info("Directory permissions check passed", "AvatarController");

        // Добавить логи для проверки файла
        Logger::info("File tmp_name: " . $file['tmp_name'], "AvatarController");
        Logger::info("File tmp_name exists: " . file_exists($file['tmp_name']), "AvatarController");
        Logger::info("File tmp_name is readable: " . is_readable($file['tmp_name']), "AvatarController");

        // Проверить размер файла
        Logger::info("File size check: " . ($file['size'] > 0 ? 'OK' : 'ZERO'), "AvatarController");

        // Проверить ошибки загрузки
        Logger::info("File upload error: " . $file['error'], "AvatarController");
        
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
    
        // После всех проверок добавить:
        Logger::info("Starting file move", "AvatarController", [
            'tmp_name' => $file['tmp_name'],
            'target_path' => $filePath,
            'target_dir_exists' => is_dir(dirname($filePath)),
            'target_dir_writable' => is_writable(dirname($filePath))
        ]);

        // В методе upload() перед move_uploaded_file добавить:
        Logger::info("Final file path check", "AvatarController", [
            'upload_dir' => $this->uploadDir,
            'upload_dir_realpath' => realpath($this->uploadDir),
            'upload_dir_writable' => is_writable($this->uploadDir),
            'php_user' => get_current_user(),
            'php_process_user' => posix_getpwuid(posix_geteuid())['name'] ?? 'unknown'
        ]);

        // New filename format: [role]-[id].[ext]
        $filename = $userRole . '-' . $userId . '.' . $extension;
        $filePath = $this->uploadDir . $filename;

        Logger::info("File path details", "AvatarController", [
            'filename' => $filename,
            'filepath' => $filePath,
            'filepath_exists' => file_exists($filePath)
        ]);

        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            Logger::info("File moved successfully", "AvatarController", [
                'target_path' => $filePath,
                'file_exists' => file_exists($filePath),
                'file_size' => filesize($filePath)
            ]);
            // Добавляем timestamp для предотвращения кеширования
            $timestamp = time();
            return Flight::json([
                'success' => true,
                'filename' => $filename,
                'path' => '/profile-images/avatars/' . $filename . '?t=' . $timestamp
            ]);
        } else {
            Logger::error("Failed to move file", "AvatarController", [
                'tmp_name' => $file['tmp_name'],
                'target_path' => $filePath,
                'error' => error_get_last()
            ]);
            return Flight::json(['success' => false, 'error' => 'Upload failed'], 500);
        }
    }

    public function serveImage() {
        Logger::info("serveImage called", "AvatarController", [
            'url' => Flight::request()->url,
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A'
        ]);
        
        $path = Flight::request()->url;
        $filename = basename($path);
        $filePath = $this->uploadDir . $filename;
        
        Logger::info("File check", "AvatarController", [
            'filename' => $filename,
            'filePath' => $filePath,
            'exists' => file_exists($filePath)
        ]);
        
        // Проверяем существует ли файл (без учета параметров)
        $cleanFilename = explode('?', $filename)[0]; // Убираем параметры
        $cleanFilePath = $this->uploadDir . $cleanFilename;
        
        Logger::info("Clean file check", "AvatarController", [
            'cleanFilename' => $cleanFilename,
            'cleanFilePath' => $cleanFilePath,
            'exists' => file_exists($cleanFilePath)
        ]);
        
        if (!file_exists($cleanFilePath)) {
            Logger::info("File not found, generating default avatar", "AvatarController");
            
            // Получаем пол из токена
            $gender = 'other'; // дефолтный пол
            
            $token = $_COOKIE['auth_token'] ?? null;
            Logger::info("Token check", "AvatarController", [
                'token_exists' => !empty($token)
            ]);
            
            if ($token) {
                try {
                    $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
                    $userId = $decoded->user_id;
                    
                    Logger::info("JWT decoded", "AvatarController", [
                        'userId' => $userId
                    ]);
                    
                    // Используем хранимую процедуру как в UserController
                    $stmt = $this->db->prepare("CALL sp_GetUserData(:login_id)");
                    $stmt->execute([':login_id' => $userId]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    Logger::info("User data from DB", "AvatarController", [
                        'result' => $result
                    ]);
                    
                    if ($result) {
                        $userData = json_decode($result['data'], true);
                        if ($userData && isset($userData['user']['gender']) && !empty($userData['user']['gender'])) {
                            $gender = strtolower(trim($userData['user']['gender']));
                            Logger::info("Gender extracted", "AvatarController", [
                                'original' => $userData['user']['gender'],
                                'normalized' => $gender
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    Logger::error("JWT decode error", "AvatarController", [
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            Logger::info("Final gender determined", "AvatarController", [
                'gender' => $gender
            ]);
            
            // Определяем URL в зависимости от пола
            switch ($gender) {
                case 'male':
                    $dicebearUrl = "https://api.dicebear.com/9.x/adventurer/svg?seed=Brian";
                    break;
                case 'female':
                    $dicebearUrl = "https://api.dicebear.com/9.x/adventurer/svg?seed=Andrea";
                    break;
                default:
                    $dicebearUrl = "https://api.dicebear.com/9.x/icons/svg?seed=Easton";
                    break;
            }
            
            Logger::info("Redirecting to Dicebear", "AvatarController", [
                'url' => $dicebearUrl,
                'gender_used' => $gender
            ]);
            
            header('Location: ' . $dicebearUrl);
            exit;
        }
        
        $mimeType = mime_content_type($cleanFilePath);
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($cleanFilePath));
        readfile($cleanFilePath);
    }

}