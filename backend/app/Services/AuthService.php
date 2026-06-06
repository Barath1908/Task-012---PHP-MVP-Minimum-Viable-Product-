<?php

// ============================================================
//  AuthService.php — Authentication Business Logic
//  Handles: register, login, logout, token refresh
//  Interacts directly with MySQL via PDO (no repository).
//  All DB queries are tenant-scoped.
// ============================================================

require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Security/JWT.php';
require_once __DIR__ . '/../Security/Hash.php';
require_once __DIR__ . '/../Security/CSRF.php';

class AuthService
{
    private PDO $db;
    private JWT $jwt;

    // --------------------------------------------------------
    public function __construct()
    {
        $this->db  = Database::getConnection();
        $this->jwt = new JWT();
    }

    // ========================================================
    //  REGISTER
    //  1. Validate tenant exists and is active
    //  2. Check email uniqueness within tenant
    //  3. Hash password
    //  4. Insert user
    //  5. Generate tokens → store refresh token in DB
    //  Returns: [access_token, refresh_token, user]
    // ========================================================
    public function register(array $data): array
    {
        $tenantId = (int)$data['tenant_id'];
        $roleId   = (int)$data['role_id'];
        $email    = strtolower(trim($data['email']));

        // 1. Tenant check
        $tenant = $this->findActiveTenant($tenantId);
        if (!$tenant) {
            throw new RuntimeException('Invalid or inactive tenant.', HTTP_BAD_REQUEST);
        }

        // 2. Email uniqueness within tenant
        if ($this->emailExistsInTenant($email, $tenantId)) {
            throw new RuntimeException('Email already registered in this tenant.', HTTP_CONFLICT);
        }

        // 3. Role exists check
        $role = $this->findRole($roleId);
        if (!$role) {
            throw new RuntimeException('Invalid role.', HTTP_BAD_REQUEST);
        }

        // 4. Hash password
        $passwordHash = Hash::make($data['password']);

        // 5. Insert user
        $stmt = $this->db->prepare("
            INSERT INTO users
                (tenant_id, role_id, first_name, last_name, email, phone, password_hash, created_by)
            VALUES
                (:tenant_id, :role_id, :first_name, :last_name, :email, :phone, :password_hash, NULL)
        ");

        $stmt->execute([
            ':tenant_id'     => $tenantId,
            ':role_id'       => $roleId,
            ':first_name'    => trim($data['first_name']),
            ':last_name'     => trim($data['last_name']),
            ':email'         => $email,
            ':phone'         => $data['phone'] ?? null,
            ':password_hash' => $passwordHash,
        ]);

        $userId = (int)$this->db->lastInsertId();

        // 6. Auto-insert into staff or patients table based on role
        if ($role['name'] !== ROLE_PATIENT) {
            // Admin, Provider, Nurse, Pharmacist, Receptionist → staff table
            $this->db->prepare("
                INSERT INTO staff (user_id, tenant_id, is_active)
                VALUES (:user_id, :tenant_id, 1)
            ")->execute([
                ':user_id'   => $userId,
                ':tenant_id' => $tenantId,
            ]);
        } else {
            // Patient → patients table
            // first_name and last_name required by patients table schema
            $this->db->prepare("
                INSERT INTO patients
                    (user_id, tenant_id, first_name, last_name, phone, email, is_active)
                VALUES
                    (:user_id, :tenant_id, :first_name, :last_name, :phone, :email, 1)
            ")->execute([
                ':user_id'    => $userId,
                ':tenant_id'  => $tenantId,
                ':first_name' => trim($data['first_name']),
                ':last_name'  => trim($data['last_name']),
                ':phone'      => $data['phone'] ?? null,
                ':email'      => $email,
            ]);
        }

        // 6. Load user with role name for token payload
        $user = $this->findUserById($userId);

        // 7. Generate tokens
        return $this->issueTokens($user);
    }

    // ========================================================
    //  LOGIN
    //  1. Find user by email + tenant
    //  2. Verify password
    //  3. Check user is active
    //  4. Rehash if needed
    //  5. Revoke old refresh tokens for this user
    //  6. Issue new tokens
    // ========================================================
    public function login(array $data): array
    {
        $email    = strtolower(trim($data['email']));
        $tenantId = (int)$data['tenant_id'];

        // 1. Find user
        $user = $this->findUserByEmailAndTenant($email, $tenantId);

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

        // 4. Rehash if bcrypt cost changed
        if (Hash::needsRehash($user['password_hash'])) {
            $newHash = Hash::make($data['password']);
            $this->db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                     ->execute([$newHash, $user['id']]);
        }

        // 5. Revoke all previous refresh tokens for this user
        $this->revokeAllRefreshTokens((int)$user['id']);

        // 6. Issue tokens
        return $this->issueTokens($user);
    }

    // ========================================================
    //  REFRESH TOKEN
    //  1. Validate refresh token JWT
    //  2. Check it exists in DB and is not revoked
    //  3. Check not expired
    //  4. Revoke old token
    //  5. Issue new access + refresh tokens
    // ========================================================
    public function refresh(string $rawToken): array
    {
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
        if (!$user || !(bool)$user['is_active']) {
            throw new RuntimeException('User not found or deactivated.', HTTP_UNAUTHORIZED);
        }

        // 5. Issue new tokens
        return $this->issueTokens($user);
    }

    // ========================================================
    //  LOGOUT
    //  1. Revoke all refresh tokens for user
    //  2. Clear access token from session
    //  3. Regenerate CSRF token
    // ========================================================
    public function logout(int $userId): void
    {
        $this->revokeAllRefreshTokens($userId);
        unset($_SESSION['access_token']);
        CSRF::clear();
    }

    // ========================================================
    //  PRIVATE HELPERS
    // ========================================================

    // Issue access + refresh tokens, store in session/DB
    private function issueTokens(array $user): array
    {
        $accessToken  = $this->jwt->generateAccessToken($user);
        $refreshToken = $this->jwt->generateRefreshToken($user);

        // Fix 5: Regenerate session ID before storing token
        // Prevents session fixation attacks after successful auth
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        // Store access token in PHP session
        $_SESSION['access_token'] = $accessToken;

        // Store hashed refresh token in DB
        $this->storeRefreshToken((int)$user['id'], $refreshToken);

        // Regenerate CSRF token on every new login/refresh
        // Note: csrf_token is NOT returned here — Response wrapper
        // already sends it in the outer envelope { csrf_token, payload }
        CSRF::regenerate();

        // Fix 3: csrf_token removed from data payload — no duplication
        return [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'user'          => [
                'id'        => $user['id'],
                'tenant_id' => $user['tenant_id'],
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
        return $stmt->fetch();
    }

    private function findUserByEmailAndTenant(string $email, int $tenantId): array|false
    {
        $stmt = $this->db->prepare("
            SELECT u.*, r.name AS role
            FROM users u
            JOIN roles r ON r.id = u.role_id
            WHERE u.email = ? AND u.tenant_id = ? AND u.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$email, $tenantId]);
        return $stmt->fetch();
    }

    private function emailExistsInTenant(string $email, int $tenantId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM users
            WHERE email = ? AND tenant_id = ? AND deleted_at IS NULL
        ");
        $stmt->execute([$email, $tenantId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function findActiveTenant(int $id): array|false
    {
        $stmt = $this->db->prepare("
            SELECT * FROM tenants WHERE id = ? AND is_active = 1 AND deleted_at IS NULL LIMIT 1
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    private function findRole(int $id): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM roles WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
}
