<?php

require_once __DIR__ . '/masterDatabase.php';

class SubdomainResolver
{
    private static ?array $currentTenant = null;

    /**
     * Detects subdomain from HTTP_HOST.
     * localhost:3000         → null (landing page)
     * apollo.localhost:3000  → "apollo"
     */
    public static function detect(): ?string
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';

        // Remove port
        $host = explode(':', $host)[0];

        // Split by dot
        $parts = explode('.', $host);

        // "localhost" alone = landing page, no subdomain
        if (count($parts) === 1) {
            return null;
        }

        // "apollo.localhost" → subdomain = "apollo"
        return strtolower($parts[0]);
    }

    /**
     * Loads tenant record from master DB using subdomain.
     * Returns null if not found or inactive.
     */
    public static function resolve(): ?array
    {
        if (self::$currentTenant !== null) {
            return self::$currentTenant;
        }

        $subdomain = self::detect();

        if ($subdomain === null) {
            return null;
        }

        try {
            $master = MasterDatabase::getConnection();
            $stmt   = $master->prepare(
                "SELECT * FROM tenants 
                 WHERE subdomain = ? AND is_active = 1 AND deleted_at IS NULL 
                 LIMIT 1"
            );
            $stmt->execute([$subdomain]);
            $tenant = $stmt->fetch();

            if (!$tenant) {
                return null;
            }

            self::$currentTenant = $tenant;
            return $tenant;

        } catch (Throwable $e) {
            error_log('[SubdomainResolver] ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Returns tenant or exits with 404 JSON.
     * Use this in protected routes that require a valid tenant.
     */
    public static function requireTenant(): array
    {
        $tenant = self::resolve();

        if (!$tenant) {
            http_response_code(404);
            echo json_encode([
                'csrf_token' => '',
                'payload' => [
                    'status'  => false,
                    'message' => 'Tenant not found or inactive.',
                ]
            ]);
            exit;
        }

        return $tenant;
    }

    public static function getCurrent(): ?array
    {
        return self::$currentTenant;
    }
}