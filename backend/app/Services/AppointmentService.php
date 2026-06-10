<?php

require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Security/AES.php';

class AppointmentService {
    private PDO $db;
    private AES $aes;

    public function __construct() {
        $this->db  = Database::getConnection();
        $this->aes = new AES();
    }

    /**
     * Helper to safely encrypt target text fields
     */
    private function encryptField(?string $value): ?string {
        if ($value === null || trim($value) === '') {
            return null;
        }
        return $this->aes->encrypt(trim($value));
    }

    /**
     * CONFLICT VALIDATION ENGINE (Double Booking Blocker Logic)
     * Rewritten with unique placeholder names to prevent native PDO parameter mapping bugs (HY093).
     */
    private function hasSchedulingConflict(int $tenantId, int $providerId, string $scheduledAt, int $durationMinutes, ?int $excludeAppointmentId = null): bool {
        $startTime = $scheduledAt;
        $endTime = date('Y-m-d H:i:s', strtotime($scheduledAt . " + {$durationMinutes} minutes"));

        // Using unique parameter placeholders for each position to completely prevent HY093 bugs
        $sql = "SELECT COUNT(id) FROM appointments 
            WHERE tenant_id = :tenant_id 
              AND provider_id = :provider_id 
              AND deleted_at IS NULL
              AND (
                   (scheduled_at <= :start_time1 AND DATE_ADD(scheduled_at, INTERVAL duration_minutes MINUTE) > :start_time2) OR
                   (scheduled_at < :end_time1 AND DATE_ADD(scheduled_at, INTERVAL duration_minutes MINUTE) >= :end_time2) OR
                   (scheduled_at >= :start_time3 AND DATE_ADD(scheduled_at, INTERVAL duration_minutes MINUTE) <= :end_time3)
              )
        ";

        if ($excludeAppointmentId !== null) {
            $sql .= " AND id != :exclude_id";
        }

        $stmt = $this->db->prepare($sql);
        
        $params = [
            ':tenant_id'    => $tenantId,
            ':provider_id'  => $providerId,
            ':start_time1'  => $startTime,
            ':start_time2'  => $startTime,
            ':start_time3'  => $startTime,
            ':end_time1'    => $endTime,
            ':end_time2'    => $endTime,
            ':end_time3'    => $endTime
        ];

        if ($excludeAppointmentId !== null) {
            $params[':exclude_id'] = $excludeAppointmentId;
        }

        $stmt->execute($params);
        return ((int)$stmt->fetchColumn()) > 0;
    }

