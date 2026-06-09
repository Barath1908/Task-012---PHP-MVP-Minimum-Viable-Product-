<?php

// ============================================================
//  api.php — Route Definitions
//  Parses URI + HTTP method and dispatches to controller.
//  $body is available from index.php (decrypted payload).
//  Pattern: METHOD /base/path → Controller@method
// ============================================================

require_once __DIR__ . '/../Controllers/AuthController.php';
require_once __DIR__ . '/../Controllers/DashboardController.php';
require_once __DIR__ . '/../Controllers/BillingController.php';
require_once __DIR__ . '/../Controllers/PrescriptionController.php';
require_once __DIR__ . '/../Controllers/MessageController.php';
require_once __DIR__ . '/../Controllers/StaffController.php';
require_once __DIR__ . '/../Controllers/PatientController.php';
require_once __DIR__ . '/../Controllers/AppointmentController.php';

// -- Parse URI -----------------------------------------------
$requestUri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = strtoupper($_SERVER['REQUEST_METHOD']);
$scriptDir     = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

// Strip base path prefix
$uri  = '/' . trim(substr($requestUri, strlen($scriptDir)), '/');

// ============================================================
//  AUTH ROUTES (public — no AuthMiddleware)
// ============================================================
$auth = new AuthController();
$dashboard = new DashboardController();
$billing = new BillingController();
$prescription = new PrescriptionController();
$message = new MessageController();
$staff = new StaffController();
$patientCtrl = new PatientController();
$appointCtrl = new AppointmentController();


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


// ============================================================
//  PATIENT ROUTES
// ============================================================
// POST /patients (Create)
if ($uri === '/patients' && $requestMethod === 'POST') {
    AuthMiddleware::handle();
    AuthMiddleware::allowRoles([ROLE_PROVIDER, ROLE_NURSE]);
    $patientCtrl->create($body);
}
// GET /patients (Read All)
if ($uri === '/patients' && $requestMethod === 'GET') {
    AuthMiddleware::handle();
    AuthMiddleware::allowRoles([ROLE_PROVIDER, ROLE_NURSE]);
    $patientCtrl->getAll();
}
// PUT /patients/{id} (Update)
if (str_starts_with($uri, '/patients/') && $requestMethod === 'PUT') {
    AuthMiddleware::handle();
    AuthMiddleware::allowRoles([ROLE_PROVIDER, ROLE_NURSE]); 
    $id = (int)substr($uri, strlen('/patients/'));
    $patientCtrl->update($id, $body);
}
// DELETE /patients/{id} (Delete)
if (str_starts_with($uri, '/patients/') && $requestMethod === 'DELETE') {
    AuthMiddleware::handle();
    AuthMiddleware::allowRoles([ROLE_PROVIDER, ROLE_NURSE]); 
    $id = (int)substr($uri, strlen('/patients/'));
    $patientCtrl->delete($id);
}
// GET /patients/{id} (Read Single Record Da)
if (str_starts_with($uri, '/patients/') && $requestMethod === 'GET') {
    AuthMiddleware::handle();
    AuthMiddleware::allowRoles([ROLE_PROVIDER, ROLE_NURSE]);
    $id = (int)substr($uri, strlen('/patients/'));
    $patientCtrl->getById($id);
}

// ============================================================
//  APPOINTMENT & CALENDAR ROUTES
// ============================================================
// POST /appointments (Create with Conflict Validation Check)
if ($uri === '/appointments' && $requestMethod === 'POST') {
    AuthMiddleware::handle();
    AuthMiddleware::allowRoles([ROLE_PROVIDER, ROLE_NURSE, ROLE_PATIENT]);
    $appointCtrl->create($body);
}
// GET /appointments (Read All OR Range Query Filter for Calendar API da!)
if ($uri === '/appointments' && $requestMethod === 'GET') {
    AuthMiddleware::handle();
    // Added open calendar dashboard access roles support context da macha
    AuthMiddleware::allowRoles([ROLE_ADMIN, ROLE_RECEPTIONIST, ROLE_PROVIDER, ROLE_NURSE]);
    
    // Capture optional query-string filters for calendar views (?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD)
    $startDate = $_GET['start_date'] ?? null;
    $endDate   = $_GET['end_date'] ?? null;
    
    $appointCtrl->getAll($startDate, $endDate);
}
// PUT /appointments/{id} (Update with Collision Validator Checks)
if (str_starts_with($uri, '/appointments/') && $requestMethod === 'PUT') {
    AuthMiddleware::handle();
    AuthMiddleware::allowRoles([ROLE_PROVIDER, ROLE_NURSE, ROLE_PATIENT]); 
    $id = (int)substr($uri, strlen('/appointments/'));
    $appointCtrl->update($id, $body);
}
// DELETE /appointments/{id} (Delete / Cancel lifecycle logic)
if (str_starts_with($uri, '/appointments/') && $requestMethod === 'DELETE') {
    AuthMiddleware::handle();
    AuthMiddleware::allowRoles([ROLE_PROVIDER, ROLE_NURSE, ROLE_PATIENT]); 
    $id = (int)substr($uri, strlen('/appointments/'));
    $appointCtrl->delete($id);
}
// GET /appointments/{id} (Read Single Appointment / Tooltip Details API da)
if (str_starts_with($uri, '/appointments/') && $requestMethod === 'GET') {
    AuthMiddleware::handle();
    AuthMiddleware::allowRoles([ROLE_ADMIN, ROLE_RECEPTIONIST, ROLE_PROVIDER, ROLE_NURSE]);
    $id = (int)substr($uri, strlen('/appointments/'));
    $appointCtrl->getById($id);
}


