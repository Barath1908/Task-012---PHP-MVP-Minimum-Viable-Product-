<?php

// ============================================================
//  CSRF.php — CSRF Token Generation & Validation
//  Strategy  : Session-based (token stored in PHP session)
//  Required  : ALL POST / PUT / DELETE requests
//  Regenerated on: app load, login, token refresh
// ============================================================

class CSRF
{
    private const SESSION_KEY = '_csrf_token';

    // --------------------------------------------------------
    //  generate()
    //  Creates a new cryptographically secure CSRF token,
    //  stores it in the session, and returns it.
    //  Call on: app load, after login, after token refresh.
    // --------------------------------------------------------
    public static function generate(): string
    {
        self::ensureSession();

        $token = bin2hex(random_bytes(32));
        $_SESSION[self::SESSION_KEY] = $token;

        return $token;
    }

    // --------------------------------------------------------
    //  validate()
    //  Compares submitted token against session token.
    //  Uses hash_equals to prevent timing attacks.
    //  Returns true on match, false otherwise.
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

    // --------------------------------------------------------
    //  regenerate()
    //  Invalidates old token and issues a new one.
    //  Always call after a successful login or token refresh
    //  to prevent session fixation attacks.
    // --------------------------------------------------------
    public static function regenerate(): string
    {
        self::ensureSession();
        unset($_SESSION[self::SESSION_KEY]);

        return self::generate();
    }

    // --------------------------------------------------------
    //  getToken()
    //  Returns current session CSRF token.
    //  Returns null if none has been generated yet.
    // --------------------------------------------------------
    public static function getToken(): ?string
    {
        self::ensureSession();
        return $_SESSION[self::SESSION_KEY] ?? null;
    }

    // --------------------------------------------------------
    //  clear()
    //  Removes CSRF token from session (on logout).
    // --------------------------------------------------------
    public static function clear(): void
    {
        self::ensureSession();
        unset($_SESSION[self::SESSION_KEY]);
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
