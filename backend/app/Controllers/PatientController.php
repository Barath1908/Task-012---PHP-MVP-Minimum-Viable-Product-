<?php

require_once __DIR__ . '/../Services/PatientService.php';
require_once __DIR__ . '/../Helpers/Response.php';
require_once __DIR__ . '/../Helpers/Validator.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';

class PatientController {
    private PatientService $service;

    public function __construct() {
        $this->service = new PatientService();
    }

    public function create(array $body): void {
        $userId   = AuthMiddleware::userId();
        $tenantId = AuthMiddleware::tenantId();

        $validator = new Validator($body);
        $validator->required(['first_name', 'last_name']);

        if ($validator->fails()) { Response::validationError($validator->errors()); }

        try {
            $patientId = $this->service->createPatient($body, $userId, $tenantId);
            Response::created(['patient_id' => $patientId], 'Patient card created successfully.');
        } catch (Throwable $e) { Response::error($e->getMessage()); }
    }


    public function getAll(): void {
        $tenantId = AuthMiddleware::tenantId();
        try {
            $patients = $this->service->getAllPatients($tenantId);
            Response::success($patients, 'Patients retrieved successfully.');
        } catch (Throwable $e) { Response::error($e->getMessage()); }
    }


    public function getById(int $id): void {
    $tenantId = AuthMiddleware::tenantId(); // Enforces isolation
    
    try {
        $patient = $this->service->getPatientById($id, $tenantId);
        
        if (!$patient) {
            Response::error('Patient record not found.', 404);
            return;
        }
        
        Response::success($patient, 'Patient profile record retrieved successfully.');
    } catch (Throwable $e) { 
        Response::error($e->getMessage()); 
    }
}


    public function update(int $id, array $body): void {
        $userId   = AuthMiddleware::userId();
        $tenantId = AuthMiddleware::tenantId();

        $validator = new Validator($body);
        $validator->required(['first_name', 'last_name']);

        if ($validator->fails()) { Response::validationError($validator->errors()); }

        try {
            $this->service->updatePatient($id, $body, $userId, $tenantId);
            Response::success([], 'Patient record updated successfully.');
        } catch (Throwable $e) { Response::error($e->getMessage()); }
    }

    
    public function delete(int $id): void {
        $userId   = AuthMiddleware::userId();
        $tenantId = AuthMiddleware::tenantId();
        try {
            $this->service->deletePatient($id, $userId, $tenantId);
            Response::success([], 'Patient records archived successfully.');
        } catch (Throwable $e) { Response::error($e->getMessage()); }
    }
}