<?php

// ============================================================
//  AuthMiddleware.php — JWT Validation Gate
//  Runs before every protected controller.
//  Reads access token from PHP session.
//  Attaches decoded payload to $GLOBALS['auth_user']
//  so controllers can read: user_id, tenant_id, role.
//  Also validates tenant is active per request (task req).
// ============================================================

require_once __DIR__ . '/../Security/JWT.php';
require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Helpers/Response.php';

class AuthMiddleware
{
    // --------------------------------------------------------
    //  handle()
    //  Call this at the top of every protected route.
    //  Returns decoded token payload on success.
    //  Sends 401/403 and exits on failure.
    // --------------------------------------------------------
    public static function handle(): array
    {
        self::ensureSession();

        // Read access token from Authorization header
        // Format: Authorization: Bearer <token>

        $authHeader = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? getallheaders()['Authorization']
            ?? '';

        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            Response::unauthorized('Access token missing. Please login.');
        }

        $token = trim(substr($authHeader, 7)); // Remove "Bearer " prefix

        if (empty($token)) {
            Response::unauthorized('Access token missing. Please login.');
        }

        try {
            $jwt     = new JWT();
            $payload = $jwt->validate($token);

            // Ensure it's an access token, not a refresh token

            if (($payload['type'] ?? '') !== TOKEN_ACCESS) {
                Response::unauthorized('Invalid token type.');
            }

            // Fix 4: Tenant validation per request
            // Task requirement: tenant exists + active + not deleted

            $tenantId = (int)($payload['tenant_id'] ?? 0);
            if (!$tenantId || !self::isTenantActive($tenantId)) {
                unset($_SESSION['access_token']);
                Response::forbidden('Tenant is inactive or no longer exists.');
            }

            // Attach to globals so any controller can read it

            $GLOBALS['auth_user'] = $payload;

            return $payload;

        } catch (Throwable $e) {
            
            Response::unauthorized($e->getMessage());
        }
    }

    // --------------------------------------------------------
    //  allowRoles()
    //  Role-based access check after handle().
    //  Pass allowed roles as an array of role name strings.
    //  Usage: AuthMiddleware::allowRoles([ROLE_ADMIN, ROLE_PROVIDER])
    // --------------------------------------------------------

    public static function allowRoles(array $roles): void
    {
        $authUser = $GLOBALS['auth_user'] ?? null;

        if (!$authUser) {
            Response::unauthorized('Not authenticated.');
        }

        if (!in_array($authUser['role'], $roles, strict: true)) {
            Response::forbidden('You do not have permission to access this resource.');
        }
    }

    // --------------------------------------------------------
    //  user()
    //  Returns the authenticated user payload.
    //  Call after handle() has been called.
    // --------------------------------------------------------

    public static function user(): ?array
    {
        return $GLOBALS['auth_user'] ?? null;
    }

    // --------------------------------------------------------
    //  tenantId()
    //  Convenience: returns tenant_id of authenticated user.
    // --------------------------------------------------------

    public static function tenantId(): ?int
    {
        return $GLOBALS['auth_user']['tenant_id'] ?? null;
    }

    // --------------------------------------------------------
    //  userId()
    //  Convenience: returns user_id of authenticated user.
    // --------------------------------------------------------

    public static function userId(): ?int
    {
        return $GLOBALS['auth_user']['user_id'] ?? null;
    }

    // ========================================================
    //  PRIVATE HELPERS
    // ========================================================

    // Fix 4: Query DB to confirm tenant is still active

    private static function isTenantActive(int $tenantId): bool
    {
        try {
            $db   = Database::getConnection();
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM tenants
                WHERE id = ? AND is_active = 1 AND deleted_at IS NULL
                LIMIT 1
            ");
            $stmt->execute([$tenantId]);
            return (int)$stmt->fetchColumn() > 0;
        } 
        catch (Throwable $e) {
            error_log('[AuthMiddleware] Tenant check failed: ' . $e->getMessage());
            return false;
        }
    }

    private static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
