<?php

// Now delegates to TenantDatabase based on resolved subdomain

require_once __DIR__ . '/tenantDatabase.php';
require_once __DIR__ . '/subdomainResolver.php';

class Database
{
    private static ?PDO $instance = null;

    private function __construct() {}
    private function __clone() {}

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            // Resolve tenant from subdomain
            $tenant = SubdomainResolver::resolve();

            if (!$tenant) {
                // No tenant = landing page or invalid subdomain
                // Some routes (tenant/register) don't need tenant DB
                // Controllers that need tenant DB will call requireTenant()
                http_response_code(400);
                die(json_encode([
                    'payload' => [
                        'status'  => false,
                        'message' => 'No tenant context. Access via your workspace URL.',
                    ]
                ]));
            }

            $dbName = $tenant['db_name'];
            self::$instance = TenantDatabase::getConnection($dbName);
        }

        return self::$instance;
    }

    public static function getConnection(): PDO
    {
        return self::getInstance();
    }

    // Force reset between requests if needed
    public static function reset(): void
    {
        self::$instance = null;
    }
}