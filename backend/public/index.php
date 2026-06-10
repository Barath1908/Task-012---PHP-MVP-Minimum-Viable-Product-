<?php

declare(strict_types=1);


// -- Bootstrap -----------------------------------------------
require_once __DIR__ . '/../app/Config/config.php';
require_once __DIR__ . '/../app/Config/constants.php';
require_once __DIR__ . '/../app/Config/database.php';

require_once __DIR__ . '/../app/Security/AES.php';
require_once __DIR__ . '/../app/Security/JWT.php';
require_once __DIR__ . '/../app/Security/CSRF.php';
require_once __DIR__ . '/../app/Security/Hash.php';

require_once __DIR__ . '/../app/Helpers/Response.php';
require_once __DIR__ . '/../app/Helpers/Validator.php';

require_once __DIR__ . '/../app/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/Middleware/CsrfMiddleware.php';

// -- Session -------------------------------------------------

session_name(SESSION_NAME);

session_start();

// Fix for WAMP — ensure Authorization header is available


// -- CORS Headers --------------------------------------------
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// -- Read Request Body ---------------------------------------
$rawBody = file_get_contents('php://input');
$input   = [];
$body    = [];

if (!empty($rawBody)) {
    $input = json_decode($rawBody, associative: true) ?? [];
}

// Frontend sends plain JSON — no AES decryption needed
if (!empty($input['payload'])) {
    if (is_array($input['payload'])) {
        $body = $input['payload'];
    } 
}

// Extract CSRF token from request header
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

// -- CSRF Validation -----------------------------------------
// Skip CSRF for register and login — token not yet available
$requestUri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$scriptDir     = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$currentUri    = '/' . trim(substr($requestUri, strlen($scriptDir)), '/');

$csrfExcluded = ['/auth/register', '/auth/login'];

if (!in_array($currentUri, $csrfExcluded, true)) {
    CsrfMiddleware::handle($csrfToken);
}


require_once __DIR__ . '/../app/Routes/api.php';