<?php

// ============================================================
//  Hash.php — Password Hashing & Verification
//  Uses PHP native password_hash (bcrypt, cost 12).
//  Never store or compare raw passwords anywhere.
// ============================================================

class Hash
{
    private const ALGO    = PASSWORD_BCRYPT;
    private const OPTIONS = ['cost' => 12];

    // --------------------------------------------------------
    //  make()
    //  Hash a plain-text password.
    //  Usage: Hash::make('mypassword')
    // --------------------------------------------------------
    public static function make(string $plain): string
    {
        $hash = password_hash($plain, self::ALGO, self::OPTIONS);

        if ($hash === false) {
            throw new RuntimeException('Password hashing failed');
        }

        return $hash;
    }

    // --------------------------------------------------------
    //  verify()
    //  Compare plain password against stored hash.
    //  Returns true on match, false otherwise.
    //  Usage: Hash::verify('mypassword', $storedHash)
    // --------------------------------------------------------
    public static function verify(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    // --------------------------------------------------------
    //  needsRehash()
    //  Returns true if hash was made with old cost/algo.
    //  Call after login — rehash and update DB if true.
    // --------------------------------------------------------
    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, self::ALGO, self::OPTIONS);
    }

    // --------------------------------------------------------
    //  generateRandom()
    //  Cryptographically secure random string.
    //  Used for temporary passwords, invite tokens, etc.
    // --------------------------------------------------------
    public static function generateRandom(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }
}
