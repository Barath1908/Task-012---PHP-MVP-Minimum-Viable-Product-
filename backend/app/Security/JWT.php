<?php

// ============================================================
//  JWT.php — Access & Refresh Token Generation / Validation
//  Algorithm : HS256 (HMAC-SHA256)
//  Secret    : JWT_SECRET from .env via config.php
//
//  Access Token  → short lived (15 min) → stored in PHP session
//  Refresh Token → long lived (30 days) → stored in DB
// ============================================================

class JWT
{
    private string $secret;

    // --------------------------------------------------------
    public function __construct()
    {
        if (empty(JWT_SECRET)) {
            throw new RuntimeException('JWT_SECRET not set in .env');
        }
        $this->secret = JWT_SECRET;
    }

    // --------------------------------------------------------
    //  generateAccessToken()
    //  Payload contains user identity + tenant context.
    //  Expires in ACCESS_TOKEN_EXPIRY seconds (default 15 min).
    // --------------------------------------------------------
    public function generateAccessToken(array $user): string
    {
        $payload = [
            'type'      => TOKEN_ACCESS,
            'user_id'   => $user['id'],
            'tenant_id' => $user['tenant_id'],
            'role'      => $user['role'],
            'iat'       => time(),
            'exp'       => time() + ACCESS_TOKEN_EXPIRY,
        ];

        return $this->encode($payload);
    }

    // --------------------------------------------------------
    //  generateRefreshToken()
    //  Longer lived. Stored hashed in DB (refresh_tokens table).
    //  Contains minimal payload — just enough to identify user.
    // --------------------------------------------------------
    public function generateRefreshToken(array $user): string
    {
        $payload = [
            'type'      => TOKEN_REFRESH,
            'user_id'   => $user['id'],
            'tenant_id' => $user['tenant_id'],
            'jti'       => bin2hex(random_bytes(16)), // unique token ID
            'iat'       => time(),
            'exp'       => time() + REFRESH_TOKEN_EXPIRY,
        ];

        return $this->encode($payload);
    }

    // --------------------------------------------------------
    //  validate()
    //  Verifies signature + expiry.
    //  Returns decoded payload array on success.
    //  Throws on invalid/expired token.
    // --------------------------------------------------------
    public function validate(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new InvalidArgumentException('JWT: malformed token structure');
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        // Verify signature
        $expectedSig = $this->sign("{$headerB64}.{$payloadB64}");
        if (!hash_equals($expectedSig, $signatureB64)) {
            throw new RuntimeException('JWT: invalid signature');
        }

        // Decode payload
        $payload = json_decode(
            $this->base64UrlDecode($payloadB64),
            associative: true
        );

        if (!$payload || json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('JWT: payload decode failed');
        }

        // Check expiry
        if (!isset($payload['exp']) || time() > $payload['exp']) {
            throw new RuntimeException('JWT: token has expired');
        }

        return $payload;
    }

    // --------------------------------------------------------
    //  getPayload()
    //  Decodes payload WITHOUT verifying signature.
    //  Use only for reading non-sensitive claims after validate().
    // --------------------------------------------------------
    public function getPayload(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new InvalidArgumentException('JWT: malformed token');
        }

        $payload = json_decode(
            $this->base64UrlDecode($parts[1]),
            associative: true
        );

        return $payload ?? [];
    }

    // --------------------------------------------------------
    //  hashToken()
    //  SHA-256 hash of a refresh token for DB storage.
    //  Never store raw refresh tokens in DB.
    // --------------------------------------------------------
    public function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    // ========================================================
    //  PRIVATE HELPERS
    // ========================================================

    private function encode(array $payload): string
    {
        $header    = $this->base64UrlEncode(json_encode([
            'alg' => 'HS256',
            'typ' => 'JWT',
        ]));
        $payload   = $this->base64UrlEncode(json_encode($payload));
        $signature = $this->sign("{$header}.{$payload}");

        return "{$header}.{$payload}.{$signature}";
    }

    private function sign(string $data): string
    {
        return $this->base64UrlEncode(
            hash_hmac('sha256', $data, $this->secret, binary: true)
        );
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 4 - strlen($data) % 4));
    }
}
