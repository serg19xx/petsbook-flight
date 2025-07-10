<?php

namespace App\Services;
// s
class DatabaseService
{
    private $pdo;
///
    public function __construct()
    {
        $host = 'localhost';
        $db   = 'your_db_name';
        $user = 'your_db_user';
        $pass = 'your_db_password';
        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $options = [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        $this->pdo = new \PDO($dsn, $user, $pass, $options);
    }

    public function getPdo()
    {
        return $this->pdo;
    }
}
