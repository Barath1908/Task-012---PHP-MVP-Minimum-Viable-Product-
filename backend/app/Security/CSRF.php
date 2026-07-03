<?php

// ============================================================
//  CSRF.php — CSRF Token Generation & Validation
//  Strategy  : JWT/Token-based (stored in DB or derived from JWT)
//  Since frontend is a separate React SPA on different origin,
//  PHP sessions are not shared — session-based CSRF won't work.
//  Solution  : Store CSRF token in DB against user, validate there.
// ============================================================

class CSRF
{
    private const SESSION_KEY = '_csrf_token';

    // Generate token and store in session (kept for non-SPA use)
    public static function generate(): string
    {
        self::ensureSession();
        $token = bin2hex(random_bytes(32));
        $_SESSION[self::SESSION_KEY] = $token;
        return $token;
    }


    // --------------------------------------------------------
    //  validate()
    //  Session-based — only works when session cookie is shared
    //  NOT reliable for React SPA on different origin
    // --------------------------------------------------------
    public static function validate(string $submittedToken): bool
    {
        self::ensureSession();
        $sessionToken = $_SESSION[self::SESSION_KEY] ?? '';

        if (empty($sessionToken) || empty($submittedToken)) {
            return false;
        }

        return hash_equals($sessionToken, $submittedToken);
    }

    public static function regenerate(): string
    {
        self::ensureSession();
        unset($_SESSION[self::SESSION_KEY]);
        return self::generate();
    }

    public static function getToken(): ?string
    {
        self::ensureSession();
        if (!isset($_SESSION[self::SESSION_KEY])) {
            return self::generate();
        }
        return $_SESSION[self::SESSION_KEY];
    }

    public static function clear(): void
    {
        self::ensureSession();
        unset($_SESSION[self::SESSION_KEY]);
    }

    private static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}