    public function createAppointment(array $data, int $userId, int $tenantId): int {
        $duration = $data['duration_minutes'] ?? 30;

        if ($this->hasSchedulingConflict($tenantId, $data['provider_id'], $data['scheduled_at'], $duration)) {
            throw new Exception("Scheduling conflict detected! The requested doctor slot is already filled.");
        }

        $stmt = $this->db->prepare("
            INSERT INTO appointments 
                (tenant_id, patient_id, provider_id, scheduled_at, duration_minutes, status, reason, notes)
            VALUES 
                (:tenant_id, :patient_id, :provider_id, :scheduled_at, :duration_minutes, :status, :reason, :notes)
        ");

        $stmt->execute([
            ':tenant_id'         => $tenantId,
            ':patient_id'        => $data['patient_id'],
            ':provider_id'       => $data['provider_id'],
            ':scheduled_at'      => $data['scheduled_at'],
            ':duration_minutes'  => $duration, 
            ':status'            => $data['status'] ?? 'Scheduled',
            ':reason'            => $this->encryptField($data['reason'] ?? ''), 
            ':notes'             => $this->encryptField($data['notes'] ?? '')
        
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * FETCH APPOINTMENTS WITH OPTIONAL CALENDAR RANGE FILTERS 
     */
    public function getAllAppointments(int $tenantId, int $userId, string $userRole, ?string $startDate = null, ?string $endDate = null): array {
        $sql = "SELECT * FROM appointments WHERE tenant_id = :tenant_id AND deleted_at IS NULL";

        //ROLE-BASED VISIBILITY FILTER
        if ($userRole === 'provider' || $userRole === 'doctor') {
            $sql .= " AND provider_id = :user_id";
        } else if ($userRole === 'patient') {
            $sql .= " AND patient_id = :user_id";
        }

        //DYNAMIC DATE RANGE FILTER
        if ($startDate !== null) {
            $sql .= " AND scheduled_at >= :start_date";
        }
        if ($endDate !== null) {
            $sql .= " AND scheduled_at <= :end_date";
        }

        $sql .= " ORDER BY scheduled_at ASC";

        $stmt = $this->db->prepare($sql);
        
        // Build precision binding parameters array context
        $params = [':tenant_id' => $tenantId];
        
        if ($userRole === 'provider' || $userRole === 'doctor' || $userRole === 'patient') {
            $params[':user_id'] = $userId;
        }
        if ($startDate !== null) {
            $params[':start_date'] = $startDate . " 00:00:00";
        }
        if ($endDate !== null) {
            $params[':end_date'] = $endDate . " 23:59:59";
        }

        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row = $this->decryptAppointmentFields($row);
        }
        return $rows;
    }

    /**
     * FETCH SINGLE APPOINTMENT BY ID
     */
    public function getAppointmentById(int $id, int $tenantId, int $userId, string $userRole): ?array {
        $sql = "
            SELECT 
                a.*, 
                p.first_name AS patient_first_name, 
                p.last_name AS patient_last_name, 
                p.phone AS patient_phone,
                p.email AS patient_email
            FROM appointments a
            LEFT JOIN patients p ON a.patient_id = p.id
            WHERE a.id = :id 
              AND a.tenant_id = :tenant_id 
              AND a.deleted_at IS NULL
        ";
        
        if ($userRole === 'provider' || $userRole === 'doctor') {
            $sql .= " AND a.provider_id = :user_id";
        } 
        else if ($userRole === 'patient') {
            $sql .= " AND a.patient_id = :user_id"; 
        }
        
        $sql .= " LIMIT 1";
        $stmt = $this->db->prepare($sql);
        
        $params = [
            ':id'        => $id,
            ':tenant_id' => $tenantId
        ];
        
        if ($userRole === 'provider' || $userRole === 'doctor' || $userRole === 'patient') {
            $params[':user_id'] = $userId;
        }
        
        $stmt->execute($params);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$appointment) {
            return null;
        }
        
        $appointment = $this->decryptAppointmentFields($appointment);
        
        $patientFields = ['patient_first_name', 'patient_last_name', 'patient_phone', 'patient_email'];
        foreach ($patientFields as $field) {
            if (!empty($appointment[$field])) {
                try {
                    $appointment[$field] = $this->aes->decrypt($appointment[$field]);
                } catch (Throwable $e) {
                    error_log("[AppointmentService] Decryption failed for linked field {$field}: " . $e->getMessage());
                }
            }
        }
        
        return $appointment;
    }

    public function updateAppointment(int $id, array $data, int $userId, int $tenantId): bool {
        $duration = $data['duration_minutes'] ?? 30;

        if ($this->hasSchedulingConflict($tenantId, $data['provider_id'], $data['scheduled_at'], $duration, $id)) {
            throw new Exception("Scheduling conflict detected! The requested doctor slot is already filled.");
        }

        $stmt = $this->db->prepare("
            UPDATE appointments SET 
                patient_id = :patient_id, provider_id = :provider_id, scheduled_at = :scheduled_at, 
                duration_minutes = :duration_minutes, status = :status, reason = :reason, 
                notes = :notes, updated_at = NOW()
            WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL
        ");

        return $stmt->execute([
            ':id'               => $id,
            ':tenant_id'        => $tenantId,
            ':patient_id'       => $data['patient_id'],
            ':provider_id'      => $data['provider_id'],
            ':scheduled_at'     => $data['scheduled_at'],
            ':duration_minutes' => $duration,
            ':status'           => $data['status'] ?? 'Scheduled',
            ':reason'           => $this->encryptField($data['reason'] ?? ''), 
            ':notes'            => $this->encryptField($data['notes'] ?? '')
          
        ]);
    }

    public function deleteAppointment(int $id, int $userId, int $tenantId): bool {
        $stmt = $this->db->prepare("
            UPDATE appointments SET deleted_at = NOW() WHERE id = :id AND tenant_id = :tenant_id
        ");
        return $stmt->execute([
            ':id'         => $id,
            ':tenant_id'  => $tenantId
        
        ]);
    }

    private function decryptAppointmentFields(array $row): array {
        $fieldsToDecrypt = ['reason', 'notes'];

        foreach ($fieldsToDecrypt as $field) {
            if (!empty($row[$field])) {
                try {
                    $row[$field] = $this->aes->decrypt($row[$field]);
                } catch (Throwable $e) {
                    error_log("[AppointmentService] Decryption failed for {$field}: " . $e->getMessage());
                }
            }
        }
        return $row;
    }
}