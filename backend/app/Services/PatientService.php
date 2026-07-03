<?php

require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Security/AES.php';
require_once __DIR__ . '/../Security/Hash.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';

class PatientService {
    private PDO $db;
    private AES $aes;

    public function __construct() {
        $this->db  = Database::getConnection();
        $this->aes = new AES();
    }

    /**
     * Helper to safely encrypt any optional string field.
     */
    private function encryptField(?string $value): ?string {
        if ($value === null || trim($value) === '') {
            return null;
        }
        return $this->aes->encrypt(trim($value));
    }

    public function createPatient(array $data): int 
    {
        $this->db->beginTransaction();
        try {
            // 1. Resolve role ID for 'patient'
            $roleStmt = $this->db->prepare("SELECT id FROM roles WHERE name = 'patient' LIMIT 1");
            $roleStmt->execute();
            $roleId = $roleStmt->fetchColumn();
            if (!$roleId) {
                $roleId = 4; // fallback
            }

            // 2. Hash password (optional, default to generated if blank)
            $password = !empty($data['password']) ? $data['password'] : 'Patient@123';
            $passwordHash = Hash::make($password);

            // 3. Prepare user fields
            $email = strtolower(trim($data['email'] ?? ''));
            $emailHash = hash('sha256', $email);

            // 4. Insert into users table
            $userStmt = $this->db->prepare("
                INSERT INTO users
                    (role_id, first_name, last_name, email, email_hash, phone, password_hash, is_active, created_at, updated_at)
                VALUES
                    (:role_id, :first_name, :last_name, :email, :email_hash, :phone, :password_hash, 1, NOW(), NOW())
            ");

            $userStmt->execute([
                ':role_id'       => $roleId,
                ':first_name'    => $this->encryptField($data['first_name'] ?? ''),
                ':last_name'     => $this->encryptField($data['last_name'] ?? ''),
                ':email'         => $this->encryptField($email),
                ':email_hash'    => $emailHash,
                ':phone'         => !empty($data['phone']) ? $this->encryptField($data['phone']) : null,
                ':password_hash' => $passwordHash,
            ]);

            $userId = (int)$this->db->lastInsertId();

            // 5. Insert into patients table
            $patientStmt = $this->db->prepare("
                INSERT INTO patients 
                    (user_id, first_name, last_name, date_of_birth, 
                     age, gender, phone, email, address, blood_group, allergies, 
                     medical_history, emergency_contact, is_active, created_at, updated_at) 
                VALUES 
                    (:user_id, :first_name, :last_name, :date_of_birth, 
                     :age, :gender, :phone, :email, :address, :blood_group, :allergies, 
                     :medical_history, :emergency_contact, 1, NOW(), NOW())
            ");

            $patientStmt->execute([
                ':user_id'           => $userId,
                ':first_name'        => $this->encryptField($data['first_name'] ?? ''),
                ':last_name'         => $this->encryptField($data['last_name'] ?? ''),
                ':date_of_birth'     => $this->encryptField($data['date_of_birth'] ?? ''),
                ':age'               => $this->encryptField(isset($data['age']) ? (string)$data['age'] : ''),
                ':gender'            => $this->encryptField($data['gender'] ?? ''),
                ':phone'             => $this->encryptField($data['phone'] ?? ''),
                ':email'             => $this->encryptField($email),
                ':address'           => $this->encryptField($data['address'] ?? ''),
                ':blood_group'       => $this->encryptField($data['blood_group'] ?? ''),
                ':allergies'         => $this->encryptField($data['allergies'] ?? ''),
                ':medical_history'   => $this->encryptField($data['medical_history'] ?? ''),
                ':emergency_contact' => $this->encryptField($data['emergency_contact'] ?? ''),
            ]);

            $patientId = (int)$this->db->lastInsertId();

            $this->db->commit();
            return $patientId;

        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getAllPatients(): array 
    {
        $user = AuthMiddleware::user();
        $role = $user['role'] ?? null;
        $userId = $user['user_id'] ?? null;

        if ($role === 'patient') {
            $stmt = $this->db->prepare(
                "SELECT * FROM patients WHERE user_id = ? AND deleted_at IS NULL"
            );
            $stmt->execute([$userId]);
        } else {
            $stmt = $this->db->prepare(
                "SELECT * FROM patients WHERE deleted_at IS NULL"
            );
            $stmt->execute();
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) 
        {
            $row = $this->decryptPatientFields($row);
        }
        return $rows;
    }
    
    public function getPatientById(int $id): ?array 
    {
        $stmt = $this->db->prepare("
            SELECT * FROM patients
            WHERE id = ? AND deleted_at IS NULL LIMIT 1
        ");
        $stmt->execute([$id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$patient) {
            return null;
        }
        
        return $this->decryptPatientFields($patient);
    }

    public function updatePatient(int $id, array $data): bool 
    {
        $this->db->beginTransaction();
        try {
            // Find user_id linked to the patient
            $stmt = $this->db->prepare("SELECT user_id FROM patients WHERE id = ?");
            $stmt->execute([$id]);
            $pUserId = $stmt->fetchColumn();

            if ($pUserId) {
                // Update users record
                $email = strtolower(trim($data['email'] ?? ''));
                $emailHash = hash('sha256', $email);

                $userStmt = $this->db->prepare("
                    UPDATE users SET
                        first_name = :first_name,
                        last_name = :last_name,
                        email = :email,
                        email_hash = :email_hash,
                        phone = :phone,
                        updated_at = NOW()
                    WHERE id = :user_id
                ");

                $userStmt->execute([
                    ':user_id'    => $pUserId,
                    ':first_name' => $this->encryptField($data['first_name'] ?? ''),
                    ':last_name'  => $this->encryptField($data['last_name'] ?? ''),
                    ':email'      => $this->encryptField($email),
                    ':email_hash' => $emailHash,
                    ':phone'      => !empty($data['phone']) ? $this->encryptField($data['phone']) : null,
                ]);
            }

            // Update patients record
            $patientStmt = $this->db->prepare("
                UPDATE patients SET 
                    first_name = :first_name, last_name = :last_name,
                    date_of_birth = :date_of_birth, 
                    age = :age, gender = :gender, phone = :phone, 
                    email = :email, address = :address, 
                    blood_group = :blood_group, allergies = :allergies,
                    medical_history = :medical_history, 
                    emergency_contact = :emergency_contact,
                    updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL
            ");

            $patientStmt->execute([
                ':id'                => $id,
                ':first_name'        => $this->encryptField($data['first_name'] ?? ''),
                ':last_name'         => $this->encryptField($data['last_name'] ?? ''),
                ':date_of_birth'     => $this->encryptField($data['date_of_birth'] ?? ''),
                ':age'               => $this->encryptField(isset($data['age']) ? (string)$data['age'] : ''),
                ':gender'            => $this->encryptField($data['gender'] ?? ''),
                ':phone'             => $this->encryptField($data['phone'] ?? ''),
                ':email'             => $this->encryptField($data['email'] ?? ''),
                ':address'           => $this->encryptField($data['address'] ?? ''),
                ':blood_group'       => $this->encryptField($data['blood_group'] ?? ''),
                ':allergies'         => $this->encryptField($data['allergies'] ?? ''),
                ':medical_history'   => $this->encryptField($data['medical_history'] ?? ''),
                ':emergency_contact' => $this->encryptField($data['emergency_contact'] ?? ''),
            ]);

            $this->db->commit();
            return true;

        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function deletePatient(int $id): bool 
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT user_id FROM patients WHERE id = ?");
            $stmt->execute([$id]);
            $pUserId = $stmt->fetchColumn();

            if ($pUserId) {
                // Soft delete user
                $this->db->prepare("UPDATE users SET deleted_at = NOW(), is_active = 0 WHERE id = ?")->execute([$pUserId]);
            }

            // Soft delete patient
            $this->db->prepare("UPDATE patients SET deleted_at = NOW(), is_active = 0 WHERE id = ?")->execute([$id]);

            $this->db->commit();
            return true;

        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Decrypts all encrypted fields back to plain text for API responses.
     */
    private function decryptPatientFields(array $row): array
    {
        $encryptedFields = [
            'first_name', 'last_name', 'date_of_birth', 'age', 'gender', 
            'phone', 'email', 'address', 'blood_group', 'allergies', 
            'medical_history', 'emergency_contact'
        ];

        foreach ($encryptedFields as $field) {
            if (!empty($row[$field])) {
                try {
                    $row[$field] = $this->aes->decrypt($row[$field]);
                } catch (Throwable $e) {
                    error_log("[PatientService] Decryption failed for field {$field}: " . $e->getMessage());
                }
            }
        }
        return $row;
    }
}