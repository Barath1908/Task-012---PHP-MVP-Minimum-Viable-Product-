<?php

require_once __DIR__ . '/../Services/AppointmentService.php';
require_once __DIR__ . '/../Helpers/Response.php';
require_once __DIR__ . '/../Helpers/Validator.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';

class AppointmentController {
    private AppointmentService $service;

    public function __construct() {
        $this->service = new AppointmentService();
    }

    public function create(array $body): void {
        $userId   = AuthMiddleware::userId();
        $data = $body['payload'] ?? $body;

        $validator = new Validator($data);
        $validator->required(['patient_id', 'provider_id', 'scheduled_at'])
                  ->numeric('patient_id')
                  ->numeric('provider_id');

        if ($validator->fails()) { 
            Response::validationError($validator->errors()); 
        }

        try {
            $appointmentId = $this->service->createAppointment($data, $userId);
            Response::created(['appointment_id' => $appointmentId], 'Appointment booked successfully.');
        } catch (Throwable $e) { 
            Response::error($e->getMessage()); 
        }
    }

    /**
     * Handles standard queries and multi-tenant Range Data Filters for Calendar tracking 
     */
    public function getAll(?string $startDate = null, ?string $endDate = null): void {
        $userId   = AuthMiddleware::userId();
        $user     = AuthMiddleware::user();
        $userRole = $user['role'] ?? '';

        try {
            $appointments = $this->service->getAllAppointments($userId, $userRole, $startDate, $endDate);
            Response::success($appointments, 'Calendar appointments data fetched successfully.');
        } catch (Throwable $e) { 
            Response::error($e->getMessage()); 
        }
    }

    /**
     * GET /appointments/{id}
     * Fetches details mapping directly to UI Tooltip hover structures securely
     */
    public function getById(int $id): void {
        $userId   = AuthMiddleware::userId();   
        $user     = AuthMiddleware::user();
        $userRole = $user['role'] ?? '';

        try {
            $appointment = $this->service->getAppointmentById($id, $userId, $userRole);
            
            if (!$appointment) {
                Response::error('Appointment record not found or access denied.', 404);
                return;
            }
            
            Response::success($appointment, 'Appointment details record retrieved successfully.');
        } catch (Throwable $e) { 
            Response::error($e->getMessage()); 
        }
    }

    public function update(int $id, array $body): void {
        $userId   = AuthMiddleware::userId();
        $data = $body['payload'] ?? $body;

        $validator = new Validator($data);
        $validator->required(['patient_id', 'provider_id', 'scheduled_at'])
                  ->numeric('patient_id')
                  ->numeric('provider_id');

        if ($validator->fails()) { 
            Response::validationError($validator->errors()); 
        }

        try {
            $this->service->updateAppointment($id, $data, $userId);
            Response::success([], 'Appointment details modified successfully.');
        } catch (Throwable $e) { 
            Response::error($e->getMessage()); 
        }
    }

    public function delete(int $id): void {
        $userId   = AuthMiddleware::userId();
        try {
            $this->service->deleteAppointment($id, $userId);
            Response::success([], 'Appointment canceled and dropped successfully.');
        } catch (Throwable $e) { 
            Response::error($e->getMessage()); 
        }
    }
}