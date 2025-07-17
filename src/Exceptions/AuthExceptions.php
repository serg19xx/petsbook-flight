<?php

namespace App\Exceptions;

/**
 * Исключения для аутентификации
 */
class EmailException extends \Exception
{
    public function __construct($message = "Email sending failed", $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

class ValidationException extends \Exception
{
    public function __construct($message = "Validation failed", $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

class DatabaseException extends \Exception
{
    public function __construct($message = "Database operation failed", $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

class TokenException extends \Exception
{
    public function __construct($message = "Token operation failed", $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
} 