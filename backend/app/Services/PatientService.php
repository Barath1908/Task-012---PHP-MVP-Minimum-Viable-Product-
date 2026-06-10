<?php

require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Security/AES.php';

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

    public function createPatient(array $data, int $userId, int $tenantId): int {
        $stmt = $this->db->prepare("INSERT INTO patients (tenant_id, user_id, first_name, last_name, date_of_birth, age, gender, phone, email,address, blood_group, allergies, medical_history, emergency_contact, is_active) VALUES (:tenant_id, :user_id, :first_name, :last_name, :date_of_birth, :age, :gender, :phone, :email, :address, :blood_group, :allergies, :medical_history, :emergency_contact, 1)
        ");

        $stmt->execute([
            ':tenant_id'         => $tenantId,
            ':user_id'           => $userId,
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

        return (int)$this->db->lastInsertId();
    }

    public function getAllPatients(int $tenantId): array {
        $stmt = $this->db->prepare("SELECT * FROM patients WHERE tenant_id = ? AND deleted_at IS NULL");
        $stmt->execute([$tenantId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row = $this->decryptPatientFields($row);
        }
        return $rows;
    }
    
    /**
     * Fetches a single patient record by ID, isolated by tenant context.
     */
    public function getPatientById(int $id, int $tenantId): ?array {
        // Use our instantiated driver connection safely
        $stmt = $this->db->prepare("
            SELECT * FROM patients 
            WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL 
            LIMIT 1
        ");
        $stmt->execute([$id, $tenantId]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$patient) {
            return null;
        }
        
        // Re-use our centralized helper to decrypt all 12 fields seamlessly
        return $this->decryptPatientFields($patient);
    }

    public function updatePatient(int $id, array $data, int $userId, int $tenantId): bool {
        $stmt = $this->db->prepare("
            UPDATE patients SET 
                first_name = :first_name, last_name = :last_name, date_of_birth = :date_of_birth, 
                age = :age, gender = :gender, phone = :phone, email = :email, address = :address, 
                blood_group = :blood_group, allergies = :allergies, medical_history = :medical_history, 
                emergency_contact = :emergency_contact, updated_at = NOW()
            WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL
        ");

        return $stmt->execute([
            ':id'                => $id,
            ':tenant_id'         => $tenantId,
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
    }

    public function deletePatient(int $id, int $userId, int $tenantId): bool {
        $stmt = $this->db->prepare("
            UPDATE patients SET deleted_at = NOW() WHERE id = :id AND tenant_id = :tenant_id
        ");
        return $stmt->execute([
            ':id'         => $id,
            ':tenant_id'  => $tenantId
        ]);
    }

    /**
     * Decrypts all 12 custom encrypted fields back to plain text for API responses.
     */
    private function decryptPatientFields(array $row): array {
        $encryptedFields = [
            'first_name', 'last_name', 'date_of_birth', 'age', 'gender', 
            'phone', 'email', 'address', 'blood_group', 'allergies', 
            'medical_history', 'emergency_contact'
        ];

        foreach ($encryptedFields as $field) {
            if (!empty($row[$field])) {
                try {
                    // Uses our object property safely without throwing static errors
                    $row[$field] = $this->aes->decrypt($row[$field]);
                } catch (Throwable $e) {
                    error_log("[PatientService] Decryption failed for field {$field}: " . $e->getMessage());
                }
            }
        }
        return $row;
    }
}