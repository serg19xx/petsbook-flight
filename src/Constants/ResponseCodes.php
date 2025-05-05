<?php

namespace App\Constants;

/**
 * Response codes for API
 */
class ResponseCodes {
    // Success codes
    const LOGIN_SUCCESS = 'LOGIN_SUCCESS';    
    const LOGOUT_SUCCESS = 'LOGOUT_SUCCESS';               // Успешная авторизация
    const REGISTRATION_SUCCESS = 'REGISTRATION_SUCCESS';     // Успешная регистрация
    const USER_DATA_SUCCESS = 'USER_DATA_SUCCESS';          // Успешное получение данных пользователя
    const PASSWORD_RESET_SUCCESS = 'PASSWORD_RESET_SUCCESS'; // Успешный сброс пароля
    const EMAIL_VERIFICATION_SUCCESS = 'EMAIL_VERIFICATION_SUCCESS'; // Успешная верификация email

    // Authentication error codes
    const MISSING_CREDENTIALS = 'MISSING_CREDENTIALS';       // Отсутствуют email или пароль
    const INVALID_EMAIL = 'INVALID_EMAIL';                  // Неверный или несуществующий email
    const INVALID_PASSWORD = 'INVALID_PASSWORD';            // Неверный пароль
    const LOGIN_FAILED = 'LOGIN_FAILED';                     // Ошибка входа (общая)
    const ACCOUNT_INACTIVE = 'ACCOUNT_INACTIVE';             // Аккаунт неактивен
    const EMAIL_NOT_VERIFIED = 'EMAIL_NOT_VERIFIED';         // Email не подтвержден
    const ACCOUNT_BLOCKED = 'ACCOUNT_BLOCKED';               // Аккаунт заблокирован

    // Token error codes
    const TOKEN_NOT_PROVIDED = 'TOKEN_NOT_PROVIDED';         // Токен не предоставлен
    const INVALID_TOKEN = 'INVALID_TOKEN';                   // Недействительный токен
    const TOKEN_EXPIRED = 'TOKEN_EXPIRED';                   // Токен истек

    // User error codes
    const USER_NOT_FOUND = 'USER_NOT_FOUND';                // Пользователь не найден
    const INVALID_ROLE = 'INVALID_ROLE';                     // Недействительная роль пользователя
    const EMAIL_ALREADY_EXISTS = 'EMAIL_ALREADY_EXISTS';     // Email уже существует
    const INVALID_USER_DATA = 'INVALID_USER_DATA';          // Недействительные данные пользователя

    // System error codes
    const SYSTEM_ERROR = 'SYSTEM_ERROR';                     // Системная ошибка
    const DATABASE_ERROR = 'DATABASE_ERROR';                 // Ошибка базы данных
    const EMAIL_SEND_ERROR = 'EMAIL_SEND_ERROR';            // Ошибка отправки email
}
