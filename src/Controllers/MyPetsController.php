<?php

namespace App\Controllers;

use PDO;
use Flight;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Utils\Logger;

/**
 * MyPets Controller
 * 
 * Handles all pet-related operations for authenticated users.
 * 
 * API Response Codes:
 * 
 * Success codes:
 * - PETS_LIST_SUCCESS: Successfully retrieved user's pets
 * - PET_CREATE_SUCCESS: Successfully created new pet
 * - PET_UPDATE_SUCCESS: Successfully updated pet data
 * - PET_DELETE_SUCCESS: Successfully deleted pet
 * - PET_STATUS_UPDATE_SUCCESS: Successfully updated pet status
 * 
 * Authentication error codes:
 * - TOKEN_NOT_PROVIDED: Token was not provided
 * - INVALID_TOKEN: Invalid token
 * - JWT_CONFIG_MISSING: JWT configuration is missing
 * 
 * Pet error codes:
 * - PET_NOT_FOUND: Pet not found
 * - PET_ACCESS_DENIED: Access denied to this pet
 * - INVALID_PET_DATA: Invalid pet data provided
 * - PET_CREATE_FAILED: Failed to create pet
 * - PET_UPDATE_FAILED: Failed to update pet
 * - PET_DELETE_FAILED: Failed to delete pet
 * 
 * System error codes:
 * - SYSTEM_ERROR: System error
 * - DATABASE_ERROR: Database error
 */
class MyPetsController extends BaseController {
    /** @var PDO Database connection instance */
    private $db;
    private $request;

    public function __construct($db) {
        $this->db = $db;
        $this->request = Flight::request();
        Logger::info("MyPetsController initialized", "MyPetsController");
    }

