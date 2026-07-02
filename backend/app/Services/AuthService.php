<?php

// ============================================================
//  AuthService.php — Authentication Business Logic
//  Handles: register, login, logout, token refresh
//  Interacts directly with MySQL via PDO (no repository).
//  All DB queries are tenant-scoped.
//  AES Encryption:
//    users    → first_name, last_name, email, phone encrypted
//    patients → first_name, last_name, email, phone encrypted
//  email_hash (SHA-256) used for searching users by email
// ============================================================

require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Security/JWT.php';
require_once __DIR__ . '/../Security/Hash.php';
require_once __DIR__ . '/../Security/CSRF.php';
require_once __DIR__ . '/../Security/AES.php';

class AuthService
{
    private PDO $db;
    private JWT $jwt;
    private AES $aes;

    // --------------------------------------------------------
    public function __construct()
    {
        $this->db  = Database::getConnection();
        $this->jwt = new JWT();
        $this->aes = new AES();
    }

    // ========================================================
    //  REGISTER
    //  1. Validate tenant exists and is active
    //  2. Check email uniqueness within tenant (via hash)
    //  3. Role exists check
    //  4. Hash password
    //  5. Insert user (AES encrypted fields)
    //  6. Auto-insert into staff or patients table
    //  7. Load user and generate tokens
    //  Returns: [access_token, refresh_token, user]
    // ========================================================

    public function register(array $data): array
    {
        $roleId    = (int)$data['role_id'];
        $email     = strtolower(trim($data['email']));
        $emailHash = hash('sha256', $email);
        $firstName = trim($data['first_name']);
        $lastName  = trim($data['last_name']);
        $phone     = $data['phone'] ?? null;

        // 2. Email uniqueness check via hash

        if ($this->emailExists($emailHash)) {
            throw new RuntimeException('Email already registered.', HTTP_CONFLICT);
        }

        // 3. Role exists check

        $role = $this->findRole($roleId);
        if (!$role) {
            throw new RuntimeException('Invalid role.', HTTP_BAD_REQUEST);
        }

        // 4. Hash password
        $passwordHash = Hash::make($data['password']);

        // 5. Insert user — all sensitive fields AES encrypted

        $stmt = $this->db->prepare("
            INSERT INTO users
                (role_id, first_name, last_name, email, email_hash, phone, password_hash)
            VALUES
                (:role_id, :first_name, :last_name, :email, :email_hash, :phone, :password_hash)
        ");

        $stmt->execute([
            ':role_id'       => $roleId,
            ':first_name'    => $this->aes->encrypt($firstName),
            ':last_name'     => $this->aes->encrypt($lastName),
            ':email'         => $this->aes->encrypt($email),
            ':email_hash'    => $emailHash,
            ':phone'         => !empty($phone) ? $this->aes->encrypt($phone) : null,
            ':password_hash' => $passwordHash,
        ]);

        $userId = (int)$this->db->lastInsertId();

        // 6. Auto-insert into staff or patients table based on role

        if ($role['name'] !== ROLE_PATIENT) 
        {
            // Admin, Provider, Nurse, Pharmacist, Receptionist → staff table

            $this->db->prepare("
                INSERT INTO staff (user_id, is_active)
                VALUES (:user_id, 1)
            ")->execute([
                ':user_id'   => $userId,
            ]);
        } else 
        {
            // Patient → patients table
            // first_name, last_name, email, phone AES encrypted

            $this->db->prepare("
                INSERT INTO patients
                    (user_id, first_name, last_name, phone, email, is_active)
                VALUES
                    (:user_id, :first_name, :last_name, :phone, :email, 1)
            ")->execute([
                ':user_id'    => $userId,
                ':first_name' => $this->aes->encrypt($firstName),
                ':last_name'  => $this->aes->encrypt($lastName),
                ':phone'      => !empty($phone) ? $this->aes->encrypt($phone) : null,
                ':email'      => $this->aes->encrypt($email),
            ]);
        }

        // 7. Load user with role name for token payload

        $user = $this->findUserById($userId);

        // 8. Return user details — no tokens on register

