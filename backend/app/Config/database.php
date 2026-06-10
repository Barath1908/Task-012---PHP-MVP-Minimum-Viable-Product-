<?php

// ============================================================
//  database.php — PDO Connection (Singleton)
//  Returns a single shared PDO instance for the app.
//  All credentials come from .env via config.php.
// ============================================================

class Database
{
    private static ?PDO $instance = null;

    // -- Prevent direct instantiation ------------------------
    private function __construct() {}
    private function __clone() {}

    // --------------------------------------------------------
    //  getInstance()
    //  Returns the shared PDO connection.
    //  Creates it on first call (lazy init).
    // --------------------------------------------------------
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $host    = $_ENV['DB_HOST']    ?? '127.0.0.1';
            $port    = $_ENV['DB_PORT']    ?? '3307';
            $dbname  = $_ENV['DB_NAME']    ?? '';
            $user    = $_ENV['DB_USER']    ?? '';
            $pass    = $_ENV['DB_PASS']    ?? '';
            $charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

            $dsn = "mysql:host={$host};
                     port={$port};
                     dbname={$dbname};
                     charset={$charset}";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,  // real prepared statements
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                // Never expose DB errors to client
                error_log('[DB ERROR] ' . $e->getMessage());
                http_response_code(500);
                die(json_encode([
                    'success' => false,
                    'message' => 'Database connection failed'
                ]));
            }
        }

        return self::$instance;
    }

    // --------------------------------------------------------
    //  getConnection()
    //  Alias for getInstance() — more readable in services.
    //  Usage: $db = Database::getConnection();
    // --------------------------------------------------------
    public static function getConnection(): PDO
    {
        return self::getInstance();
    }
}
