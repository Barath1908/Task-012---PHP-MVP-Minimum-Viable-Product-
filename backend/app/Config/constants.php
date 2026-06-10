<?php

// ============================================================
//  constants.php — App-Wide Constants
//  Fixed values used across controllers, services, middleware.
//  Never put secrets here — secrets go in .env / config.php.
// ============================================================

// -- Roles (matches roles.name in DB) ------------------------
define('ROLE_ADMIN',        'admin');
define('ROLE_PROVIDER',     'provider');
define('ROLE_NURSE',        'nurse');
define('ROLE_PATIENT',      'patient');
define('ROLE_PHARMACIST',   'pharmacist');
define('ROLE_RECEPTIONIST', 'receptionist');

// -- Appointment Status --------------------------------------
define('APPT_PENDING',     'pending');
define('APPT_CONFIRMED',   'confirmed');
define('APPT_IN_PROGRESS', 'in_progress');
define('APPT_COMPLETED',   'completed');
define('APPT_CANCELLED',   'cancelled');
define('APPT_NO_SHOW',     'no_show');

// -- Prescription Status -------------------------------------
define('RX_ISSUED',    'issued');
define('RX_VERIFIED',  'verified');
define('RX_DISPENSED', 'dispensed');
define('RX_CANCELLED', 'cancelled');

// -- Invoice Status ------------------------------------------
define('INV_DRAFT',          'draft');
define('INV_ISSUED',         'issued');
define('INV_PARTIALLY_PAID', 'partially_paid');
define('INV_PAID',           'paid');
define('INV_CANCELLED',      'cancelled');

// -- Payment Status ------------------------------------------
define('PAY_PENDING',   'pending');
define('PAY_COMPLETED', 'completed');
define('PAY_FAILED',    'failed');
define('PAY_REFUNDED',  'refunded');

// -- Payment Methods -----------------------------------------
define('PAY_CASH',          'cash');
define('PAY_CARD',          'card');
define('PAY_UPI',           'upi');
define('PAY_BANK_TRANSFER', 'bank_transfer');
define('PAY_INSURANCE',     'insurance');
define('PAY_OTHER',         'other');

// -- HTTP Status Codes (commonly used) -----------------------
define('HTTP_OK',                   200);
define('HTTP_CREATED',              201);
define('HTTP_BAD_REQUEST',          400);
define('HTTP_UNAUTHORIZED',         401);
define('HTTP_FORBIDDEN',            403);
define('HTTP_NOT_FOUND',            404);
define('HTTP_CONFLICT',             409);
define('HTTP_UNPROCESSABLE',        422);
define('HTTP_INTERNAL_SERVER_ERROR',500);

// -- API Response Status Labels ------------------------------
define('STATUS_SUCCESS', true);
define('STATUS_ERROR',   false);

// -- Soft Delete / Active Flags ------------------------------
define('IS_ACTIVE',   1);
define('IS_INACTIVE', 0);
define('IS_DELETED',  1);  // used to check deleted_at IS NOT NULL

// -- Token Types ---------------------------------------------
define('TOKEN_ACCESS',  'access');
define('TOKEN_REFRESH', 'refresh');

// -- AES Cipher ----------------------------------------------
define('AES_CIPHER', 'AES-256-CBC');

// -- Default Appointment Duration (minutes) ------------------
define('DEFAULT_APPT_DURATION', 30);
