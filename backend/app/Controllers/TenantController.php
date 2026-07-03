<?php

require_once __DIR__ . '/../Services/TenantProvisioningService.php';
require_once __DIR__ . '/../Helpers/Response.php';
require_once __DIR__ . '/../Helpers/Validator.php';
require_once __DIR__ . '/../Config/subdomainResolver.php';

class TenantController
{
    private TenantProvisioningService $service;

    public function __construct()
    {
        $this->service = new TenantProvisioningService();
    }

    // POST /tenant/register
    public function register(array $body): void
    {
        $validator = new Validator($body);

        $validator
            ->required([
                'company_name',
                'admin_name',
                'admin_email',
                'password',
                'confirm_password'
            ])
            ->email('admin_email')
            ->min('password', 8);

        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }

        if ($body['password'] !== $body['confirm_password']) {
            Response::error('Passwords do not match.', 422);
        }

        try {
            $result = $this->service->register($body);

            Response::created(
                $result,
                'Tenant registered successfully. Your workspace is ready.'
            );
        } catch (Throwable $e) {

            error_log("[TenantController] " . $e->getMessage());

            Response::error($e->getMessage());
        }
    }

    // GET /tenant/check?subdomain=apollo
    public function checkSubdomain(): void
    {
        $subdomain = $_GET['subdomain'] ?? '';

        if (empty($subdomain)) {
            Response::error('Subdomain is required.', 400);
        }

        $master = MasterDatabase::getConnection();

        $stmt = $master->prepare(
            "SELECT COUNT(id) FROM tenants WHERE subdomain = ?"
        );

        $stmt->execute([$subdomain]);

        $exists = (int)$stmt->fetchColumn() > 0;

        Response::success(['available' => !$exists], 'Subdomain check complete.');
    }



    public function getConfig(): void
    {
        $tenant = SubdomainResolver::resolve();

        if (!$tenant) {
            Response::error('Tenant not found.', 404);
        }

        Response::success([
            'company_name' => $tenant['company_name'],
            'subdomain'    => $tenant['subdomain'],
            'theme'        => $tenant['theme'],
            'plan_type'    => $tenant['plan_type'],
        ], 'Tenant config loaded.');
    }

    // PUT /tenant/theme
    public function updateTheme(array $body): void
    {
        AuthMiddleware::handle();
        AuthMiddleware::allowRoles([ROLE_ADMIN]);

        $tenant = SubdomainResolver::resolve();
        if (!$tenant) {
            Response::error('Tenant not found.', 404);
        }

        $theme = $body['theme'] ?? '';
        if (!in_array($theme, ['dark', 'light', 'warm'], true)) {
            Response::error('Invalid theme. Allowed values: dark, light, warm.', 400);
        }

        try {
            $master = MasterDatabase::getConnection();
            $stmt   = $master->prepare("UPDATE tenants SET theme = ? WHERE id = ?");
            $stmt->execute([$theme, $tenant['id']]);

            Response::success(['theme' => $theme], 'Theme updated successfully.');
        } catch (Throwable $e) {
            error_log('[TenantController] Update theme error: ' . $e->getMessage());
            Response::error('Failed to update theme.');
        }
    }
}