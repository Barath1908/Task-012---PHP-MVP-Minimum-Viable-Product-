<?php

require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Security/AES.php';
require_once __DIR__ . '/../Security/Hash.php';

class StaffService
{
    private PDO $db;
    private AES $aes;

    public function __construct()
    {
        $this->db  = Database::getConnection();
        $this->aes = new AES();
    }

    // CREATE STAFF
    public function createStaff(array $data, array $authUser): array
    {
        $tenantId  = (int)$authUser['tenant_id'];

        $email = strtolower(trim($data['email']));
        $emailHash = hash('sha256', $email);

        // check duplicate email
        $stmt = $this->db->prepare("
            SELECT id FROM users
            WHERE email_hash = ?
            AND tenant_id = ?
            AND deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute([$emailHash, $tenantId]);

        if ($stmt->fetch()) {
            throw new RuntimeException('Email already exists.', HTTP_CONFLICT);
        }

        // get role id
        $stmt = $this->db->prepare("
            SELECT id FROM roles WHERE name = ? LIMIT 1
        ");

        $stmt->execute([$data['role']]);
        $role = $stmt->fetch();

        if (!$role) {
            throw new RuntimeException('Invalid role.', HTTP_BAD_REQUEST);
        }

        $passwordHash = Hash::make($data['password']);

        // create user
        $stmt = $this->db->prepare("
            INSERT INTO users (
                tenant_id,
                role_id,
                first_name,
                last_name,
                email,
                email_hash,
                phone,
                password_hash
            ) VALUES (
                :tenant_id,
                :role_id,
                :first_name,
                :last_name,
                :email,
                :email_hash,
                :phone,
                :password_hash
            )
        ");

        $stmt->execute([
            ':tenant_id'     => $tenantId,
            ':role_id'       => $role['id'],
            ':first_name'    => $this->aes->encrypt($data['first_name']),
            ':last_name'     => $this->aes->encrypt($data['last_name']),
            ':email'         => $this->aes->encrypt($email),
            ':email_hash'    => $emailHash,
            ':phone'         => $this->aes->encrypt($data['phone']),
            ':password_hash' => $passwordHash
        ]);

        $userId = (int)$this->db->lastInsertId();

        // create staff
        $stmt = $this->db->prepare("
            INSERT INTO staff (
                tenant_id,
                user_id,
                specialization,
                qualification,
                license_number
            ) VALUES (
                ?, ?, ?, ?, ?
            )
        ");

        $stmt->execute([
            $tenantId,
            $userId,
            $data['specialization'] ?? null,
            $data['qualification'] ?? null,
            $data['license_number'] ?? null
        ]);

        return $this->getStaffById($userId, $tenantId);
    }

    // LIST STAFF
    public function getStaff(int $tenantId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                u.id,
                u.first_name,
                u.last_name,
                u.email,
                u.phone,
                r.name AS role,
                s.specialization,
                s.qualification,
                s.license_number,
                s.is_active
            FROM users u
            INNER JOIN staff s ON s.user_id = u.id
            INNER JOIN roles r ON r.id = u.role_id
            WHERE u.tenant_id = ?
            AND u.deleted_at IS NULL
            AND s.deleted_at IS NULL
            ORDER BY u.id DESC
        ");

        $stmt->execute([$tenantId]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['first_name'] = $this->safeDecrypt($row['first_name']);
            $row['last_name']  = $this->safeDecrypt($row['last_name']);
            $row['email']      = $this->safeDecrypt($row['email']);
            $row['phone']      = $this->safeDecrypt($row['phone']);
        }

        return $rows;
    }

    // VIEW STAFF
    public function getStaffById(int $userId, int $tenantId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                u.id,
                u.first_name,
                u.last_name,
                u.email,
                u.phone,
                r.name AS role,
                s.specialization,
                s.qualification,
                s.license_number,
                s.is_active
            FROM users u
            INNER JOIN staff s ON s.user_id = u.id
            INNER JOIN roles r ON r.id = u.role_id
            WHERE u.id = ?
            AND u.tenant_id = ?
            AND u.deleted_at IS NULL
            AND s.deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute([$userId, $tenantId]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new RuntimeException('Staff not found.', HTTP_NOT_FOUND);
        }

        $row['first_name'] = $this->safeDecrypt($row['first_name']);
        $row['last_name']  = $this->safeDecrypt($row['last_name']);
        $row['email']      = $this->safeDecrypt($row['email']);
        $row['phone']      = $this->safeDecrypt($row['phone']);

        return $row;
    }

    // UPDATE STAFF
    public function updateStaff(int $id, array $data, array $authUser): array
    {
        $tenantId = (int)$authUser['tenant_id'];

        $this->getStaffById($id, $tenantId);

        $stmt = $this->db->prepare("
            UPDATE staff
            SET
                specialization = ?,
                qualification = ?,
                license_number = ?
            WHERE user_id = ?
        ");

        $stmt->execute([
            $data['specialization'] ?? null,
            $data['qualification'] ?? null,
            $data['license_number'] ?? null,
            $id
        ]);

        return $this->getStaffById($id, $tenantId);
    }

    // DELETE STAFF (SOFT DELETE)
    public function deleteStaff(int $id, array $authUser): void
    {
        $tenantId = (int)$authUser['tenant_id'];

        $this->getStaffById($id, $tenantId);

        $this->db->prepare("
            UPDATE users SET deleted_at = NOW()
            WHERE id = ?
        ")->execute([$id]);

        $this->db->prepare("
            UPDATE staff SET deleted_at = NOW()
            WHERE user_id = ?
        ")->execute([$id]);
    }

    // SAFE DECRYPT
    private function safeDecrypt(?string $value): ?string
    {
        try {
            return $this->aes->decrypt($value);
        } catch (Throwable $e) {
            return $value;
        }
    }
}