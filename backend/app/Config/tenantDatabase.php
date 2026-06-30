<?php

// Resolves the correct tenant DB based on subdomain
// and returns a PDO connection to that tenant's database.

class TenantDatabase
{
    private static array $instances = [];

    private function __construct() {}
    private function __clone() {}

    /**
     * Get tenant PDO connection by db_name.
     * Called after subdomain is resolved from request.
     */
    public static function getInstance(string $dbName): PDO
    {
        if (!isset(self::$instances[$dbName])) {
            $host    = $_ENV['DB_HOST']    ?? '127.0.0.1';
            $port    = $_ENV['DB_PORT']    ?? '3306';
            $user    = $_ENV['DB_USER']    ?? 'root';
            $pass    = $_ENV['DB_PASS']    ?? '';
            $charset = 'utf8mb4';

            $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset={$charset}";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instances[$dbName] = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                error_log('[TENANT DB ERROR] ' . $e->getMessage());
                http_response_code(500);
                die(json_encode([
                    'success' => false,
                    'message' => "Tenant database connection failed for: {$dbName}"
                ]));
            }
        }

        return self::$instances[$dbName];
    }

    public static function getConnection(string $dbName): PDO
    {
        return self::getInstance($dbName);
    }
}