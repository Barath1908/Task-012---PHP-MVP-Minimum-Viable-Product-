<?php

require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Security/AES.php';

class PrescriptionService
{
    private PDO $db;
    private AES $aes;

    public function __construct()
    {
        $this->db  = Database::getConnection();
        $this->aes = new AES();
    }

    // CREATE PRESCRIPTION
    
    public function createPrescription(
        array $data
    ): array {

        $medications = $this->aes->encrypt(
            $data['medications']
        );

        $instructions = $this->aes->encrypt(
            $data['instructions'] ?? ''
        );

        $stmt = $this->db->prepare("
            INSERT INTO prescriptions
            (
                appointment_id,
                patient_id,
                provider_id,
                medications,
                instructions,
                status
            )
            VALUES
            (
                ?, ?, ?, ?, ?, ?
            )
        ");

        $stmt->execute([
            $data['appointment_id'] ?? null,
            $data['patient_id'],
            $data['provider_id'],
            $medications,
            $instructions,
            INV_ISSUED
        ]);

        return [
            'prescription_id' => (int)$this->db->lastInsertId(),
            'status'          => 'issued'
        ];
    }

    // GET PRESCRIPTION

    public function getPrescription(
        int $prescriptionId
    ): array {

        $stmt = $this->db->prepare("
            SELECT *
            FROM prescriptions
            WHERE
                id = ?
                AND deleted_at IS NULL
        ");

        $stmt->execute([
            $prescriptionId
        ]);

        $prescription = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$prescription) {
            throw new RuntimeException(
                'Prescription not found.',
                HTTP_NOT_FOUND
            );
        }

        $prescription['medications']
            = $this->aes->decrypt(
                $prescription['medications']
            );

        $prescription['instructions']
            = $this->aes->decrypt(
                $prescription['instructions']
            );

        return $prescription;
    }

    // VERIFY PRESCRIPTION
    
    public function verifyPrescription(
        int $prescriptionId,
        int $userId
    ): array {

        $stmt = $this->db->prepare("
            SELECT *
            FROM prescriptions
            WHERE
                id = ?
                AND deleted_at IS NULL
        ");

        $stmt->execute([
            $prescriptionId
        ]);

        $prescription = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$prescription) {
            throw new RuntimeException(
                'Prescription not found.'
            );
        }

        $stmt = $this->db->prepare("
            UPDATE prescriptions
            SET
                status = 'verified',
                pharmacist_id = ?,
                updated_at = NOW()
            WHERE
                id = ?
        ");

        $stmt->execute([
            $userId,
            $prescriptionId
        ]);

        return [
            'prescription_id' => $prescriptionId,
            'status'          => 'verified'
        ];
    }

    // DISPENSE PRESCRIPTION

    public function dispensePrescription(
        int $prescriptionId,
        int $userId
    ): array {

        $stmt = $this->db->prepare("
            SELECT *
            FROM prescriptions
            WHERE
                id = ?
                AND deleted_at IS NULL
        ");

        $stmt->execute([
            $prescriptionId
        ]);

        $prescription = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$prescription) {
            throw new RuntimeException(
                'Prescription not found.'
            );
        }

        $stmt = $this->db->prepare("
            UPDATE prescriptions
            SET
                status = 'dispensed',
                pharmacist_id = ?,
                dispensed_at = NOW(),
                updated_at = NOW()
            WHERE
                id = ?
        ");

        $stmt->execute([
            $userId,
            $prescriptionId
        ]);

        return [
            'prescription_id' => $prescriptionId,
            'status'          => 'dispensed'
        ];
    }
}