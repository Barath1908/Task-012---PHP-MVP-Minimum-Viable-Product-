<?php

// ============================================================
//  api.php — Route Definitions
//  Parses URI + HTTP method and dispatches to controller.
//  $body is available from index.php (decrypted payload).
//  Pattern: METHOD /base/path → Controller@method
// ============================================================

require_once __DIR__ . '/../Controllers/AuthController.php';
require_once __DIR__ . '/../Controllers/DashboardController.php';

// -- Parse URI -----------------------------------------------
$requestUri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = strtoupper($_SERVER['REQUEST_METHOD']);
$scriptDir     = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

// Strip base path prefix
$uri  = '/' . trim(substr($requestUri, strlen($scriptDir)), '/');

//error_log('[DEBUG URI] requestUri=' . $requestUri . ' | uri=' . $uri);

// ============================================================
//  AUTH ROUTES (public — no AuthMiddleware)
// ============================================================
$auth = new AuthController();
$dashboard = new DashboardController();

/*// TEMP DEBUG
file_put_contents('C:/wamp64/www/task12-branch3/debug.txt',
    'uri=' . $uri . "\n" .
    'method=' . $requestMethod . "\n" .
    'body=' . json_encode($body) . "\n"
);*/

// POST /auth/register
if ($uri === '/auth/register' && $requestMethod === 'POST') {
    $auth->register($body);
}

// POST /auth/login
if ($uri === '/auth/login' && $requestMethod === 'POST') {
    $auth->login($body);
}

// POST /auth/refresh
if ($uri === '/auth/refresh' && $requestMethod === 'POST') {
    $auth->refresh();
}

// POST /auth/logout  (protected)
if ($uri === '/auth/logout' && $requestMethod === 'POST') {
    AuthMiddleware::handle();
    $auth->logout();
}

// POST /auth/change-password (protected)
if ($uri === '/auth/change-password' && $requestMethod === 'POST') {
    AuthMiddleware::handle();
    $auth->changePassword($body);
}

// ============================================================
//  DASHBOARD ROUTES
//  Roles: Admin, Provider
// ============================================================


// GET /dashboard/summary
if ($uri === '/dashboard/summary' && $requestMethod === 'GET') {
    AuthMiddleware::handle();
    AuthMiddleware::allowRoles([ROLE_ADMIN, ROLE_PROVIDER]);
    $dashboard->getSummary();
}

/*// GET /auth/csrf-token  (public — issued on app load)
if ($uri === '/auth/csrf-token' && $requestMethod === 'GET') {
    $auth->csrfToken();
}*/

// ============================================================
//  PATIENT ROUTES — placeholder (Team Member 2)
//  AuthMiddleware::handle() + allowRoles() will go here.
// ============================================================
// POST   /patients
// GET    /patients
// GET    /patients/{id}
// PUT    /patients/{id}
// DELETE /patients/{id}

// ============================================================
//  APPOINTMENT ROUTES — placeholder (Team Member 2)
// ============================================================
// POST   /appointments
// GET    /appointments
// GET    /appointments/{id}
// PUT    /appointments/{id}
// DELETE /appointments/{id}

// ============================================================
//  PRESCRIPTION ROUTES — placeholder (Team Member 3)
// ============================================================

// ============================================================
//  BILLING ROUTES — placeholder (Team Member 3)
// ============================================================

// ============================================================
//  STAFF ROUTES — placeholder
// ============================================================

// ============================================================
//  DASHBOARD ROUTES — placeholder
// ============================================================

// -- 404 fallback --------------------------------------------
Response::notFound('Route not found.');