//  PRESCRIPTION ROUTES 

// POST /auth/prescription

if ($uri === '/auth/prescription' && $requestMethod === 'POST') 
{
    AuthMiddleware::handle();

    AuthMiddleware::allowRoles( [ROLE_PROVIDER] );

    $prescription->createPrescription($body);
}

// GET /auth/prescription/{id}  -- prescription_id

if (preg_match('#^/auth/prescription/(\d+)$#', $uri, $matches)
    && $requestMethod === 'GET'
) {
    AuthMiddleware::handle();

    $prescription->viewPrescription( (int)$matches[1] );
}

// PUT /auth/prescription/{id}/verify  -- prescription_id

if ( preg_match('#^/auth/prescription/(\d+)/verify$#', $uri, $matches)
    && $requestMethod === 'PUT'
) {

    AuthMiddleware::handle();

    AuthMiddleware::allowRoles( [ ROLE_PHARMACIST ] );

    $prescription->verifyPrescription( (int)$matches[1] );
}

// PUT /auth/prescription/{id}/dispense  -- prescription_id

if (preg_match('#^/auth/prescription/(\d+)/dispense$#', $uri, $matches)
    && $requestMethod === 'PUT'
) {
    AuthMiddleware::handle();

    AuthMiddleware::allowRoles([ ROLE_PHARMACIST ]);

    $prescription->dispensePrescription(
        (int)$matches[1]
    );
}

//  BILLING ROUTES 

// POST /auth/billing/invoice
if (
    $uri === '/auth/billing/invoice'  && $requestMethod === 'POST')
{

    AuthMiddleware::handle();

    AuthMiddleware::allowRoles([
        ROLE_ADMIN,
        ROLE_PROVIDER
    ]);

    $billing->createInvoice($body);
}

// GET /auth/billing/invoice
if (
    $uri === '/auth/billing/invoice'
    && $requestMethod === 'GET'
) {

    AuthMiddleware::handle();

     AuthMiddleware::allowRoles([
        ROLE_ADMIN,
        ROLE_PROVIDER,
        ROLE_PATIENT
    ]);

    $billing->listInvoices();
}

// GET /auth/billing/invoice/{id}  --- invoice_id
if (
    preg_match('#^/auth/billing/invoice/(\d+)$#', $uri, $matches)
    && $requestMethod === 'GET'
) {

    AuthMiddleware::handle();

     AuthMiddleware::allowRoles([
        ROLE_ADMIN,
        ROLE_PROVIDER,
        ROLE_PATIENT
    ]);

    $billing->viewInvoice((int)$matches[1] );
}

// PUT /auth/billing/invoice/{id}  --- invoice_id

if (preg_match('#^/auth/billing/invoice/(\d+)$#', $uri, $matches)
    && $requestMethod === 'PUT')
 {
    AuthMiddleware::handle();

    AuthMiddleware::allowRoles([
        ROLE_ADMIN,
        ROLE_PROVIDER
    ]);

    $billing->updateInvoice( (int)$matches[1], $body );
}

// DELETE /auth/billing/invoice/{id} --- invoice_id

 if ( preg_match('#^/auth/billing/invoice/(\d+)$#', $uri, $matches)
    && $requestMethod === 'DELETE')
  {
    AuthMiddleware::handle();

    AuthMiddleware::allowRoles([
        ROLE_ADMIN,
        ROLE_PROVIDER
    ]);

    $billing->deleteInvoice((int)$matches[1]);
}

