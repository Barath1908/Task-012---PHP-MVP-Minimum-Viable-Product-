<?php

require_once __DIR__ . '/../Services/StaffService.php';
require_once __DIR__ . '/../Helpers/Response.php';
require_once __DIR__ . '/../Helpers/Validator.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';

class StaffController
{
    private StaffService $service;

    public function __construct()
    {
        $this->service = new StaffService();
    }

    // CREATE STAFF
    public function create(array $body): void
    {
        $validator = new Validator($body);

        $validator->required([
            'first_name',
            'last_name',
            'email',
            'phone',
            'password',
            'role'
        ])
        ->email('email')
        ->min('password', 8);

        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }

        try {
            $result = $this->service->createStaff(
                $body,
                AuthMiddleware::user()
            );

            Response::created($result, 'Staff created successfully.');

        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: HTTP_BAD_REQUEST);
        }
    }

    // LIST STAFF
    public function list(): void
    {
        try {
            $result = $this->service->getStaff(
                AuthMiddleware::tenantId()
            );

            Response::success($result, 'Staff fetched successfully.');

        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: HTTP_BAD_REQUEST);
        }
    }

    // VIEW STAFF
    public function view(int $id): void
    {
        try {
            $result = $this->service->getStaffById(
                $id,
                AuthMiddleware::tenantId()
            );

            Response::success($result, 'Staff fetched successfully.');

        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: HTTP_NOT_FOUND);
        }
    }

    // UPDATE STAFF
    public function update(int $id, array $body): void
    {
        try {
            $result = $this->service->updateStaff(
                $id,
                $body,
                AuthMiddleware::user()
            );

            Response::success($result, 'Staff updated successfully.');

        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: HTTP_BAD_REQUEST);
        }
    }

    // DELETE STAFF (SOFT DELETE)
    public function delete(int $id): void
    {
        try {
            $this->service->deleteStaff(
                $id,
                AuthMiddleware::user()
            );

            Response::success([], 'Staff deleted successfully.');

        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: HTTP_BAD_REQUEST);
        }
    }
}
