<?php

// ============================================================
//  Response.php — Standardized Encrypted API Responses
//  Every response is AES-encrypted before sending.
//  Structure:
//  {
//    "csrf_token": "...",   // new token for next request
//    "payload":    "..."    // AES-encrypted JSON
//  }
// ============================================================

require_once __DIR__ . '/../Security/AES.php';
require_once __DIR__ . '/../Security/CSRF.php';

class Response
{
    // --------------------------------------------------------
    //  success()
    //  Sends a 200 (or custom) encrypted success response.
    // --------------------------------------------------------
    public static function success(
        mixed  $data    = [],
        string $message = 'Success',
        int    $code    = HTTP_OK
    ): void {
        self::send([
            'status'  => STATUS_SUCCESS,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    // --------------------------------------------------------
    //  created()
    //  Sends a 201 encrypted response for resource creation.
    // --------------------------------------------------------
    public static function created(
        mixed  $data    = [],
        string $message = 'Created successfully'
    ): void {
        self::send([
            'status'  => STATUS_SUCCESS,
            'message' => $message,
            'data'    => $data,
        ], HTTP_CREATED);
    }

    // --------------------------------------------------------
    //  error()
    //  Sends an encrypted error response.
    // --------------------------------------------------------
    public static function error(
        string $message = 'An error occurred',
        int    $code    = HTTP_BAD_REQUEST,
        mixed  $errors  = null
    ): void {
        $body = [
            'status'  => STATUS_ERROR,
            'message' => $message,
        ];

        if ($errors !== null) {
            $body['errors'] = $errors;
        }

        self::send($body, $code);
    }

    // --------------------------------------------------------
    //  unauthorized()
    //  401 — missing or invalid token.
    // --------------------------------------------------------
    public static function unauthorized(string $message = 'Unauthorized'): void
    {
        self::error($message, HTTP_UNAUTHORIZED);
    }

    // --------------------------------------------------------
    //  forbidden()
    //  403 — valid token but insufficient role/permission.
    // --------------------------------------------------------
    public static function forbidden(string $message = 'Forbidden'): void
    {
        self::error($message, HTTP_FORBIDDEN);
    }

    // --------------------------------------------------------
    //  notFound()
    //  404 — resource not found.
    // --------------------------------------------------------
    public static function notFound(string $message = 'Not found'): void
    {
        self::error($message, HTTP_NOT_FOUND);
    }

    // --------------------------------------------------------
    //  validationError()
    //  422 — request failed validation.
    // --------------------------------------------------------
    public static function validationError(mixed $errors, string $message = 'Validation failed'): void
    {
        self::error($message, HTTP_UNPROCESSABLE, $errors);
    }

    // ========================================================
    //  PRIVATE — core send logic
    // ========================================================
     private static function send(array $body, int $code): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');

        // TEMP: skip encryption for Postman testing
        echo json_encode([
            'csrf_token' => CSRF::getToken() ?? '',
            'payload'    => $body,
        ]);

        exit;
    }
   
}
