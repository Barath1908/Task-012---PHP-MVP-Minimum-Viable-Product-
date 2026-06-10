<?php

// ============================================================
//  config.php — Application Configuration
//  Loads .env values and defines core app settings.
//  All sensitive values (keys, secrets) come from .env only.
// ============================================================

// -- Load .env -----------------------------------------------
$envPath = dirname(__DIR__, 2) . '/.env';

if (!file_exists($envPath)) {
    http_response_code(500);
    die(json_encode(['status' => 'error', 'message' => '.env file not found']));
}

$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

foreach ($lines as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
    [$key, $value] = explode('=', $line, 2);
    $_ENV[trim($key)] = trim($value);
}

// -- Environment ---------------------------------------------
define('APP_ENV',      $_ENV['APP_ENV']      ?? 'production');
define('APP_NAME',     $_ENV['APP_NAME']     ?? 'HealthcareAPI');
define('APP_URL',      $_ENV['APP_URL']      ?? 'http://localhost');
define('APP_TIMEZONE', $_ENV['APP_TIMEZONE'] ?? 'UTC');

date_default_timezone_set(APP_TIMEZONE);

// -- Security Keys (from .env only) --------------------------
define('AES_KEY',        $_ENV['AES_KEY']        ?? '');
define('AES_IV',         $_ENV['AES_IV']         ?? '');
define('JWT_SECRET',     $_ENV['JWT_SECRET']      ?? '');

// -- Token Expiry --------------------------------------------
define('ACCESS_TOKEN_EXPIRY',  (int)($_ENV['ACCESS_TOKEN_EXPIRY']  ?? 900));     // 15 min
define('REFRESH_TOKEN_EXPIRY', (int)($_ENV['REFRESH_TOKEN_EXPIRY'] ?? 604800));  // 7 days

// -- Session -------------------------------------------------
define('SESSION_NAME',     $_ENV['SESSION_NAME']     ?? 'healthcare_session');
define('SESSION_LIFETIME', (int)($_ENV['SESSION_LIFETIME'] ?? 900));

// -- Error Reporting -----------------------------------------
if (APP_ENV === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// -- Storage Paths -------------------------------------------
define('LOG_PATH',     dirname(__DIR__, 2) . '/storage/logs/');
define('SESSION_PATH', dirname(__DIR__, 2) . '/storage/sessions/');
