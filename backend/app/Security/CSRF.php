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
    //  validateFromDB()
    //  For SPA/React — validate token against DB record
    //  Call this instead of validate() for API routes
    // --------------------------------------------------------
    public static function validateFromDB(string $submittedToken, int $userId, PDO $pdo): bool
    {
        if (empty($submittedToken)) {
            return false;
        }

        $stmt = $pdo->prepare(
            "SELECT csrf_token FROM user_tokens WHERE user_id = ? AND csrf_token = ?"
        );
        $stmt->execute([$userId, $submittedToken]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return !empty($row);
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
        return $_SESSION[self::SESSION_KEY] ?? null;
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