<?php

class MasterDatabase
{
    private static ?PDO $instance = null;

    private function __construct() {}
    private function __clone() {}

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $host    = $_ENV['DB_HOST']    ?? '127.0.0.1';
            $port    = $_ENV['DB_PORT']    ?? '3306';
            $dbname  = $_ENV['DB_NAME']    ?? 'healthcare_master_db';
            $user    = $_ENV['DB_USER']    ?? 'root';
            $pass    = $_ENV['DB_PASS']    ?? '';
            $charset = 'utf8mb4';

            // ===== DEBUG LOGS =====
            error_log("========== MASTER DATABASE DEBUG ==========");
            error_log("Host     : " . $host);
            error_log("Port     : " . $port);
            error_log("Database : " . $dbname);
            error_log("User     : " . $user);
            error_log("Password : " . ($pass === '' ? '[EMPTY]' : '[SET]'));
            error_log("DSN      : mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}");
            error_log("===========================================");

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

           try {
    // Connect WITHOUT selecting a database first
    $testDsn = "mysql:host={$host};port={$port};charset={$charset}";
    $pdo = new PDO($testDsn, $user, $pass, $options);

    error_log("Connected to MariaDB successfully.");

    $stmt = $pdo->query("SELECT @@hostname AS hostname, @@port AS port");
    $server = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log("Connected Server: " . print_r($server, true));

    $stmt = $pdo->query("SHOW DATABASES");
    $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);
    error_log("Available Databases: " . implode(", ", $databases));

    // Now connect to the required database
    self::$instance = new PDO($dsn, $user, $pass, $options);

} catch (PDOException $e) {
    error_log("[MASTER DB ERROR] " . $e->getMessage());

    http_response_code(500);
    die(json_encode([
        'success' => false,
        'message' => 'Master database connection failed'
    ]));
}
        }

        return self::$instance;
    }


    public static function getConnection(): PDO
    {
        return self::getInstance();
    }
}