// POST /auth/billing/payment
if (
    $uri === '/auth/billing/payment'
    && $requestMethod === 'POST'
) {

    AuthMiddleware::handle();

    AuthMiddleware::allowRoles([
        ROLE_PATIENT
    ]);

    $billing->recordPayment($body);
}

// PUT /auth/billing/payment/{id} -- payment_id

if ( preg_match('#^/auth/billing/payment/(\d+)$#', $uri, $matches)
    && $requestMethod === 'PUT') 
{
    AuthMiddleware::handle();

    AuthMiddleware::allowRoles([
        ROLE_ADMIN,
        ROLE_PROVIDER
    ]);

    $billing->updatePayment((int)$matches[1], $body);
}

// GET /auth/billing/summary
if ( $uri === '/auth/billing/summary' && $requestMethod === 'GET') {

    AuthMiddleware::handle();

    AuthMiddleware::allowRoles([
        ROLE_ADMIN,
        ROLE_PROVIDER
    ]);

    $billing->summary();
}

// STAFF ROUTES

// GET /auth/staff

if ($uri === '/auth/staff'
    && $requestMethod === 'GET'
) {
    AuthMiddleware::handle();

    $staff->list();
}

// GET /auth/staff/{id}  -- user_id

if (
    preg_match('#^/auth/staff/(\d+)$#', $uri, $matches)
    && $requestMethod === 'GET'
) {
    AuthMiddleware::handle();

    $staff->view( (int)$matches[1] );
}

// POST /auth/staff

if ($uri === '/auth/staff' && $requestMethod === 'POST')
{
    AuthMiddleware::handle();

    AuthMiddleware::allowRoles([ ROLE_ADMIN ]);

    $staff->create($body);
}

// PUT /auth/staff/{id}  -- user_id

if ( preg_match('#^/auth/staff/(\d+)$#', $uri, $matches)
    && $requestMethod === 'PUT')
{
    AuthMiddleware::handle();

    AuthMiddleware::allowRoles([ ROLE_ADMIN ]);

    $staff->update(
        (int)$matches[1],
        $body
    );
}

// DELETE /auth/staff/{id} -- user_id

if (
    preg_match('#^/auth/staff/(\d+)$#', $uri, $matches)
    && $requestMethod === 'DELETE'
) {
    AuthMiddleware::handle();

    AuthMiddleware::allowRoles([
        ROLE_ADMIN
    ]);

    $staff->delete( (int)$matches[1] );
}

// COMMUNICATION ROUTES

// POST /auth/message

if ($uri === '/auth/message' && $requestMethod === 'POST')
{
    AuthMiddleware::handle();

    AuthMiddleware::allowRoles([
        ROLE_ADMIN,
        ROLE_PROVIDER,
        ROLE_NURSE
    ]);

    $message->createMessage($body);
}

// GET /auth/message/{id}   --- msg_id

if (
    preg_match('#^/auth/message/(\d+)$#', $uri, $matches)
    && $requestMethod === 'GET'
) {
    AuthMiddleware::handle();

    AuthMiddleware::allowRoles([
        ROLE_ADMIN,
        ROLE_PROVIDER,
        ROLE_NURSE
    ]);

    $message->getMessage(
        (int)$matches[1]
    );
}

// GET /auth/message/appointment/{id}   -- appointment_id

if (
    preg_match(
        '#^/auth/message/appointment/(\d+)$#',
        $uri,
        $matches
    )
    && $requestMethod === 'GET'
) {
    AuthMiddleware::handle();

    AuthMiddleware::allowRoles([
        ROLE_ADMIN,
        ROLE_PROVIDER,
        ROLE_NURSE
    ]);

    $message->getAppointmentMessages( (int)$matches[1] );
}

// PUT /auth/message/{id}/read  -- msg_id

if (
    preg_match(
        '#^/auth/message/(\d+)/read$#',
        $uri,
        $matches
    )
    && $requestMethod === 'PUT'
) {
    AuthMiddleware::handle();

    AuthMiddleware::allowRoles([
        ROLE_ADMIN,
        ROLE_PROVIDER,
        ROLE_NURSE
    ]);

    $message->markAsRead( (int)$matches[1] );
}

// DELETE /auth/message/{id}   -- msg_id

if (
    preg_match('#^/auth/message/(\d+)$#', $uri, $matches)
    && $requestMethod === 'DELETE'
) {
    AuthMiddleware::handle();

    AuthMiddleware::allowRoles([
        ROLE_ADMIN,
        ROLE_PROVIDER,
        ROLE_NURSE
    ]);

    $message->deleteMessage( (int)$matches[1] );
}



// -- 404 fallback --------------------------------------------
Response::notFound('Route not found.');
