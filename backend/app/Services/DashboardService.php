<?php

// ============================================================
//  DashboardService.php — Dashboard Business Logic
//  Returns aggregated statistics for dashboard.
//  All queries are tenant-scoped.
//  No decryption needed — only counts returned.
// ============================================================

require_once __DIR__ . '/../Config/database.php';

class DashboardService
{
    private PDO $db;

    // --------------------------------------------------------
    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    // ========================================================
    //  getSummary()
    //  Returns full dashboard statistics for a tenant.
    //  Includes: patients, appointments, prescriptions summary
    // ========================================================
    public function getSummary(int $tenantId): array
    {
        return [
            'patients'      => $this->getPatientStats($tenantId),
            'appointments'  => $this->getAppointmentStats($tenantId),
            'prescriptions' => $this->getPrescriptionStats($tenantId),
        ];
    }

    // ========================================================
    //  PRIVATE HELPERS
    // ========================================================

    // --------------------------------------------------------
    //  getPatientStats()
    //  Returns total active patients for tenant
    // --------------------------------------------------------
    private function getPatientStats(int $tenantId): array
    {
        $stmt = $this->db->prepare("SELECT COUNT(id) AS total FROM patients WHERE tenant_id = ? AND deleted_at IS NULL AND is_active = 1");
        $stmt->execute([$tenantId]);
        $result = $stmt->fetch();

        return [
            'total' => (int)$result['total'],
        ];
    }

    // --------------------------------------------------------
    //  getAppointmentStats()
    //  Returns total and per-status appointment counts
    // --------------------------------------------------------
    private function getAppointmentStats(int $tenantId): array
    {
        // Total count
        $stmt = $this->db->prepare("SELECT COUNT(id) AS total FROM appointments WHERE tenant_id = ? AND deleted_at IS NULL");
        $stmt->execute([$tenantId]);
        $total = (int)$stmt->fetch()['total'];

        // Per status counts
        $stmt = $this->db->prepare("SELECT status, COUNT(id) AS count FROM appointments WHERE tenant_id = ? AND deleted_at IS NULL GROUP BY status");
        $stmt->execute([$tenantId]);
        $rows = $stmt->fetchAll();

        // Build status counts with default 0
        $statusCounts = [
            APPT_PENDING   => 0,
            APPT_CONFIRMED => 0,
            APPT_COMPLETED => 0,
            APPT_CANCELLED => 0,
        ];

        foreach ($rows as $row) {
            if (array_key_exists($row['status'], $statusCounts)) {
                $statusCounts[$row['status']] = (int)$row['count'];
            }
        }

        return [
            'total'     => $total,
            'pending'   => $statusCounts[APPT_PENDING],
            'confirmed' => $statusCounts[APPT_CONFIRMED],
            'completed' => $statusCounts[APPT_COMPLETED],
            'cancelled' => $statusCounts[APPT_CANCELLED],
        ];
    }

    // --------------------------------------------------------
    //  getPrescriptionStats()
    //  Returns total and per-status prescription counts
    // --------------------------------------------------------
    private function getPrescriptionStats(int $tenantId): array
    {
        // Total count
        $stmt = $this->db->prepare("SELECT COUNT(id) AS total FROM prescriptions WHERE tenant_id = ? AND deleted_at IS NULL");
        $stmt->execute([$tenantId]);
        $total = (int)$stmt->fetch()['total'];

        // Per status counts
        $stmt = $this->db->prepare("SELECT status, COUNT(id) AS count FROM prescriptions WHERE tenant_id = ? AND deleted_at IS NULL GROUP BY status");
        $stmt->execute([$tenantId]);
        $rows = $stmt->fetchAll();

        // Build status counts with default 0
        $statusCounts = [
            RX_ISSUED    => 0,
            RX_VERIFIED  => 0,
            RX_DISPENSED => 0,
            RX_CANCELLED => 0,
        ];

        foreach ($rows as $row) {
            if (array_key_exists($row['status'], $statusCounts)) {
                $statusCounts[$row['status']] = (int)$row['count'];
            }
        }

        return [
            'total'     => $total,
            'issued'    => $statusCounts[RX_ISSUED],
            'verified'  => $statusCounts[RX_VERIFIED],
            'dispensed' => $statusCounts[RX_DISPENSED],
            'cancelled' => $statusCounts[RX_CANCELLED],
        ];
    }
}