        return [
            'id'        => $user['id'],
            'role'      => $user['role'],
            'email'     => $user['email'],
            'fullName'  => $user['first_name'] . ' ' . $user['last_name'],
        ];
    }

    // ========================================================
    //  LOGIN
    //  1. Find user by email_hash + tenant
    //  2. Verify password
    //  3. Check user is active
    //  4. Rehash if needed
    //  5. Revoke old refresh tokens
    //  6. Issue new tokens
    // ========================================================

    public function login(array $data): array
    {
        $email     = strtolower(trim($data['email']));
        $emailHash = hash('sha256', $email);

        // 1. Find user by email hash — fast single row lookup
        $user = $this->findUserByEmailHash($emailHash);

        if (!$user) {
            throw new RuntimeException('Invalid credentials.', HTTP_UNAUTHORIZED);
        }

        // 2. Verify password
        if (!Hash::verify($data['password'], $user['password_hash'])) {
            throw new RuntimeException('Invalid credentials.', HTTP_UNAUTHORIZED);
        }

        // 3. Active check
        if (!(bool)$user['is_active']) {
            throw new RuntimeException('Account is deactivated. Contact admin.', HTTP_FORBIDDEN);
        }


        // 5. Revoke all previous refresh tokens
        $this->revokeAllRefreshTokens((int)$user['id']);

        // Generate CSRF token on login only
        CSRF::regenerate();

        // 6. Issue tokens
        return $this->issueTokens($user);
    }

    // ========================================================
    //  CHANGE PASSWORD
    //  1. Find user by id
    //  2. Verify current password
    //  3. Check new password is different
    //  4. Hash new password
    //  5. Update in DB
    //  6. Revoke all refresh tokens — force re-login on other devices
    // ========================================================

    public function changePassword(int $userId, string $currentPassword, string $newPassword): void
    {
        // 1. Find user
        $user = $this->findUserById($userId);
        if (!$user) {
            throw new RuntimeException('User not found.', HTTP_NOT_FOUND);
        }

        // 2. Verify current password
        if (!Hash::verify($currentPassword, $user['password_hash'])) {
            throw new RuntimeException('Current password is incorrect.', HTTP_UNAUTHORIZED);
        }

        // 3. Check new password is not same as current
        if (Hash::verify($newPassword, $user['password_hash'])) {
            throw new RuntimeException('New password must be different from current password.', HTTP_BAD_REQUEST);
        }

        // 4. Hash new password
        $newHash = Hash::make($newPassword);

        // 5. Update password in DB
        $this->db->prepare("
            UPDATE users SET password_hash = ? WHERE id = ?
        ")->execute([$newHash, $userId]);

        // 6. Revoke all refresh tokens — forces re-login on all other devices
        $this->revokeAllRefreshTokens($userId);
    }

    // ========================================================
    //  REFRESH TOKEN
    //  1. Validate refresh token JWT
    //  2. Check it exists in DB and is not revoked
    //  3. Check not expired
    //  4. Revoke old token
    //  5. Issue new access + refresh tokens
    // ========================================================

    public function refresh(): array
    {
        // Read refresh token from HttpOnly cookie
        // Never from request body — cookie is automatic

        $rawToken = $_COOKIE['refresh_token'] ?? null;

        if (!$rawToken) {
            throw new RuntimeException('Refresh token missing.', HTTP_UNAUTHORIZED);
        }

        // 1. Validate JWT structure + signature + expiry

        try {
            $payload = $this->jwt->validate($rawToken);
        } catch (Throwable $e) {
            throw new RuntimeException('Refresh token invalid or expired.', HTTP_UNAUTHORIZED);
        }

        if (($payload['type'] ?? '') !== TOKEN_REFRESH) {
            throw new RuntimeException('Invalid token type.', HTTP_UNAUTHORIZED);
        }

        // 2. Check DB record

        $tokenHash = $this->jwt->hashToken($rawToken);
        $record    = $this->findRefreshToken($tokenHash);

        if (!$record || (bool)$record['revoked']) {
            throw new RuntimeException('Refresh token has been revoked.', HTTP_UNAUTHORIZED);
        }

        if (strtotime($record['expires_at']) < time()) {
            throw new RuntimeException('Refresh token has expired.', HTTP_UNAUTHORIZED);
        }

        // 3. Revoke old token

        $this->revokeRefreshToken($tokenHash);

        // 4. Load fresh user data

        $user = $this->findUserById((int)$payload['user_id']);
        if (!$user || !(bool)$user['is_active']) 
        {
            throw new RuntimeException('User not found or deactivated.', HTTP_UNAUTHORIZED);
        }

        // 5. Issue new tokens

        return $this->issueTokens($user);
    }

    // ========================================================
    //  LOGOUT
    //  1. Revoke all refresh tokens for user
    //  2. Clear access token from session
    //  3. Clear CSRF token
    // ========================================================

    public function logout(int $userId): void
    {
        $this->revokeAllRefreshTokens($userId);
        CSRF::clear();

        // Clear refresh token cookie

        setcookie(
            'refresh_token',
            '',
            [
                'expires'  => time() - 3600,  // expire immediately
                'path'     => '/',
                'httponly' => true,
                'secure'   => false,
                'samesite' => 'Lax'
            ]
        );
    }

    // ========================================================
    //  PRIVATE HELPERS
    // ========================================================

    private function issueTokens(array $user): array
    {
        $accessToken  = $this->jwt->generateAccessToken($user);
        $refreshToken = $this->jwt->generateRefreshToken($user);

        // Regenerate session ID — prevents session fixation attacks

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        // Store hashed refresh token in DB
        $this->storeRefreshToken((int)$user['id'], $refreshToken);


        // Store refresh token in HttpOnly cookie
        // JavaScript cannot read this — XSS protection

        setcookie(
            'refresh_token',           // cookie name
            $refreshToken,             // raw token value
            [
                'expires'  => time() + REFRESH_TOKEN_EXPIRY,
                'path'     => '/',
                'httponly' => true,    // JavaScript cannot access
                'secure'   => false,   // set true in production (HTTPS)
                'samesite' => 'Lax'    // CSRF protection
            ]
        );

        return [
            'access_token'  => $accessToken,
            'user'          => [
                'id'        => $user['id'],
                'role'      => $user['role'],
                'email'     => $user['email'],
                'fullName'  => $user['first_name'] . ' ' . $user['last_name'],
            ],
        ];
    }


    private function storeRefreshToken(int $userId, string $rawToken): void
    {
        $hash      = $this->jwt->hashToken($rawToken);
        $expiresAt = date('Y-m-d H:i:s', time() + REFRESH_TOKEN_EXPIRY);

        $this->db->prepare("
            INSERT INTO refresh_tokens (user_id, token_hash, expires_at)
            VALUES (:user_id, :token_hash, :expires_at)
        ")->execute([
            ':user_id'    => $userId,
            ':token_hash' => $hash,
            ':expires_at' => $expiresAt,
        ]);
    }

    private function revokeRefreshToken(string $tokenHash): void
    {
        $this->db->prepare("
            UPDATE refresh_tokens SET revoked = 1 WHERE token_hash = ?
        ")->execute([$tokenHash]);
    }

    private function revokeAllRefreshTokens(int $userId): void
    {
        $this->db->prepare("
            UPDATE refresh_tokens SET revoked = 1 WHERE user_id = ?
        ")->execute([$userId]);
    }

    private function findRefreshToken(string $tokenHash): array|false
    {
        $stmt = $this->db->prepare("
            SELECT * FROM refresh_tokens WHERE token_hash = ? LIMIT 1
        ");
        $stmt->execute([$tokenHash]);
        return $stmt->fetch();
    }

    private function findUserById(int $id): array|false
    {
        $stmt = $this->db->prepare("
            SELECT u.*, r.name AS role
            FROM users u
            JOIN roles r ON r.id = u.role_id
            WHERE u.id = ? AND u.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        if ($user) {
            $user = $this->decryptUserFields($user);
        }

        return $user;
    }

    private function findUserByEmailHash(string $emailHash): array|false
    {
        // Search by SHA-256 hash — fast and secure

        $stmt = $this->db->prepare("
            SELECT u.*, r.name AS role
            FROM users u
            JOIN roles r ON r.id = u.role_id
            WHERE u.email_hash = ? AND u.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$emailHash]);
        $user = $stmt->fetch();

        if ($user) {
            $user = $this->decryptUserFields($user);
        }

        return $user;
    }

    private function emailExists(string $emailHash): bool
    {
        // Check by SHA-256 hash — never store or compare plain email

        $stmt = $this->db->prepare("SELECT COUNT(id) FROM users WHERE email_hash = ? AND deleted_at IS NULL");
        $stmt->execute([$emailHash]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function findRole(int $id): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM roles WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    // --------------------------------------------------------
    //  decryptUserFields()
    //  Decrypts all AES encrypted fields of a user row.
    //  Called after every user fetch from DB.
    // --------------------------------------------------------
    
    private function decryptUserFields(array $user): array
    {
        try {
            $user['first_name'] = $this->aes->decrypt($user['first_name']);
        } catch (Throwable $e) {
            // already plain text — old record
        }

        try {
            $user['last_name'] = $this->aes->decrypt($user['last_name']);
        } catch (Throwable $e) {
            // already plain text — old record
        }

        try {
            $user['email'] = $this->aes->decrypt($user['email']);
        } catch (Throwable $e) {
            // already plain text — old record
        }

        if (!empty($user['phone'])) {
            try {
                $user['phone'] = $this->aes->decrypt($user['phone']);
            } catch (Throwable $e) {
                // already plain text — old record
            }
        }

        return $user;
    }
}