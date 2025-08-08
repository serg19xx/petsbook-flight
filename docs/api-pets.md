# Pets API Documentation

## Overview

The Pets API provides endpoints for managing user pets. All endpoints require authentication via JWT token in cookies.

## Authentication

All endpoints require a valid JWT token in the `auth_token` cookie. The token should contain a `user_id` field.

## Endpoints

### GET /api/pets/my-pets

Get all pets for the authenticated user.

**Headers:**
- `Cookie: auth_token=<jwt_token>`

**Response (200):**
```json
{
  "success": true,
  "pets": [
    {
      "id": 1,
      "name": "Buddy",
      "species": "Dog",
      "breed": "Golden Retriever",
      "birth_date": "2020-05-15",
      "weight": 25.5,
      "status": "active",
      "created_at": "2024-01-15 10:30:00",
      "updated_at": "2024-01-15 10:30:00"
    }
  ],
  "total_count": 1
}
```

**Error Responses:**
- `401` - `TOKEN_NOT_PROVIDED` - No token provided
- `401` - `INVALID_TOKEN` - Invalid or expired token
- `500` - `JWT_CONFIG_MISSING` - JWT configuration missing
- `500` - `SYSTEM_ERROR` - System error

### POST /api/pets

Create a new pet for the authenticated user.

**Headers:**
- `Cookie: auth_token=<jwt_token>`
- `Content-Type: application/json`

**Request Body:**
```json
{
  "name": "Buddy",
  "gender": "Boy",
  "dob": "2020-05-15",
  "species": "Dog",
  "breed": "Golden Retriever",
  "color": "Golden",
  "description": "Friendly and energetic dog",
  "published": 1,
  "pet_size": "Large"
}
```

**Required Fields:**
- `name` - Pet name (string)
- `species` - Pet species (string)

**Optional Fields:**
- `gender` - Pet gender ("Boy" or "Girl")
- `dob` - Date of birth in YYYY-MM-DD format
- `breed` - Pet breed (string)
- `color` - Pet color (string)
- `description` - Pet description (string, max 1000 chars)
- `published` - Published status (integer, 0 or 1, default: 1)
- `pet_size` - Pet size (string)

**Response (200):**
```json
{
  "success": true,
  "pet": {
    "id": 1,
    "name": "Buddy",
    "gender": "Boy",
    "dob": "2020-05-15",
    "species": "Dog",
    "breed": "Golden Retriever",
    "color": "Golden",
    "description": "Friendly and energetic dog",
    "published": 1,
    "pet_size": "Large",
    "created": "2024-01-15 10:30:00"
  }
}
```

**Error Responses:**
- `400` - `INVALID_PET_DATA` - Missing required fields or invalid data
- `401` - `TOKEN_NOT_PROVIDED` - No token provided
- `401` - `INVALID_TOKEN` - Invalid or expired token
- `500` - `JWT_CONFIG_MISSING` - JWT configuration missing
- `500` - `SYSTEM_ERROR` - System error

### PUT /api/pets/:id

Update an existing pet.

**Headers:**
- `Cookie: auth_token=<jwt_token>`
- `Content-Type: application/json`

**URL Parameters:**
- `id` - Pet ID (integer)

**Request Body:**
```json
{
  "name": "Buddy Updated",
  "gender": "Boy",
  "dob": "2020-05-15",
  "species": "Dog",
  "breed": "Golden Retriever",
  "color": "Golden",
  "description": "Updated description",
  "published": 1,
  "pet_size": "Large"
}
```

**All fields are optional for updates.**

**Response (200):**
```json
{
  "success": true,
  "pet": {
    "id": 1,
    "name": "Buddy Updated",
    "gender": "Boy",
    "dob": "2020-05-15",
    "species": "Dog",
    "breed": "Golden Retriever",
    "color": "Golden",
    "description": "Updated description",
    "published": 1,
    "pet_size": "Large",
    "created": "2024-01-15 10:30:00"
  }
}
```

**Error Responses:**
- `400` - `INVALID_PET_DATA` - Invalid JSON data or no fields to update
- `401` - `TOKEN_NOT_PROVIDED` - No token provided
- `401` - `INVALID_TOKEN` - Invalid or expired token
- `403` - `PET_ACCESS_DENIED` - Pet doesn't belong to user
- `404` - `PET_NOT_FOUND` - Pet not found
- `500` - `JWT_CONFIG_MISSING` - JWT configuration missing
- `500` - `SYSTEM_ERROR` - System error

