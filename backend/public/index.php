<?php

declare(strict_types=1);


// -- CORS Headers (MUST BE FIRST) ----------------------------

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token");

if (!empty($origin)) {
    header("Access-Control-Allow-Origin: " . $origin);
} else {
    header("Access-Control-Allow-Origin: *");
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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

require_once __DIR__ . '/../app/Config/masterDatabase.php';
require_once __DIR__ . '/../app/Config/subdomainResolver.php';

require_once __DIR__ . '/../app/Controllers/TenantController.php';



// -- Session -------------------------------------------------

session_name(SESSION_NAME);

session_start();



// Fix for WAMP — ensure Authorization header is available

if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {

    $headers = getallheaders();

    if (isset($headers['Authorization'])) {

        $_SERVER['HTTP_AUTHORIZATION'] = $headers['Authorization'];

    }

}



// -- Read Request Body ---------------------------------------

$rawBody = file_get_contents('php://input');

$input = [];
$body  = [];


if (!empty($rawBody)) {

    $input = json_decode($rawBody, true) ?? [];

}



// Frontend sends plain JSON

if (!empty($input['payload'])) {

    if (is_array($input['payload'])) {

        $body = $input['payload'];

    }

}



// -- CSRF Token ---------------------------------------------

$headers = getallheaders();

$csrfToken = 
    $_SERVER['HTTP_X_CSRF_TOKEN'] 
    ?? $headers['X-CSRF-Token'] 
    ?? $headers['x-csrf-token'] 
    ?? '';





// -- CSRF Validation -----------------------------------------


// Skip CSRF for these routes

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');


$currentUri = '/' . trim(
    substr($requestUri, strlen($scriptDir)),
    '/'
);



$csrfExcluded = [

    '/auth/register',
    '/auth/login',
    '/auth/logout',
    '/tenant/register'

];



if (!in_array($currentUri, $csrfExcluded, true)) {

    CsrfMiddleware::handle($csrfToken);

}




// -- Parse URI -----------------------------------------------


$rawUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');


$earlyUri = '/' . trim(
    substr($rawUri, strlen($scriptDir)),
    '/'
);


$earlyMethod = strtoupper($_SERVER['REQUEST_METHOD']);




// -- TENANT ROUTES -------------------------------------------


$tenantCtrl = new TenantController();




// POST /tenant/register

if (
    $earlyUri === '/tenant/register' 
    && 
    $earlyMethod === 'POST'
) {

    $tenantCtrl->register($body);

    exit;

}





// GET /tenant/check

if (
    $earlyUri === '/tenant/check'
    &&
    $earlyMethod === 'GET'
) {

    $tenantCtrl->checkSubdomain();

    exit;

}





// GET /tenant/config

if (
    $earlyUri === '/tenant/config'
    &&
    $earlyMethod === 'GET'
) {

    $tenantCtrl->getConfig();

    exit;

}

// PUT /tenant/theme
if ($earlyUri === '/tenant/theme' && $earlyMethod === 'PUT') {
    $tenantCtrl->updateTheme($body);
    exit;
}



// -- Other Routes ---------------------------------------------

require_once __DIR__ . '/../app/Routes/api.php';