<?php

declare(strict_types=1);

/*file_put_contents('C:/wamp64/www/task12-branch3/debug.txt',
    'REQUEST_URI=' . $_SERVER['REQUEST_URI'] . "\n" .
    'SCRIPT_NAME=' . $_SERVER['SCRIPT_NAME'] . "\n"
);*/

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
ini_set('session.gc_maxlifetime', (string)SESSION_LIFETIME);
session_name(SESSION_NAME);

if (APP_ENV === 'production') {
    ini_set('session.cookie_secure',   '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    session_save_path(SESSION_PATH);
}

session_start();

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

// TEMP: Skip AES decrypt for Postman testing
// payload is sent as plain JSON object directly
if (!empty($input['payload'])) {
    if (is_array($input['payload'])) {
        // payload sent as JSON object directly (Postman testing)
        $body = $input['payload'];
    } elseif (is_string($input['payload'])) {
        // try JSON decode first (plain string)
        $decoded = json_decode($input['payload'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $body = $decoded;
        } else {
            // real AES encrypted string — decrypt it
            try {
                $aes  = new AES();
                $body = $aes->decryptToArray($input['payload']);
            } catch (Throwable $e) {
                error_log('[Gateway] AES decrypt failed: ' . $e->getMessage());
                Response::error('Invalid or malformed request payload.', HTTP_BAD_REQUEST);
            }
        }
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

// -- Route ---------------------------------------------------
/*file_put_contents('C:/wamp64/www/task12-branch3/debug.txt',
    'REQUEST_URI=' . $_SERVER['REQUEST_URI'] . "\n" .
    'SCRIPT_NAME=' . $_SERVER['SCRIPT_NAME'] . "\n" .
    'reached_routes=YES' . "\n" .
    'body=' . json_encode($body) . "\n"
);*/

require_once __DIR__ . '/../app/Routes/api.php';