### DELETE /api/pets/:id

Delete a pet.

**Headers:**
- `Cookie: auth_token=<jwt_token>`

**URL Parameters:**
- `id` - Pet ID (integer)

**Response (200):**
```json
{
  "success": true,
  "message": "Pet deleted successfully"
}
```

**Error Responses:**
- `401` - `TOKEN_NOT_PROVIDED` - No token provided
- `401` - `INVALID_TOKEN` - Invalid or expired token
- `403` - `PET_ACCESS_DENIED` - Pet doesn't belong to user
- `404` - `PET_NOT_FOUND` - Pet not found
- `500` - `JWT_CONFIG_MISSING` - JWT configuration missing
- `500` - `SYSTEM_ERROR` - System error

### PATCH /api/pets/:id/status

Update pet status.

**Headers:**
- `Cookie: auth_token=<jwt_token>`
- `Content-Type: application/json`

**URL Parameters:**
- `id` - Pet ID (integer)

**Request Body:**
```json
{
  "published": 0
}
```

**Required Fields:**
- `published` - Published status (integer, 0 or 1)

**Response (200):**
```json
{
  "success": true,
  "pet": {
    "id": 1,
    "name": "Buddy",
    "gender": "Boy",
    "dob": "2020-05-15",
    "species": "Dog",
    "breed": "Golden Retriever",
    "color": "Golden",
    "description": "Friendly and energetic dog",
    "published": 0,
    "pet_size": "Large",
    "created": "2024-01-15 10:30:00"
  }
}
```

**Error Responses:**
- `400` - `INVALID_PET_DATA` - Status not provided or empty
- `401` - `TOKEN_NOT_PROVIDED` - No token provided
- `401` - `INVALID_TOKEN` - Invalid or expired token
- `403` - `PET_ACCESS_DENIED` - Pet doesn't belong to user
- `404` - `PET_NOT_FOUND` - Pet not found
- `500` - `JWT_CONFIG_MISSING` - JWT configuration missing
- `500` - `SYSTEM_ERROR` - System error

## Error Codes

### Authentication Errors
- `TOKEN_NOT_PROVIDED` - No JWT token provided in cookies
- `INVALID_TOKEN` - Invalid or expired JWT token
- `JWT_CONFIG_MISSING` - JWT configuration is missing

### Pet Errors
- `PET_NOT_FOUND` - Pet with specified ID not found
- `PET_ACCESS_DENIED` - User doesn't have access to this pet
- `INVALID_PET_DATA` - Invalid pet data provided
- `PET_CREATE_FAILED` - Failed to create pet
- `PET_UPDATE_FAILED` - Failed to update pet
- `PET_DELETE_FAILED` - Failed to delete pet

### System Errors
- `SYSTEM_ERROR` - General system error
- `DATABASE_ERROR` - Database error

## Database Schema

The `pets` table has the following structure:

```sql
CREATE TABLE `pets` (
  `id` bigint(20) unsigned NOT NULL DEFAULT 0,
  `ownerId` bigint(20) unsigned NOT NULL,
  `name` varchar(100) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `gender` enum('Boy','Girl') CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `species` varchar(100) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `breed` varchar(100) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `color` varchar(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `description` varchar(1000) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `published` tinyint(4) NOT NULL DEFAULT 1,
  `pet_size` varchar(45) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci
```

## Examples

### Creating a new pet
```bash
curl -X POST http://localhost/api/pets \
  -H "Content-Type: application/json" \
  -H "Cookie: auth_token=your_jwt_token" \
  -d '{
    "name": "Fluffy",
    "gender": "Girl",
    "dob": "2021-03-10",
    "species": "Cat",
    "breed": "Persian",
    "color": "White",
    "description": "Beautiful Persian cat",
    "published": 1,
    "pet_size": "Medium"
  }'
```

### Getting user's pets
```bash
curl -X GET http://localhost/api/pets/my-pets \
  -H "Cookie: auth_token=your_jwt_token"
```

### Updating pet published status
```bash
curl -X PATCH http://localhost/api/pets/1/status \
  -H "Content-Type: application/json" \
  -H "Cookie: auth_token=your_jwt_token" \
  -d '{
    "published": 0
  }'
``` 