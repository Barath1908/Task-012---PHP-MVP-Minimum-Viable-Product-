<?php

// ============================================================
//  CsrfMiddleware.php — CSRF Token Validation Gate
//  Runs before AuthMiddleware on all POST/PUT/DELETE requests.
//  Expects CSRF token in the decrypted request payload:
//  { "csrf_token": "...", "payload": "ENCRYPTED_DATA" }
// ============================================================

require_once __DIR__ . '/../Security/CSRF.php';
require_once __DIR__ . '/../Helpers/Response.php';

class CsrfMiddleware
{
    // Methods that require CSRF validation
    private const PROTECTED_METHODS = ['POST', 'PUT', 'DELETE', 'PATCH'];

    // --------------------------------------------------------
    //  handle()
    //  Validates CSRF token for mutating HTTP methods.
    //  GET and OPTIONS requests are skipped.
    //  Exits with 403 if token is missing or invalid.
    // --------------------------------------------------------
    public static function handle(string $csrfToken = ''): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if (!in_array($method, self::PROTECTED_METHODS, strict: true)) {
            return;
        }

        if (empty($csrfToken)) {
            Response::error('CSRF token missing.', HTTP_FORBIDDEN);
        }


        if (!CSRF::validate($csrfToken)) {
            Response::error('CSRF token invalid or expired.', HTTP_FORBIDDEN);
        }
    }
}
