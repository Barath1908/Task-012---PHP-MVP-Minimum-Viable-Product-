<?php

require_once __DIR__ . '/../Services/PrescriptionService.php';
require_once __DIR__ . '/../Helpers/Response.php';
require_once __DIR__ . '/../Helpers/Validator.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';

class PrescriptionController
{
    private PrescriptionService $service;

    public function __construct()
    {
        $this->service = new PrescriptionService();
    }

    // CREATE PRESCRIPTION

    public function createPrescription(array $body): void
    {
        $validator = new Validator($body);

        $validator->required([
            'patient_id',
            'provider_id',
            'medications'
        ]);

        if ($validator->fails())
        {
            Response::validationError(
                $validator->errors()
            );
        }

        try {

            $result = $this->service->createPrescription(
                $body,
                AuthMiddleware::user()
            );

            Response::created(
                $result,
                'Prescription created successfully.'
            );

        } catch (RuntimeException $e) {

            Response::error(
                $e->getMessage(),
                $e->getCode() ?: HTTP_BAD_REQUEST
            );
        }
    }

    // VIEW PRESCRIPTION

    public function viewPrescription(int $id): void
    {
        try {

            $result = $this->service->getPrescription(
                $id,
                AuthMiddleware::tenantId()
            );

            Response::success(
                $result,
                'Prescription fetched successfully.'
            );

        } catch (RuntimeException $e) {

            Response::error(
                $e->getMessage(),
                $e->getCode() ?: HTTP_NOT_FOUND
            );
        }
    }

    // VERIFY PRESCRIPTION

    public function verifyPrescription(int $id): void
    {
        try {

            $result = $this->service->verifyPrescription(
                $id,
                AuthMiddleware::user()
            );

            Response::success(
                $result,
                'Prescription verified successfully.'
            );

        } catch (RuntimeException $e) {

            Response::error(
                $e->getMessage(),
                $e->getCode() ?: HTTP_BAD_REQUEST
            );
        }
    }

    // DISPENSE PRESCRIPTION

    public function dispensePrescription(int $id): void
    {
        try {

            $result = $this->service->dispensePrescription(
                $id,
                AuthMiddleware::user()
            );

            Response::success(
                $result,
                'Medicine dispensed successfully.'
            );

        } catch (RuntimeException $e) {

            Response::error(
                $e->getMessage(),
                $e->getCode() ?: HTTP_BAD_REQUEST
            );
        }
    }
}