    /**
     * Get user's pets with their photos
     * Returns pets with main_photos array containing filenames and URLs
     */
    public function getMyPets() {
        Logger::info("getMyPets called", "MyPetsController");

        try {
            // Validate JWT configuration
            if (!isset($_ENV['JWT_SECRET'])) {
                Logger::error("JWT_SECRET not set", "MyPetsController");
                return Flight::json([
                    'status' => 500,
                    'error_code' => 'JWT_CONFIG_MISSING',
                    'data' => null
                ], 500);
            }

            // Get token from cookies (for frontend) or URL parameter (for testing)
            $token = (isset($_COOKIE['auth_token']) ? $_COOKIE['auth_token'] : null) ?? $_GET['token'] ?? null;
            Logger::info("Token from cookies: " . (isset($_COOKIE['auth_token']) ? "exists" : "missing"), "MyPetsController");
            Logger::info("Token from URL: " . ($_GET['token'] ?? "missing"), "MyPetsController");
            Logger::info("Final token: " . ($token ? "exists" : "missing"), "MyPetsController");

            if (!$token) {
                Logger::error("No token provided", "MyPetsController");
                return Flight::json([
                    'status' => 401,
                    'error_code' => 'TOKEN_NOT_PROVIDED',
                    'data' => null
                ], 401);
            }

            // Decode and validate token
            try {
                $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
                Logger::info("Decoded token: " . json_encode($decoded), "MyPetsController");
                
                if (!isset($decoded->user_id)) {
                    Logger::error("Invalid token structure", "MyPetsController");
                    return Flight::json([
                        'status' => 401,
                        'error_code' => 'INVALID_TOKEN',
                        'data' => null
                    ], 401);
                }

                $userId = $decoded->user_id;

                // Get user's pets from database with all fields
                $stmt = $this->db->prepare("
                    SELECT 
                        p.id,
                        p.ownerId,
                        p.name,
                        p.gender,
                        p.dob,
                        p.species,
                        p.breed,
                        p.color,
                        p.pet_size,
                        p.description,
                        p.published,
                        p.created
                    FROM pets p
                    WHERE p.ownerId = :user_id
                    ORDER BY p.created DESC
                ");
                
                $stmt->execute(['user_id' => $userId]);
                $pets = $stmt->fetchAll(PDO::FETCH_ASSOC);

                Logger::info("Retrieved " . count($pets) . " pets for user " . $userId, "MyPetsController");

                // Добавляем фотографии для каждого питомца
                $petsWithPhotos = [];
                $basePhotosPath = __DIR__ . '/../../public/profile-images/pet-photos/user-' . $userId . '/';
                
                foreach ($pets as $pet) {
                    $petPhotosPath = $basePhotosPath . 'pet-' . $pet['id'] . '/';
                    $mainPhotos = [];
                    
                    // Ищем все фото питомца
                    if (is_dir($petPhotosPath)) {
                        $photoFiles = glob($petPhotosPath . 'pet_*.*');
                        
                        foreach ($photoFiles as $photoFile) {
                            $filename = basename($photoFile);
                            $mainPhotos[] = [
                                'filename' => $filename,
                                'url' => "/profile-images/pet-photos/user-{$userId}/pet-{$pet['id']}/{$filename}"
                            ];
                        }
                        
                        Logger::info("Found " . count($photoFiles) . " photos for pet " . $pet['id'], "MyPetsController");
                    }
                    
                    // Добавляем массив фотографий к данным питомца
                    $pet['main_photos'] = $mainPhotos;
                    $petsWithPhotos[] = $pet;
                }

                Logger::info("Prepared pets data with photos", "MyPetsController", [
                    'total_pets' => count($petsWithPhotos),
                    'user_id' => $userId
                ]);

                return Flight::json([
                    'status' => 200,
                    'error_code' => null,
                    'data' => [
                        'pets' => $petsWithPhotos
                    ]
                ], 200);

            } catch (\Exception $e) {
                Logger::error("Token decode error: " . $e->getMessage(), "MyPetsController");
                return Flight::json([
                    'status' => 401,
                    'error_code' => 'INVALID_TOKEN',
                    'data' => null
                ], 401);
            }

        } catch (\Exception $e) {
            Logger::error("getMyPets error: " . $e->getMessage(), "MyPetsController");
            return Flight::json([
                'status' => 500,
                'error_code' => 'SYSTEM_ERROR',
                'data' => null
            ], 500);
        }
    }

    /**
     * Create a new pet
     * 
     * @return void JSON response with created pet data
     * 
     * @api {post} /api/pets Create new pet
     * @apiHeader {String} Cookie session_id=... Session cookie
     * @apiHeader {String} Content-Type application/json
     * 
     * @apiParam {String} name Pet name (required)
     * @apiParam {String} species Pet species (required)
     * @apiParam {String} [breed] Pet breed
     * @apiParam {String} [birth_date] Pet birth date (YYYY-MM-DD)
     * @apiParam {Number} [weight] Pet weight
     * @apiParam {String} [status] Pet status (default: active)
     * 
     * @apiSuccess {Object} pet Created pet data
     * 
     * @apiError {String} error_code Error code (TOKEN_NOT_PROVIDED, INVALID_PET_DATA, etc.)
     */
    public function createPet() {
        Logger::info("createPet called", "MyPetsController");

        try {
            // Validate JWT configuration
            if (!isset($_ENV['JWT_SECRET'])) {
                Logger::error("JWT_SECRET not set", "MyPetsController");
                return Flight::json([
                    'status' => 500,
                    'error_code' => 'JWT_CONFIG_MISSING',
                    'data' => null
                ], 500);
            }

            // Get token from cookies (for frontend) or URL parameter (for testing)
            $token = (isset($_COOKIE['auth_token']) ? $_COOKIE['auth_token'] : null) ?? $_GET['token'] ?? null;
            if (!$token) {
                Logger::error("No token provided", "MyPetsController");
                return Flight::json([
                    'status' => 401,
                    'error_code' => 'TOKEN_NOT_PROVIDED',
                    'data' => null
                ], 401);
            }

            // Decode and validate token
            try {
                $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
                if (!isset($decoded->user_id)) {
                    return Flight::json([
                        'status' => 401,
                        'error_code' => 'INVALID_TOKEN',
                        'data' => null
                    ], 401);
                }

                $userId = $decoded->user_id;

                // Get request data
                $data = json_decode($this->request->getBody(), true);
                
                // Validate required fields
                if (!isset($data['name']) || !isset($data['species'])) {
                    Logger::error("Missing required fields", "MyPetsController");
                    return Flight::json([
                        'status' => 400,
                        'error_code' => 'INVALID_PET_DATA',
                        'data' => null
                    ], 400);
                }

                // Validate data
                if (empty(trim($data['name'])) || empty(trim($data['species']))) {
                    Logger::error("Empty required fields", "MyPetsController");
                    return Flight::json([
                        'status' => 400,
                        'error_code' => 'INVALID_PET_DATA',
                        'data' => null
                    ], 400);
                }

                // Prepare pet data
                $petData = [
                    'ownerId' => $userId,
                    'name' => trim($data['name']),
                    'gender' => isset($data['gender']) ? $data['gender'] : null,
                    'dob' => isset($data['dob']) ? $data['dob'] : null,
                    'species' => trim($data['species']),
                    'breed' => isset($data['breed']) ? trim($data['breed']) : null,
                    'color' => isset($data['color']) ? trim($data['color']) : null,
                    'description' => isset($data['description']) ? trim($data['description']) : null,
                    'pet_size' => isset($data['pet_size']) ? trim($data['pet_size']) : null
                ];

                // Insert pet into database
                $stmt = $this->db->prepare("
                    INSERT INTO pets (ownerId, name, gender, dob, species, breed, color, description, pet_size)
                    VALUES (:ownerId, :name, :gender, :dob, :species, :breed, :color, :description, :pet_size)
                ");
                
                $stmt->execute($petData);
                $petId = $this->db->lastInsertId();

                // Get created pet data
                $stmt = $this->db->prepare("
                    SELECT 
                        id, name, gender, dob, species, breed, color, description, published, pet_size, created
                    FROM pets 
                    WHERE id = :id
                ");
                $stmt->execute(['id' => $petId]);
                $pet = $stmt->fetch(PDO::FETCH_ASSOC);

                Logger::info("Created pet with ID: " . $petId, "MyPetsController");

                return Flight::json([
                    'status' => 200,
                    'error_code' => 'PET_CREATE_SUCCESS',
                    'data' => ['pet' => $pet]
                ], 200);

            } catch (\Exception $e) {
                Logger::error("Token decode error: " . $e->getMessage(), "MyPetsController");
                return Flight::json([
                    'status' => 401,
                    'error_code' => 'INVALID_TOKEN',
                    'data' => null
                ], 401);
            }

        } catch (\Exception $e) {
            Logger::error("createPet error: " . $e->getMessage(), "MyPetsController");
            return Flight::json([
                'status' => 500,
                'error_code' => 'SYSTEM_ERROR',
                'data' => null
            ], 500);
        }
    }

    /**
     * Update pet data
     * 
     * @param int $petId Pet ID
     * @return void JSON response with updated pet data
     * 
     * @api {put} /api/pets/:id Update pet
     * @apiHeader {String} Cookie session_id=... Session cookie
     * @apiHeader {String} Content-Type application/json
     * @apiParam {Number} id Pet ID
     * 
     * @apiParam {String} [name] Pet name
     * @apiParam {String} [species] Pet species
     * @apiParam {String} [breed] Pet breed
     * @apiParam {String} [birth_date] Pet birth date (YYYY-MM-DD)
     * @apiParam {Number} [weight] Pet weight
     * @apiParam {String} [status] Pet status
     * 
     * @apiSuccess {Object} pet Updated pet data
     * 
     * @apiError {String} error_code Error code (PET_NOT_FOUND, PET_ACCESS_DENIED, etc.)
     */
    public function updatePet($petId) {
        Logger::info("updatePet called for pet ID: " . $petId, "MyPetsController");

        try {
            // Validate JWT configuration
            if (!isset($_ENV['JWT_SECRET'])) {
                Logger::error("JWT_SECRET not set", "MyPetsController");
                return Flight::json([
                    'status' => 500,
                    'error_code' => 'JWT_CONFIG_MISSING',
                    'data' => null
                ], 500);
            }

            // Get token from cookies (for frontend) or URL parameter (for testing)
            $token = (isset($_COOKIE['auth_token']) ? $_COOKIE['auth_token'] : null) ?? $_GET['token'] ?? null;
            if (!$token) {
                Logger::error("No token provided", "MyPetsController");
                return Flight::json([
                    'status' => 401,
                    'error_code' => 'TOKEN_NOT_PROVIDED',
                    'data' => null
                ], 401);
            }

            // Decode and validate token
            try {
                $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
                if (!isset($decoded->user_id)) {
                    return Flight::json([
                        'status' => 401,
                        'error_code' => 'INVALID_TOKEN',
                        'data' => null
                    ], 401);
                }

                $userId = $decoded->user_id;

                // Check if pet exists and belongs to user
                $stmt = $this->db->prepare("
                    SELECT id, ownerId FROM pets WHERE id = :pet_id
                ");
                $stmt->execute(['pet_id' => $petId]);
                $pet = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$pet) {
                    Logger::error("Pet not found: " . $petId, "MyPetsController");
                    return Flight::json([
                        'status' => 404,
                        'error_code' => 'PET_NOT_FOUND',
                        'data' => null
                    ], 404);
                }

                if ($pet['ownerId'] != $userId) {
                    Logger::error("Access denied to pet: " . $petId, "MyPetsController");
                    return Flight::json([
                        'status' => 403,
                        'error_code' => 'PET_ACCESS_DENIED',
                        'data' => null
                    ], 403);
                }

                // Get request data
                $data = json_decode($this->request->getBody(), true);
                
                if (!$data) {
                    Logger::error("Invalid JSON data", "MyPetsController");
                    return Flight::json([
                        'status' => 400,
                        'error_code' => 'INVALID_PET_DATA',
                        'data' => null
                    ], 400);
                }

                // Prepare update data
                $updateFields = [];
                $updateParams = ['pet_id' => $petId];

                $allowedFields = ['name', 'gender', 'dob', 'species', 'breed', 'color', 'description', 'pet_size'];
                foreach ($allowedFields as $field) {
                    if (isset($data[$field])) {
                        $updateFields[] = "$field = :$field";
                        $updateParams[$field] = $data[$field];
                    }
                }

                if (empty($updateFields)) {
                    Logger::error("No fields to update", "MyPetsController");
                    return Flight::json([
                        'status' => 400,
                        'error_code' => 'INVALID_PET_DATA',
                        'data' => null
                    ], 400);
                }

                // Update pet
                $sql = "UPDATE pets SET " . implode(', ', $updateFields) . " WHERE id = :pet_id";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($updateParams);

                // Get updated pet data
                $stmt = $this->db->prepare("
                    SELECT 
                        id, name, gender, dob, species, breed, color, description, pet_size, created
                    FROM pets 
                    WHERE id = :pet_id
                ");
                $stmt->execute(['pet_id' => $petId]);
                $updatedPet = $stmt->fetch(PDO::FETCH_ASSOC);

                Logger::info("Updated pet with ID: " . $petId, "MyPetsController");

                return Flight::json([
                    'status' => 200,
                    'error_code' => 'PET_UPDATE_SUCCESS',
                    'data' => ['pet' => $updatedPet]
                ], 200);

            } catch (\Exception $e) {
                Logger::error("Token decode error: " . $e->getMessage(), "MyPetsController");
                return Flight::json([
                    'status' => 401,
                    'error_code' => 'INVALID_TOKEN',
                    'data' => null
                ], 401);
            }

        } catch (\Exception $e) {
            Logger::error("updatePet error: " . $e->getMessage(), "MyPetsController");
            return Flight::json([
                'status' => 500,
                'error_code' => 'SYSTEM_ERROR',
                'data' => null
            ], 500);
        }
    }

    /**
     * Delete pet
     * 
     * @param int $petId Pet ID
     * @return void JSON response
     * 
     * @api {delete} /api/pets/:id Delete pet
     * @apiHeader {String} Cookie session_id=... Session cookie
     * @apiParam {Number} id Pet ID
     * 
     * @apiSuccess {String} message Success message
     * 
     * @apiError {String} error_code Error code (PET_NOT_FOUND, PET_ACCESS_DENIED, etc.)
     */
    public function deletePet($petId) {
        Logger::info("deletePet called for pet ID: " . $petId, "MyPetsController");

        try {
            // Validate JWT configuration
            if (!isset($_ENV['JWT_SECRET'])) {
                Logger::error("JWT_SECRET not set", "MyPetsController");
                return Flight::json([
                    'status' => 500,
                    'error_code' => 'JWT_CONFIG_MISSING',
                    'data' => null
                ], 500);
            }

            // Get token from cookies (for frontend) or URL parameter (for testing)
            $token = (isset($_COOKIE['auth_token']) ? $_COOKIE['auth_token'] : null) ?? $_GET['token'] ?? null;
            if (!$token) {
                Logger::error("No token provided", "MyPetsController");
                return Flight::json([
                    'status' => 401,
                    'error_code' => 'TOKEN_NOT_PROVIDED',
                    'data' => null
                ], 401);
            }

            // Decode and validate token
            try {
                $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
                if (!isset($decoded->user_id)) {
                    return Flight::json([
                        'status' => 401,
                        'error_code' => 'INVALID_TOKEN',
                        'data' => null
                    ], 401);
                }

                $userId = $decoded->user_id;

                // Check if pet exists and belongs to user
                $stmt = $this->db->prepare("
                    SELECT id, ownerId FROM pets WHERE id = :pet_id
                ");
                $stmt->execute(['pet_id' => $petId]);
                $pet = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$pet) {
                    Logger::error("Pet not found: " . $petId, "MyPetsController");
                    return Flight::json([
                        'status' => 404,
                        'error_code' => 'PET_NOT_FOUND',
                        'data' => null
                    ], 404);
                }

                if ($pet['ownerId'] != $userId) {
                    Logger::error("Access denied to pet: " . $petId, "MyPetsController");
                    return Flight::json([
                        'status' => 403,
                        'error_code' => 'PET_ACCESS_DENIED',
                        'data' => null
                    ], 403);
                }

                // Delete pet
                $stmt = $this->db->prepare("DELETE FROM pets WHERE id = :pet_id");
                $stmt->execute(['pet_id' => $petId]);

                Logger::info("Deleted pet with ID: " . $petId, "MyPetsController");

                return Flight::json([
                    'status' => 200,
                    'error_code' => 'PET_DELETE_SUCCESS',
                    'data' => ['message' => 'Pet deleted successfully']
                ], 200);

            } catch (\Exception $e) {
                Logger::error("Token decode error: " . $e->getMessage(), "MyPetsController");
                return Flight::json([
                    'status' => 401,
                    'error_code' => 'INVALID_TOKEN',
                    'data' => null
                ], 401);
            }

        } catch (\Exception $e) {
            Logger::error("deletePet error: " . $e->getMessage(), "MyPetsController");
            return Flight::json([
                'status' => 500,
                'error_code' => 'SYSTEM_ERROR',
                'data' => null
            ], 500);
        }
    }

    /**
     * Update pet status
     * 
     * @param int $petId Pet ID
     * @return void JSON response with updated pet data
     * 
     * @api {patch} /api/pets/:id/status Update pet status
     * @apiHeader {String} Cookie session_id=... Session cookie
     * @apiHeader {String} Content-Type application/json
     * @apiParam {Number} id Pet ID
     * 
     * @apiParam {String} status New pet status (active, inactive, lost, found, etc.)
     * 
     * @apiSuccess {Object} pet Updated pet data
     * 
     * @apiError {String} error_code Error code (PET_NOT_FOUND, PET_ACCESS_DENIED, etc.)
     */
    public function updatePetStatus($petId) {
        Logger::info("updatePetStatus called for pet ID: " . $petId, "MyPetsController");

        try {
            // Validate JWT configuration
            if (!isset($_ENV['JWT_SECRET'])) {
                Logger::error("JWT_SECRET not set", "MyPetsController");
                return Flight::json([
                    'status' => 500,
                    'error_code' => 'JWT_CONFIG_MISSING',
                    'data' => null
                ], 500);
            }

            // Get token from cookies (for frontend) or URL parameter (for testing)
            $token = (isset($_COOKIE['auth_token']) ? $_COOKIE['auth_token'] : null) ?? $_GET['token'] ?? null;
            if (!$token) {
                Logger::error("No token provided", "MyPetsController");
                return Flight::json([
                    'status' => 401,
                    'error_code' => 'TOKEN_NOT_PROVIDED',
                    'data' => null
                ], 401);
            }

            // Decode and validate token
            try {
                $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
                if (!isset($decoded->user_id)) {
                    return Flight::json([
                        'status' => 401,
                        'error_code' => 'INVALID_TOKEN',
                        'data' => null
                    ], 401);
                }

                $userId = $decoded->user_id;

                // Check if pet exists and belongs to user
                $stmt = $this->db->prepare("
                    SELECT id, ownerId FROM pets WHERE id = :pet_id
                ");
                $stmt->execute(['pet_id' => $petId]);
                $pet = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$pet) {
                    Logger::error("Pet not found: " . $petId, "MyPetsController");
                    return Flight::json([
                        'status' => 404,
                        'error_code' => 'PET_NOT_FOUND',
                        'data' => null
                    ], 404);
                }

                if ($pet['ownerId'] != $userId) {
                    Logger::error("Access denied to pet: " . $petId, "MyPetsController");
                    return Flight::json([
                        'status' => 403,
                        'error_code' => 'PET_ACCESS_DENIED',
                        'data' => null
                    ], 403);
                }

                // Get request data
                $data = json_decode($this->request->getBody(), true);
                
                if (!isset($data['published'])) {
                    Logger::error("Published status not provided", "MyPetsController");
                    return Flight::json([
                        'status' => 400,
                        'error_code' => 'INVALID_PET_DATA',
                        'data' => null
                    ], 400);
                }

                $newPublished = (int)$data['published'];
                if (!in_array($newPublished, [0, 1])) {
                    Logger::error("Invalid published value", "MyPetsController");
                    return Flight::json([
                        'status' => 400,
                        'error_code' => 'INVALID_PET_DATA',
                        'data' => null
                    ], 400);
                }

                // Update pet published status
                $stmt = $this->db->prepare("
                    UPDATE pets 
                    SET published = :published 
                    WHERE id = :pet_id
                ");
                $stmt->execute([
                    'published' => $newPublished,
                    'pet_id' => $petId
                ]);

                // Get updated pet data
                $stmt = $this->db->prepare("
                    SELECT 
                        id, name, gender, dob, species, breed, color, description, published, pet_size, created
                    FROM pets 
                    WHERE id = :pet_id
                ");
                $stmt->execute(['pet_id' => $petId]);
                $updatedPet = $stmt->fetch(PDO::FETCH_ASSOC);

                Logger::info("Updated published status for pet ID: " . $petId . " to: " . $newPublished, "MyPetsController");

                return Flight::json([
                    'status' => 200,
                    'error_code' => 'PET_STATUS_UPDATE_SUCCESS',
                    'data' => ['pet' => $updatedPet]
                ], 200);

            } catch (\Exception $e) {
                Logger::error("Token decode error: " . $e->getMessage(), "MyPetsController");
                return Flight::json([
                    'status' => 401,
                    'error_code' => 'INVALID_TOKEN',
                    'data' => null
                ], 401);
            }

        } catch (\Exception $e) {
            Logger::error("updatePetStatus error: " . $e->getMessage(), "MyPetsController");
            return Flight::json([
                'status' => 500,
                'error_code' => 'SYSTEM_ERROR',
                'data' => null
            ], 500);
        }
    }

    /**
     * Upload pet photo
     * Expects: multipart/form-data with 'photo' file and 'pet_id' field
     * pet_id = 0 - create new pet, pet_id > 0 - update existing pet photo
     */
    public function uploadPetPhoto() {
        try {
            // Получаем токен из cookie
            $token = $_COOKIE['auth_token'] ?? null;
            
            if (!$token) {
                return Flight::json([
                    'status' => 401,
                    'error_code' => 'MISSING_TOKEN',
                    'data' => null
                ], 401);
            }

            Logger::info("Token from cookie: " . $token, "MyPetsController");
            
            try {
                $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
                $userId = $decoded->user_id;
                $userRole = $decoded->role;
            } catch (\Exception $e) {
                Logger::error("Token decode error: " . $e->getMessage(), "MyPetsController");
                return Flight::json([
                    'status' => 401,
                    'error_code' => 'INVALID_TOKEN',
                    'data' => null
                ], 401);
            }

            Logger::info("User data from token", "MyPetsController", [
                'user_id' => $userId,
                'user_role' => $userRole
            ]);

            // Получаем данные из тела запроса
            // Ожидаемая структура: { petId: 229, filename: null, fileInfo: {...} }
            $requestData = json_decode(file_get_contents('php://input'), true);
            
            // Если это не JSON, пробуем получить из form-data
            if (!$requestData) {
                $petIdRaw = Flight::request()->data->petId ?? Flight::request()->data->pet_id ?? null;
            } else {
                $petIdRaw = $requestData['petId'] ?? null;
            }
            
            // Логируем полученные данные
            Logger::info("Request data received", "MyPetsController", [
                'request_data' => $requestData,
                'pet_id_raw' => $petIdRaw,
                'files_count' => count($_FILES)
            ]);
            
            // Преобразуем в int, но учитываем null
            if ($petIdRaw === null || $petIdRaw === '' || $petIdRaw === '0' || $petIdRaw === 0) {
                $petId = 0; // Новый питомец
            } else {
                $petId = (int)$petIdRaw;
                if ($petId <= 0) {
                    return Flight::json([
                        'status' => 400,
                        'error_code' => 'INVALID_PET_ID',
                        'data' => null
                    ], 400);
                }
            }

            Logger::info("Pet ID processed", "MyPetsController", [
                'pet_id_raw' => $petIdRaw,
                'pet_id_processed' => $petId,
                'is_new_pet' => $petId === 0
            ]);

            // Если pet_id = 0, создаем нового питомца
            if ($petId === 0) {
                Logger::info("Creating new pet", "MyPetsController", ['user_id' => $userId]);
                
                $stmt = $this->db->prepare("
                    INSERT INTO pets (ownerId, name, gender, dob, species, breed, color, pet_size, description, published, created) 
                    VALUES (?, '', NULL, NULL, '', '', '', '', '', 0, NOW())
                ");
                $stmt->execute([$userId]);
                $petId = $this->db->lastInsertId();
                
                Logger::info("New pet created", "MyPetsController", [
                    'new_pet_id' => $petId,
                    'owner_id' => $userId
                ]);
            } else {
                // Проверяем, что питомец существует и принадлежит пользователю
                $stmt = $this->db->prepare("
                    SELECT id FROM pets 
                    WHERE id = ? AND ownerId = ?
                ");
                $stmt->execute([$petId, $userId]);
                $pet = $stmt->fetch();

                if (!$pet) {
                    return Flight::json([
                        'status' => 404,
                        'error_code' => 'PET_NOT_FOUND',
                        'data' => null
                    ], 404);
                }
                
                Logger::info("Existing pet found", "MyPetsController", [
                    'pet_id' => $petId,
                    'owner_id' => $userId
                ]);
            }

            // Проверяем загруженный файл
            $file = $_FILES['photo'] ?? null;
            if (!$file) {
                Logger::error("No file uploaded", "MyPetsController");
                return Flight::json([
                    'status' => 400,
                    'error_code' => 'NO_FILE_UPLOADED',
                    'data' => null
                ], 400);
            }

            // Проверяем тип файла
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($extension, $allowedTypes)) {
                Logger::error("Invalid file type: " . $extension, "MyPetsController");
                return Flight::json([
                    'status' => 400,
                    'error_code' => 'INVALID_FILE_TYPE',
                    'data' => null
                ], 400);
            }

            // Проверяем размер файла (максимум 5MB)
            $maxSize = 5 * 1024 * 1024; // 5MB
            if ($file['size'] > $maxSize) {
                return Flight::json([
                    'status' => 400,
                    'error_code' => 'FILE_TOO_LARGE',
                    'data' => null
                ], 400);
            }

            // Вычисляем хеш файла сразу после проверок
            $fileHash = md5_file($file['tmp_name']);
            Logger::info("File hash calculated", "MyPetsController", [
                'file_hash' => $fileHash,
                'file_size' => $file['size']
            ]);

            // Получаем filename из запроса
            $filename = null;
            if (!$requestData) {
                $filename = Flight::request()->data->filename ?? null;
            } else {
                $filename = $requestData['filename'] ?? null;
            }

            // Подробное логирование для диагностики
            Logger::info("Detailed filename processing", "MyPetsController", [
                'filename_from_request' => $filename,
                'filename_is_null' => $filename === null,
                'filename_is_empty_string' => $filename === '',
                'filename_type' => gettype($filename),
                'raw_request_data' => $requestData,
                'raw_flight_data' => Flight::request()->data->getData() ?? 'NO_DATA'
            ]);

            // Нормализуем filename - пустая строка тоже считается как null
            if ($filename === '' || $filename === null) {
                $filename = null;
            }

            Logger::info("Filename processing result", "MyPetsController", [
                'final_filename' => $filename,
                'operation_type' => $filename === null ? 'CREATE_NEW_FILE' : 'UPDATE_EXISTING_FILE'
            ]);

            // Создаем структуру директорий: /public/profile-images/pet-photos/user-{ID}/pet-{ID}/
            $baseDir = __DIR__ . '/../../public/profile-images/pet-photos/';
            $userDir = $baseDir . "user-{$userId}/";
            $petDir = $userDir . "pet-{$petId}/";
            
            // Создаем все необходимые директории
            if (!is_dir($baseDir)) {
                mkdir($baseDir, 0777, true);
            }
            if (!is_dir($userDir)) {
                mkdir($userDir, 0777, true);
            }
            if (!is_dir($petDir)) {
                mkdir($petDir, 0777, true);
            }
            
            // Устанавливаем права доступа
            chmod($baseDir, 0777);
            chmod($userDir, 0777);
            chmod($petDir, 0777);

            Logger::info("Directory structure created", "MyPetsController", [
                'base_dir' => $baseDir,
                'user_dir' => $userDir,
                'pet_dir' => $petDir,
                'pet_dir_writable' => is_writable($petDir)
            ]);

            // Проверяем права на запись
            if (!is_writable($petDir)) {
                Logger::error("Pet directory is not writable", "MyPetsController");
                return Flight::json([
                    'status' => 500,
                    'error_code' => 'UPLOAD_DIR_NOT_WRITABLE',
                    'data' => null
                ], 500);
            }

            // Определяем имя файла в зависимости от логики
            $finalFilename = '';
            $isNewFile = false;
            $isUpdateFile = false;

            if ($filename === null) {
                // Новый файл - проверяем лимит и генерируем уникальное имя
                $existingPhotos = glob($petDir . "pet_*.*");
                
                // Тихо возвращаемся если уже 4 фото (клиент не должен такое отправлять)
                if (count($existingPhotos) >= 4) {
                    Logger::info("Max photos reached - silent return", "MyPetsController", [
                        'pet_id' => $petId,
                        'existing_photos_count' => count($existingPhotos),
                        'max_allowed' => 4
                    ]);
                    
                    // Возвращаем "успех" но с информацией о лимите
                    return Flight::json([
                        'status' => 200,
                        'error_code' => null,
                        'data' => [
                            'message' => 'Maximum photos limit reached',
                            'pet_id' => $petId,
                            'total_photos' => count($existingPhotos),
                            'max_photos' => 4,
                            'operation_skipped' => true
                        ]
                    ], 200);
                }
                
                $uniqueId = uniqid('', true);
                $finalFilename = "pet_{$uniqueId}.{$extension}";
                $isNewFile = true;
                
                Logger::info("Creating new photo file with UUID", "MyPetsController", [
                    'existing_photos_count' => count($existingPhotos),
                    'unique_id' => $uniqueId,
                    'filename' => $finalFilename
                ]);
                
            } else {
                // Обновление существующего файла - БЕЗ проверки лимита
                $finalFilename = $filename;
                $isUpdateFile = true;
                
                Logger::info("Updating existing photo file", "MyPetsController", [
                    'filename' => $finalFilename,
                    'file_exists' => file_exists($petDir . $finalFilename),
                    'file_hash' => $fileHash,
                    'operation' => 'UPDATE_EXISTING_FILE'
                ]);
                
                // Тихо возвращаемся если файл не существует (клиент не должен такое отправлять)
                if (!file_exists($petDir . $finalFilename)) {
                    Logger::info("Photo file not found - silent return", "MyPetsController", [
                        'pet_id' => $petId,
                        'filename' => $finalFilename,
                        'file_path' => $petDir . $finalFilename
                    ]);
                    
                    // Возвращаем "успех" но с информацией об отсутствии файла
                    return Flight::json([
                        'status' => 200,
                        'error_code' => null,
                        'data' => [
                            'message' => 'Photo file not found',
                            'pet_id' => $petId,
                            'filename' => $finalFilename,
                            'operation_skipped' => true
                        ]
                    ], 200);
                }
            }

            $filePath = $petDir . $finalFilename;

            Logger::info("Final photo file details", "MyPetsController", [
                'final_filename' => $finalFilename,
                'file_path' => $filePath,
                'is_new_file' => $isNewFile,
                'is_update_file' => $isUpdateFile,
                'operation_type' => $isNewFile ? 'CREATE_NEW' : 'UPDATE_EXISTING'
            ]);

            // Загружаем файл
            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                // Формируем относительный путь для ответа (БД не обновляем - пути не храним)
                $photoPath = "/profile-images/pet-photos/user-{$userId}/pet-{$petId}/{$finalFilename}";

                // Подсчитываем общее количество фото после операции
                $totalPhotos = count(glob($petDir . "pet_*.*"));

                Logger::info("Pet photo uploaded successfully", "MyPetsController", [
                    'pet_id' => $petId,
                    'owner_id' => $userId,
                    'filename' => $finalFilename,
                    'file_size' => filesize($filePath),
                    'operation_type' => $isNewFile ? 'NEW_FILE' : 'UPDATE_FILE',
                    'total_photos' => $totalPhotos
                ]);

                $timestamp = time();
                return Flight::json([
                    'status' => 200,
                    'error_code' => null,
                    'data' => [
                        'message' => $isNewFile ? 'New photo uploaded successfully' : 'Photo updated successfully',
                        'pet_id' => $petId,
                        'photo_path' => $photoPath . '?t=' . $timestamp,
                        'filename' => $finalFilename,
                        'is_new_pet' => $petId !== (int)$petIdRaw,
                        'operation_type' => $isNewFile ? 'new_file' : 'update_file',
                        'total_photos' => $totalPhotos,
                        'max_photos' => 4
                    ]
                ], 200);

            } else {
                Logger::error("Failed to move uploaded file", "MyPetsController", [
                    'tmp_name' => $file['tmp_name'],
                    'target_path' => $filePath,
                    'error' => error_get_last()
                ]);
                return Flight::json([
                    'status' => 500,
                    'error_code' => 'UPLOAD_FAILED',
                    'data' => null
                ], 500);
            }

        } catch (\Exception $e) {
            Logger::error("uploadPetPhoto error: " . $e->getMessage(), "MyPetsController");
            return Flight::json([
                'status' => 500,
                'error_code' => 'SYSTEM_ERROR',
                'data' => null
            ], 500);
        }
    }

    /**
     * Delete pet photos
     * 
     * @return void JSON response
     * 
     * @api {post} /api/pets/photo/delete Delete pet photos
     * @apiHeader {String} Cookie auth_token=... Authentication token
     * @apiHeader {String} Content-Type application/json
     * 
     * @apiParam {Number} pet_id Pet ID (required)
     * @apiParam {String} filename Filename to delete (required)
     * 
     * @apiSuccess {String} message Success message
     * @apiSuccess {String} filename Deleted filename
     * 
     * @apiError {String} error_code Error code (TOKEN_NOT_PROVIDED, INVALID_TOKEN, PET_NOT_FOUND, etc.)
     */
    public function deletePetPhotos() {
        Logger::info("deletePetPhotos called", "MyPetsController");

        try {
            // Validate JWT configuration
            if (!isset($_ENV['JWT_SECRET'])) {
                Logger::error("JWT_SECRET not set", "MyPetsController");
                return Flight::json([
                    'status' => 500,
                    'error_code' => 'JWT_CONFIG_MISSING',
                    'data' => null
                ], 500);
            }

            // Get token from cookies (for frontend) or URL parameter (for testing)
            $token = (isset($_COOKIE['auth_token']) ? $_COOKIE['auth_token'] : null) ?? $_GET['token'] ?? null;
            if (!$token) {
                Logger::error("No token provided", "MyPetsController");
                return Flight::json([
                    'status' => 401,
                    'error_code' => 'TOKEN_NOT_PROVIDED',
                    'data' => null
                ], 401);
            }

            // Decode and validate token
            try {
                $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
                if (!isset($decoded->user_id)) {
                    return Flight::json([
                        'status' => 401,
                        'error_code' => 'INVALID_TOKEN',
                        'data' => null
                    ], 401);
                }

                $userId = $decoded->user_id;

                // Get request data
                $data = json_decode($this->request->getBody(), true);
                
                if (!$data) {
                    Logger::error("Invalid JSON data", "MyPetsController");
                    return Flight::json([
                        'status' => 400,
                        'error_code' => 'INVALID_REQUEST_DATA',
                        'data' => null
                    ], 400);
                }

                // Validate required fields
                if (!isset($data['pet_id']) || !isset($data['filename'])) {
                    Logger::error("Missing required fields", "MyPetsController");
                    return Flight::json([
                        'status' => 400,
                        'error_code' => 'MISSING_REQUIRED_FIELDS',
                        'data' => null
                    ], 400);
                }

                $petId = (int)$data['pet_id'];
                $filename = $data['filename'];

                if ($petId <= 0) {
                    Logger::error("Invalid pet ID: " . $petId, "MyPetsController");
                    return Flight::json([
                        'status' => 400,
                        'error_code' => 'INVALID_PET_ID',
                        'data' => null
                    ], 400);
                }

                if (empty($filename) || !is_string($filename)) {
                    Logger::error("Invalid filename", "MyPetsController");
                    return Flight::json([
                        'status' => 400,
                        'error_code' => 'INVALID_FILENAME',
                        'data' => null
                    ], 400);
                }

                // Check if pet exists and belongs to user
                $stmt = $this->db->prepare("
                    SELECT id, ownerId FROM pets WHERE id = :pet_id
                ");
                $stmt->execute(['pet_id' => $petId]);
                $pet = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$pet) {
                    Logger::error("Pet not found: " . $petId, "MyPetsController");
                    return Flight::json([
                        'status' => 404,
                        'error_code' => 'PET_NOT_FOUND',
                        'data' => null
                    ], 404);
                }

                if ($pet['ownerId'] != $userId) {
                    Logger::error("Access denied to pet: " . $petId, "MyPetsController");
                    return Flight::json([
                        'status' => 403,
                        'error_code' => 'PET_ACCESS_DENIED',
                        'data' => null
                    ], 403);
                }

                // Define pet photos directory
                $petDir = __DIR__ . "/../../public/profile-images/pet-photos/user-{$userId}/pet-{$petId}/";
                
                if (!is_dir($petDir)) {
                    Logger::error("Pet photos directory not found: " . $petDir, "MyPetsController");
                    return Flight::json([
                        'status' => 404,
                        'error_code' => 'PHOTOS_DIR_NOT_FOUND',
                        'data' => null
                    ], 404);
                }

                // Validate filename format (should start with 'pet_' and have valid extension)
                if (!preg_match('/^pet_[a-zA-Z0-9_.]+\.(jpg|jpeg|png|gif|webp)$/i', $filename)) {
                    Logger::error("Invalid filename format: " . $filename, "MyPetsController");
                    return Flight::json([
                        'status' => 400,
                        'error_code' => 'INVALID_FILENAME_FORMAT',
                        'data' => null
                    ], 400);
                }

                $filePath = $petDir . $filename;

                // Check if file exists
                if (!file_exists($filePath)) {
                    Logger::error("File not found: " . $filename, "MyPetsController");
                    return Flight::json([
                        'status' => 404,
                        'error_code' => 'FILE_NOT_FOUND',
                        'data' => null
                    ], 404);
                }

                // Delete file
                if (unlink($filePath)) {
                    Logger::info("Deleted pet photo: " . $filename, "MyPetsController", [
                        'pet_id' => $petId,
                        'user_id' => $userId,
                        'filename' => $filename
                    ]);
                } else {
                    Logger::error("Failed to delete pet photo: " . $filename, "MyPetsController", [
                        'pet_id' => $petId,
                        'user_id' => $userId,
                        'file_path' => $filePath
                    ]);
                    return Flight::json([
                        'status' => 500,
                        'error_code' => 'DELETE_FAILED',
                        'data' => null
                    ], 500);
                }

                // Count remaining photos
                $remainingPhotos = count(glob($petDir . "pet_*.*"));

                Logger::info("Pet photo deletion completed", "MyPetsController", [
                    'pet_id' => $petId,
                    'user_id' => $userId,
                    'filename' => $filename,
                    'remaining_photos' => $remainingPhotos
                ]);

                return Flight::json([
                    'status' => 200,
                    'error_code' => null,
                    'data' => [
                        'message' => 'Photo deleted successfully',
                        'filename' => $filename,
                        'remaining_photos' => $remainingPhotos
                    ]
                ], 200);

            } catch (\Exception $e) {
                Logger::error("Token decode error: " . $e->getMessage(), "MyPetsController");
                return Flight::json([
                    'status' => 401,
                    'error_code' => 'INVALID_TOKEN',
                    'data' => null
                ], 401);
            }

        } catch (\Exception $e) {
            Logger::error("deletePetPhotos error: " . $e->getMessage(), "MyPetsController");
            return Flight::json([
                'status' => 500,
                'error_code' => 'SYSTEM_ERROR',
                'data' => null
            ], 500);
        }
    }
} 