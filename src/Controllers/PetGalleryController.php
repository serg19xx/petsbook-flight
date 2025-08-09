<?php

namespace App\Controllers;

use PDO;
use PDOException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Utils\Logger;

class PetGalleryController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
        Logger::info("PetGalleryController initialized", "PetGalleryController");
    }

    public function getPublicPets() {
        Logger::info("getPublicPets called", "PetGalleryController");

        try {
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = min(50, max(1, (int)($_GET['limit'] ?? 12)));
            $offset = ($page - 1) * $limit;
            
            $species = $_GET['species'] ?? null;
            $gender = $_GET['gender'] ?? null;
            $age = $_GET['age'] ?? null;
            $sort = $_GET['sort'] ?? 'newest';
            
            $whereConditions = ['p.published = 1'];
            $params = [];
            
            if ($species && !empty(trim($species))) {
                $whereConditions[] = 'p.species = :species';
                $params['species'] = trim($species);
            }
            
            if ($gender && !empty(trim($gender))) {
                $whereConditions[] = 'p.gender = :gender';
                $params['gender'] = trim($gender);
            }
            
            $orderBy = 'p.created DESC';
            switch ($sort) {
                case 'oldest':
                    $orderBy = 'p.created ASC';
                    break;
                case 'name':
                    $orderBy = 'p.name ASC, p.created DESC';
                    break;
                case 'newest':
                default:
                    $orderBy = 'p.created DESC';
                    break;
            }

            $countSql = "SELECT COUNT(DISTINCT p.id) as total FROM pets p WHERE " . implode(' AND ', $whereConditions);
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($params);
            $totalCount = $countStmt->fetch()['total'];

            $dataSql = "
                SELECT p.id, p.ownerId, p.name, p.gender, p.dob, p.species, p.breed, p.color, p.pet_size, p.description, p.created
                FROM pets p
                WHERE " . implode(' AND ', $whereConditions) . "
                ORDER BY $orderBy
                LIMIT :limit OFFSET :offset
            ";

            $params['limit'] = $limit;
            $params['offset'] = $offset;
            
            $dataStmt = $this->db->prepare($dataSql);
            $dataStmt->execute($params);
            $pets = $dataStmt->fetchAll();

            $currentUserId = null;
            $authToken = $_COOKIE['auth_token'] ?? null;
            if ($authToken) {
                try {
                    $decoded = JWT::decode($authToken, new Key($_ENV['JWT_SECRET'], 'HS256'));
                    $currentUserId = $decoded->user_id ?? null;
                } catch (\Exception $e) {
                    $currentUserId = null;
                }
            }

            $petIds = array_column($pets, 'id');
            $likesInfo = [];
            $userLikes = [];
            
            if (!empty($petIds)) {
                $placeholders = str_repeat('?,', count($petIds) - 1) . '?';
                $likesCountStmt = $this->db->prepare("SELECT petId, COUNT(*) as likes_count FROM pet_liked WHERE petId IN ($placeholders) GROUP BY petId");
                $likesCountStmt->execute($petIds);
                $likesCountResult = $likesCountStmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($likesCountResult as $like) {
                    $likesInfo[$like['petId']] = (int)$like['likes_count'];
                }
                
                if ($currentUserId) {
                    $userLikesStmt = $this->db->prepare("SELECT petId FROM pet_liked WHERE petId IN ($placeholders) AND userId = ?");
                    $userParams = array_merge($petIds, [$currentUserId]);
                    $userLikesStmt->execute($userParams);
                    $userLikesResult = $userLikesStmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    foreach ($userLikesResult as $petId) {
                        $userLikes[$petId] = true;
                    }
                }
            }

            $petsWithPhotos = [];
            $basePhotosPath = __DIR__ . '/../../public/profile-images/pet-photos/';
            
            foreach ($pets as $pet) {
                $petPhotosPath = $basePhotosPath . 'user-' . $pet['ownerId'] . '/pet-' . $pet['id'] . '/';
                $mainPhotos = [];
                
                if (is_dir($petPhotosPath)) {
                    $photoFiles = glob($petPhotosPath . 'pet_*.*');
                    
                    foreach ($photoFiles as $photoFile) {
                        $filename = basename($photoFile);
                        $mainPhotos[] = [
                            'filename' => $filename,
                            'url' => "/profile-images/pet-photos/user-{$pet['ownerId']}/pet-{$pet['id']}/{$filename}"
                        ];
                    }
                }
                
                $pet['main_photos'] = $mainPhotos;
                
                if ($pet['dob']) {
                    $birthDate = new \DateTime($pet['dob']);
                    $today = new \DateTime();
                    $age = $birthDate->diff($today);
                    $pet['age_years'] = $age->y;
                    $pet['age_months'] = $age->m;
                } else {
                    $pet['age_years'] = null;
                    $pet['age_months'] = null;
                }
                
                $pet['is_liked'] = isset($userLikes[$pet['id']]);
                $pet['likes_count'] = $likesInfo[$pet['id']] ?? 0;
                
                $petsWithPhotos[] = $pet;
            }

            $totalPages = ceil($totalCount / $limit);

            return \Flight::json([
                'status' => 200,
                'error_code' => null,
                'data' => [
                    'pets' => $petsWithPhotos,
                    'pagination' => [
                        'total_count' => (int)$totalCount,
                        'page' => $page,
                        'limit' => $limit,
                        'total_pages' => $totalPages,
                        'has_next' => $page < $totalPages,
                        'has_prev' => $page > 1
                    ],
                    'filters_applied' => [
                        'species' => $species,
                        'gender' => $gender,
                        'age' => $age,
                        'location' => null,
                        'radius' => null,
                        'sort' => $sort
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            Logger::error("getPublicPets error: " . $e->getMessage(), "PetGalleryController");
            return \Flight::json(['status' => 500, 'error_code' => 'SYSTEM_ERROR', 'data' => null], 500);
        }
    }

    public function getPetDetails($petId) {
        try {
            $stmt = $this->db->prepare("SELECT p.id, p.ownerId, p.name, p.gender, p.dob, p.species, p.breed, p.color, p.pet_size, p.description, p.created, p.published FROM pets p WHERE p.id = :pet_id AND p.published = 1");
            $stmt->execute(['pet_id' => $petId]);
            $pet = $stmt->fetch();

            if (!$pet) {
                return \Flight::json(['status' => 404, 'error_code' => 'PET_NOT_FOUND', 'data' => null], 404);
            }

            $currentUserId = null;
            $authToken = $_COOKIE['auth_token'] ?? null;
            if ($authToken) {
                try {
                    $decoded = JWT::decode($authToken, new Key($_ENV['JWT_SECRET'], 'HS256'));
                    $currentUserId = $decoded->user_id ?? null;
                    
                    if ($currentUserId) {
                        $likeStmt = $this->db->prepare("SELECT COUNT(*) as count FROM pet_liked WHERE petId = :pet_id AND userId = :user_id");
                        $likeStmt->execute(['pet_id' => $petId, 'user_id' => $currentUserId]);
                        $pet['is_liked'] = $likeStmt->fetch()['count'] > 0;
                    }
                } catch (\Exception $e) {
                    $currentUserId = null;
                }
            }
            
            if (!isset($pet['is_liked'])) {
                $pet['is_liked'] = false;
            }

            $likesStmt = $this->db->prepare("SELECT COUNT(*) as count FROM pet_liked WHERE petId = :pet_id");
            $likesStmt->execute(['pet_id' => $petId]);
            $pet['likes_count'] = (int)$likesStmt->fetch()['count'];

            $basePhotosPath = __DIR__ . '/../../public/profile-images/pet-photos/';
            $petPhotosPath = $basePhotosPath . 'user-' . $pet['ownerId'] . '/pet-' . $pet['id'] . '/';
            $mainPhotos = [];
            
            if (is_dir($petPhotosPath)) {
                $photoFiles = glob($petPhotosPath . 'pet_*.*');
                
                foreach ($photoFiles as $photoFile) {
                    $filename = basename($photoFile);
                    $mainPhotos[] = [
                        'filename' => $filename,
                        'url' => "/profile-images/pet-photos/user-{$pet['ownerId']}/pet-{$pet['id']}/{$filename}"
                    ];
                }
            }
            
            $pet['main_photos'] = $mainPhotos;
            
            if ($pet['dob']) {
                $birthDate = new \DateTime($pet['dob']);
                $today = new \DateTime();
                $age = $birthDate->diff($today);
                $pet['age_years'] = $age->y;
                $pet['age_months'] = $age->m;
            } else {
                $pet['age_years'] = null;
                $pet['age_months'] = null;
            }

            return \Flight::json(['status' => 200, 'error_code' => null, 'data' => $pet], 200);

        } catch (\Exception $e) {
            Logger::error("getPetDetails error: " . $e->getMessage(), "PetGalleryController");
            return \Flight::json(['status' => 500, 'error_code' => 'SYSTEM_ERROR', 'data' => null], 500);
        }
    }

    public function togglePetLike($petId) {
        try {
            $token = $_COOKIE['auth_token'] ?? null;
            if (!$token) {
                $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
                if (!$authHeader) {
                    $headers = getallheaders();
                    $authHeader = $headers['Authorization'] ?? null;
                }
                
                if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                    $token = $matches[1];
                }
            }

            if (!$token) {
                return \Flight::json(['status' => 401, 'error_code' => 'UNAUTHORIZED', 'data' => null], 401);
            }

            try {
                $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
                $userId = $decoded->user_id;
            } catch (\Exception $e) {
                return \Flight::json(['status' => 401, 'error_code' => 'UNAUTHORIZED', 'data' => null], 401);
            }

            $petStmt = $this->db->prepare("SELECT id FROM pets WHERE id = :pet_id AND published = 1");
            $petStmt->execute(['pet_id' => $petId]);
            if (!$petStmt->fetch()) {
                return \Flight::json(['status' => 404, 'error_code' => 'PET_NOT_FOUND', 'data' => null], 404);
            }

            $likeStmt = $this->db->prepare("SELECT COUNT(*) as count FROM pet_liked WHERE petId = :pet_id AND userId = :user_id");
            $likeStmt->execute(['pet_id' => $petId, 'user_id' => $userId]);
            $likeExists = $likeStmt->fetch()['count'] > 0;

            if ($likeExists) {
                $deleteStmt = $this->db->prepare("DELETE FROM pet_liked WHERE petId = :pet_id AND userId = :user_id");
                $deleteStmt->execute(['pet_id' => $petId, 'user_id' => $userId]);
                $liked = 0;
                $action = 'unliked';
            } else {
                $insertStmt = $this->db->prepare("INSERT INTO pet_liked (petId, userId) VALUES (:pet_id, :user_id)");
                $insertStmt->execute(['pet_id' => $petId, 'user_id' => $userId]);
                $liked = 1;
                $action = 'liked';
            }

            $countStmt = $this->db->prepare("SELECT COUNT(*) as count FROM pet_liked WHERE petId = :pet_id");
            $countStmt->execute(['pet_id' => $petId]);
            $likesCount = (int)$countStmt->fetch()['count'];

            return \Flight::json([
                'status' => 200,
                'error_code' => null,
                'data' => [
                    'liked' => $liked,
                    'action' => $action,
                    'pet_id' => (int)$petId,
                    'user_id' => (int)$userId,
                    'likes_count' => $likesCount
                ]
            ], 200);

        } catch (\Exception $e) {
            Logger::error("togglePetLike error: " . $e->getMessage(), "PetGalleryController");
            return \Flight::json(['status' => 500, 'error_code' => 'SYSTEM_ERROR', 'data' => null], 500);
        }
    }
}
