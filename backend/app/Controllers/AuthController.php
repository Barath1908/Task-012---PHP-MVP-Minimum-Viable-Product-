<?php

// ============================================================
//  AuthController.php — Authentication HTTP Layer
//  Receives decrypted $body from index.php.
//  Validates input → calls AuthService → sends Response.
//  Never contains business logic or DB queries.
// ============================================================

require_once __DIR__ . '/../Services/AuthService.php';
require_once __DIR__ . '/../Helpers/Response.php';
require_once __DIR__ . '/../Helpers/Validator.php';
require_once __DIR__ . '/../Security/CSRF.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';

class AuthController
{
    private AuthService $service;

    // --------------------------------------------------------
    public function __construct()
    {
        $this->service = new AuthService();
    }

    // ========================================================
    //  POST /auth/register
    //  Public. Validates input → registers user → returns tokens.
    // ========================================================
    public function register(array $body): void
    {
        $validator = new Validator($body);
        $validator->required(['tenant_id', 'role_id', 'first_name', 'last_name', 'email', 'password'])
          ->email('email')
          ->min('password', 8)
          ->max('first_name', 80)
          ->max('last_name', 80)
          ->numeric('tenant_id')
          ->numeric('role_id');

        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }

        try {
            $result = $this->service->register($body);
            Response::created($result, 'Registration successful.');
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: HTTP_BAD_REQUEST);
        }
    }

    // ========================================================
    //  POST /auth/login
    //  Public. Validates input → authenticates → returns tokens.
    // ========================================================
    public function login(array $body): void
    {
        $validator = new Validator($body);
        $validator->required(['tenant_id', 'email', 'password'])
          ->email('email')
          ->numeric('tenant_id');

        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }

        try {
            $result = $this->service->login($body);
            Response::success($result, 'Login successful.');
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: HTTP_UNAUTHORIZED);
        }
    }

    // ========================================================
    //  POST /auth/refresh
    //  Public. Exchanges refresh token for new access token.
    //  Expects: { "refresh_token": "..." } in decrypted body.
    // ========================================================
    public function refresh(): void
    {
        // No body needed — refresh token comes from HttpOnly cookie
        try {
            $result = $this->service->refresh();
            Response::success($result, 'Token refreshed successfully.');
        } catch (RuntimeException $e) {
            Response::unauthorized($e->getMessage());
        }
    }

    // ========================================================
    //  POST /auth/logout
    //  Protected (AuthMiddleware runs before this in api.php).
    //  Revokes refresh tokens, clears session, clears CSRF.
    // ========================================================
    public function logout(): void
    {
        $userId = AuthMiddleware::userId();

        try {
            $this->service->logout($userId);

            // Fix 6: Clear session cookie from browser before destroying
            // Prevents stale cookie lingering on client side
            if (isset($_COOKIE[session_name()])) {
                setcookie(
                    session_name(),
                    '',
                    time() - 3600,
                    '/'
                );
            }

            // Destroy session entirely
            session_unset();
            session_destroy();

            Response::success([], 'Logged out successfully.');
        } catch (Throwable $e) {
            error_log('[Logout] Error: ' . $e->getMessage());
            Response::error('Logout failed. Please try again.');
        }
    }

    /*/ ========================================================
    //  GET /auth/csrf-token
    //  Public. Called on app load to get initial CSRF token.
    //  Returns a fresh CSRF token (unencrypted — it's public).
    // ========================================================
    public function csrfToken(): void
    {
        $token = CSRF::generate();

        // CSRF token response is NOT encrypted (by design —
        // it's the bootstrap token needed to send the first request)
        http_response_code(HTTP_OK);
        header('Content-Type: application/json');
        echo json_encode([
            'success'    => STATUS_SUCCESS,
            'csrf_token' => $token,
        ]);
        exit;
    }*/

    // ========================================================
    //  POST /auth/change-password
    //  Protected. Validates current password → updates to new.
    // ========================================================
    public function changePassword(array $body): void
    {
        $validator = new Validator($body);
        $validator->required(['current_password', 'new_password'])
                ->min('new_password', 8);

        if ($validator->fails()) {
            Response::validationError($validator->errors());
        }

        $userId = AuthMiddleware::userId();

        try {
            $this->service->changePassword(
                $userId,
                $body['current_password'],
                $body['new_password']
            );
            Response::success([], 'Password changed successfully.');
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: HTTP_BAD_REQUEST);
        }
    }
}
