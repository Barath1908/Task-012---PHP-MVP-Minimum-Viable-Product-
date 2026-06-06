<?php

// ============================================================
//  AES.php — AES-256-CBC Encryption / Decryption
//  Used to encrypt every request payload and response body.
//  Key and IV come from .env via config.php constants.
//
//  Frontend counterpart: CryptoJS AES-256 (same key/IV)
// ============================================================

class AES
{
    private string $key;
    private string $iv;
    private string $cipher = AES_CIPHER; // 'AES-256-CBC'

    // --------------------------------------------------------
    public function __construct()
    {
        // Key must be 32 bytes for AES-256
        // IV  must be 16 bytes for CBC
        $this->key = substr(hash('sha256', AES_KEY, true), 0, 32);
        $this->iv  = substr(hash('md5',    AES_IV,  true), 0, 16);

        if (empty(AES_KEY) || empty(AES_IV)) {
            throw new RuntimeException('AES_KEY or AES_IV not set in .env');
        }
    }

    // --------------------------------------------------------
    //  encrypt()
    //  Accepts a plain string or array (auto JSON-encoded).
    //  Returns base64-encoded ciphertext.
    // --------------------------------------------------------
    public function encrypt(mixed $data): string
    {
        $plain = is_array($data) ? json_encode($data) : (string)$data;

        $encrypted = openssl_encrypt(
            $plain,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $this->iv
        );

        if ($encrypted === false) {
            throw new RuntimeException('AES encryption failed');
        }

        return base64_encode($encrypted);
    }

    // --------------------------------------------------------
    //  decrypt()
    //  Accepts base64-encoded ciphertext.
    //  Returns plain string. Caller decodes JSON if needed.
    // --------------------------------------------------------
    public function decrypt(string $ciphertext): string
    {
        $decoded = base64_decode($ciphertext, strict: true);

        if ($decoded === false) {
            throw new InvalidArgumentException('AES decrypt: invalid base64 input');
        }

        $plain = openssl_decrypt(
            $decoded,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $this->iv
        );

        if ($plain === false) {
            throw new RuntimeException('AES decryption failed — bad key, IV, or corrupted data');
        }

        return $plain;
    }

    // --------------------------------------------------------
    //  decryptToArray()
    //  Convenience: decrypt + JSON decode in one call.
    //  Returns associative array or throws on invalid JSON.
    // --------------------------------------------------------
    public function decryptToArray(string $ciphertext): array
    {
        $plain = $this->decrypt($ciphertext);
        $data  = json_decode($plain, associative: true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('AES decryptToArray: decrypted data is not valid JSON');
        }

        return $data;
    }
}
