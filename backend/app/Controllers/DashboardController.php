<?php

// ============================================================
//  DashboardController.php — Dashboard HTTP Layer
//  Receives authenticated request from api.php.
//  Calls DashboardService → sends Response.
//  Roles allowed: Admin, Provider
// ============================================================

require_once __DIR__ . '/../Services/DashboardService.php';
require_once __DIR__ . '/../Helpers/Response.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';

class DashboardController
{
    private DashboardService $service;

    // --------------------------------------------------------
    public function __construct()
    {
        $this->service = new DashboardService();
    }

    // ========================================================
    //  GET /dashboard/summary
    //  Protected — Admin and Provider only.
    //  Returns full dashboard statistics for authenticated tenant.
    // ========================================================
    public function getSummary(): void
    {
        // Get tenant_id from authenticated user token
        $tenantId = AuthMiddleware::tenantId();

        try {
            $summary = $this->service->getSummary($tenantId);
            Response::success($summary, 'Dashboard summary retrieved successfully.');
        } catch (Throwable $e) {
            error_log('[Dashboard] Error: ' . $e->getMessage());
            Response::error('Failed to retrieve dashboard summary.');
        }
    }
}