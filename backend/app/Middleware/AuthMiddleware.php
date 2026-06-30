<?php

// ============================================================
//  AuthMiddleware.php — JWT Validation Gate
//  Runs before every protected controller.
//  Reads access token from PHP session.
//  Attaches decoded payload to $GLOBALS['auth_user']
//  so controllers can read: user_id, role.
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


    // Replace handle() in AuthMiddleware.php

    public static function handle(): array
    {
        self::ensureSession();

        $authHeader = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? getallheaders()['Authorization']
            ?? '';

        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            Response::unauthorized('Access token missing. Please login.');
        }

        $token = trim(substr($authHeader, 7));

        if (empty($token)) {
            Response::unauthorized('Access token missing. Please login.');
        }

        try {
            $jwt     = new JWT();
            $payload = $jwt->validate($token);

            if (($payload['type'] ?? '') !== TOKEN_ACCESS) {
                Response::unauthorized('Invalid token type.');
            }

            // No tenant check needed — DB is already tenant-scoped
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


    private static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
