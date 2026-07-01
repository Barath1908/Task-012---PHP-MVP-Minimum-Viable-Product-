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
    public function createStaff(array $data): array
    {
        $email = strtolower(trim($data['email']));
        $emailHash = hash('sha256', $email);

        // check duplicate email
        $stmt = $this->db->prepare("
            SELECT id FROM users
            WHERE email_hash = ?
            AND deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute([$emailHash]);

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

        // Prevent patient from being created as staff
        if (strtolower($data['role']) === 'patient') {
            throw new RuntimeException(
                'Patient role cannot be created as staff.',
                HTTP_BAD_REQUEST
            );
        }

        $passwordHash = Hash::make($data['password']);

        // create user
        $stmt = $this->db->prepare("
            INSERT INTO users (
                role_id,
                first_name,
                last_name,
                email,
                email_hash,
                phone,
                password_hash,
                is_active
            ) VALUES (
                :role_id,
                :first_name,
                :last_name,
                :email,
                :email_hash,
                :phone,
                :password_hash,
                1
            )
        ");

        $stmt->execute([
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
                user_id,
                specialization,
                qualification,
                license_number,
                is_active
            ) VALUES (
                ?, ?, ?, ?, 1
            )
        ");

        $stmt->execute([
            $userId,
            $data['specialization'] ?? null,
            $data['qualification'] ?? null,
            $data['license_number'] ?? null
        ]);

        return $this->getStaffById($userId);
    }

    // LIST STAFF
    public function getStaff(): array
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
            WHERE u.deleted_at IS NULL
            AND s.deleted_at IS NULL
            ORDER BY u.id DESC
        ");

        $stmt->execute();
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
    public function getStaffById(int $userId): array
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
            AND u.deleted_at IS NULL
            AND s.deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute([$userId]);
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
    public function updateStaff(int $id, array $data): array
    {
        $this->getStaffById($id);

        // Update staff table fields
        $stmt = $this->db->prepare("
            UPDATE staff
            SET
                specialization = :specialization,
                qualification = :qualification,
                license_number = :license_number,
                is_active = :is_active
            WHERE user_id = :user_id
        ");

        $stmt->execute([
            ':specialization' => $data['specialization'] ?? null,
            ':qualification'  => $data['qualification'] ?? null,
            ':license_number' => $data['license_number'] ?? null,
            ':is_active'       => isset($data['is_active']) ? (int)$data['is_active'] : 1,
            ':user_id'        => $id
        ]);

        // Update user table fields
        $roleId = null;
        if (isset($data['role'])) {
            $stmt = $this->db->prepare("SELECT id FROM roles WHERE name = ? LIMIT 1");
            $stmt->execute([$data['role']]);
            $role = $stmt->fetch();
            if ($role) {
                $roleId = $role['id'];
            }
        }

        $fields = [];
        $params = [];

        if (isset($data['first_name'])) {
            $fields[] = 'first_name = :first_name';
            $params[':first_name'] = $this->aes->encrypt($data['first_name']);
        }
        if (isset($data['last_name'])) {
            $fields[] = 'last_name = :last_name';
            $params[':last_name'] = $this->aes->encrypt($data['last_name']);
        }
        if (isset($data['phone'])) {
            $fields[] = 'phone = :phone';
            $params[':phone'] = $this->aes->encrypt($data['phone']);
        }
        if (isset($data['is_active'])) {
            $fields[] = 'is_active = :is_active';
            $params[':is_active'] = (int)$data['is_active'];
        }
        if (isset($data['email'])) {
            $email = strtolower(trim($data['email']));
            $emailHash = hash('sha256', $email);
            
            // Check if email already exists for a different user
            $stmt = $this->db->prepare("
                SELECT id FROM users
                WHERE email_hash = ? AND id != ? AND deleted_at IS NULL
                LIMIT 1
            ");
            $stmt->execute([$emailHash, $id]);
            if ($stmt->fetch()) {
                throw new RuntimeException('Email already exists.', HTTP_CONFLICT);
            }
            
            $fields[] = 'email = :email';
            $params[':email'] = $this->aes->encrypt($email);
            $fields[] = 'email_hash = :email_hash';
            $params[':email_hash'] = $emailHash;
        }
        if ($roleId !== null) {
            $fields[] = 'role_id = :role_id';
            $params[':role_id'] = $roleId;
        }

        if (!empty($fields)) {
            $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = :user_id";
            $params[':user_id'] = $id;
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        }

        return $this->getStaffById($id);
    }

    // DELETE STAFF (SOFT DELETE)
    public function deleteStaff(int $id): void
    {
        $this->getStaffById($id);

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
