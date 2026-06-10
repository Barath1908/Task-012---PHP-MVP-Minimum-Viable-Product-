-- ============================================================
--  Healthcare MVP — Database Schema
--  Naming Convention : snake_case, lowercase plural tables
--  Engine            : InnoDB  |  Charset: utf8mb4
-- ============================================================

CREATE DATABASE IF NOT EXISTS t12_healthcare_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE t12_healthcare_db;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. TENANTS
--    One row per hospital / clinic
-- ============================================================
CREATE TABLE tenants (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    name          VARCHAR(150)    NOT NULL,
    slug          VARCHAR(100)    NOT NULL UNIQUE,
    email         VARCHAR(150)    NOT NULL UNIQUE,
    phone         VARCHAR(20)         NULL,
    address       TEXT                NULL,
    is_active     TINYINT(1)      NOT NULL DEFAULT 1,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at    DATETIME            NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- 2. ROLES
--    Seed: admin, provider, nurse, patient, pharmacist,
--          receptionist
-- ============================================================
CREATE TABLE roles (
    id            TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name          VARCHAR(50)      NOT NULL UNIQUE,  -- e.g. "admin"
    label         VARCHAR(50)      NOT NULL,         -- e.g. "Admin"
    created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- 3. USERS
--    All system actors.
--    One user = one role (MVP simplicity).
--    created_by / updated_by are NULL-safe:
--    the very first Admin has no prior user to reference.
-- ============================================================
CREATE TABLE users (
    id                INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    tenant_id         INT UNSIGNED     NOT NULL,
    role_id           TINYINT UNSIGNED NOT NULL,
    first_name        VARCHAR(80)      NOT NULL,
    last_name         VARCHAR(80)      NOT NULL,
    email             VARCHAR(150)     NOT NULL,
    phone             VARCHAR(20)          NULL,
    password_hash     VARCHAR(255)     NOT NULL,
    is_active         TINYINT(1)       NOT NULL DEFAULT 1,
    created_at        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at        DATETIME             NULL,
    created_by        INT UNSIGNED         NULL,   -- NULL for first admin
    updated_by        INT UNSIGNED         NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email_tenant (email, tenant_id),
    INDEX idx_users_tenant  (tenant_id),
    INDEX idx_users_role    (role_id),
    CONSTRAINT fk_users_tenant      FOREIGN KEY (tenant_id)  REFERENCES tenants (id),
    CONSTRAINT fk_users_role        FOREIGN KEY (role_id)    REFERENCES roles   (id),
    CONSTRAINT fk_users_created_by  FOREIGN KEY (created_by) REFERENCES users   (id),
    CONSTRAINT fk_users_updated_by  FOREIGN KEY (updated_by) REFERENCES users   (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- 4. REFRESH TOKENS
-- ============================================================
CREATE TABLE refresh_tokens (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED    NOT NULL,
    token_hash    VARCHAR(255)    NOT NULL UNIQUE,
    expires_at    DATETIME        NOT NULL,
    revoked       TINYINT(1)      NOT NULL DEFAULT 0,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_refresh_tokens_user (user_id),
    CONSTRAINT fk_refresh_tokens_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- 5. STAFF
--    Extra profile info for non-patient users
--    (doctors, nurses, pharmacists, receptionists, admins)
-- ============================================================
CREATE TABLE staff (
    id                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    tenant_id         INT UNSIGNED    NOT NULL,
    user_id           INT UNSIGNED    NOT NULL UNIQUE,
    specialization    VARCHAR(100)        NULL,
    qualification     VARCHAR(150)        NULL,
    license_number    VARCHAR(80)         NULL,
    is_active         TINYINT(1)      NOT NULL DEFAULT 1,
    created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at        DATETIME            NULL,
    created_by        INT UNSIGNED        NULL,
    updated_by        INT UNSIGNED        NULL,
    PRIMARY KEY (id),
    INDEX idx_staff_tenant (tenant_id),
    CONSTRAINT fk_staff_tenant      FOREIGN KEY (tenant_id)  REFERENCES tenants (id),
    CONSTRAINT fk_staff_user        FOREIGN KEY (user_id)    REFERENCES users   (id),
    CONSTRAINT fk_staff_created_by  FOREIGN KEY (created_by) REFERENCES users   (id),
    CONSTRAINT fk_staff_updated_by  FOREIGN KEY (updated_by) REFERENCES users   (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- 6. PATIENTS
--    Sensitive columns stored AES-encrypted
-- ============================================================
CREATE TABLE patients (
    id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    tenant_id           INT UNSIGNED    NOT NULL,
    user_id             INT UNSIGNED        NULL,   -- NULL if registered by staff only
    first_name          VARCHAR(80)     NOT NULL,
    last_name           VARCHAR(80)     NOT NULL,
    date_of_birth       DATE                NULL,
    gender              ENUM('male','female','other') NULL,
    phone               VARCHAR(20)         NULL,
    email               VARCHAR(150)        NULL,
    address             TEXT                NULL,
    -- AES-encrypted sensitive fields
    blood_group         TEXT                NULL,
    allergies           TEXT                NULL,
    medical_history     TEXT                NULL,
    emergency_contact   TEXT                NULL,
    is_active           TINYINT(1)      NOT NULL DEFAULT 1,
    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at          DATETIME            NULL,
    created_by          INT UNSIGNED        NULL,
    updated_by          INT UNSIGNED        NULL,
    PRIMARY KEY (id),
    INDEX idx_patients_tenant (tenant_id),
    INDEX idx_patients_user   (user_id),
    INDEX idx_patients_email  (email),
    CONSTRAINT fk_patients_tenant      FOREIGN KEY (tenant_id)  REFERENCES tenants (id),
    CONSTRAINT fk_patients_user        FOREIGN KEY (user_id)    REFERENCES users   (id),
    CONSTRAINT fk_patients_created_by  FOREIGN KEY (created_by) REFERENCES users   (id),
    CONSTRAINT fk_patients_updated_by  FOREIGN KEY (updated_by) REFERENCES users   (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- 7. APPOINTMENTS
--    end_time is NOT stored — calculated in PHP:
--    end_time = scheduled_at + INTERVAL duration_minutes MINUTE
-- ============================================================
CREATE TABLE appointments (
    id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    tenant_id           INT UNSIGNED    NOT NULL,
    patient_id          INT UNSIGNED    NOT NULL,
    provider_id         INT UNSIGNED    NOT NULL,   -- role_id must = Provider; enforced in AppointmentService.php
    scheduled_at        DATETIME        NOT NULL,
    duration_minutes    SMALLINT        NOT NULL DEFAULT 30,
    status              ENUM(
                            'pending',
                            'confirmed',
                            'in_progress',
                            'completed',
                            'cancelled',
                            'no_show'
                        )               NOT NULL DEFAULT 'pending',
    reason              TEXT                NULL,
    notes               TEXT                NULL,
    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at          DATETIME            NULL,
    created_by          INT UNSIGNED    NOT NULL,
    updated_by          INT UNSIGNED        NULL,
    PRIMARY KEY (id),
    INDEX idx_appointments_tenant    (tenant_id),
    INDEX idx_appointments_patient   (patient_id),
    INDEX idx_appointments_provider  (provider_id),
    INDEX idx_appointments_scheduled (scheduled_at),
    INDEX idx_appointments_status    (status),
    CONSTRAINT fk_appointments_tenant      FOREIGN KEY (tenant_id)   REFERENCES tenants  (id),
    CONSTRAINT fk_appointments_patient     FOREIGN KEY (patient_id)  REFERENCES patients (id),
    CONSTRAINT fk_appointments_provider    FOREIGN KEY (provider_id) REFERENCES users    (id),
    CONSTRAINT fk_appointments_created_by  FOREIGN KEY (created_by)  REFERENCES users    (id),
    CONSTRAINT fk_appointments_updated_by  FOREIGN KEY (updated_by)  REFERENCES users    (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- 8. PRESCRIPTIONS
--    medications & instructions stored AES-encrypted
-- ============================================================
CREATE TABLE prescriptions (
    id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    tenant_id           INT UNSIGNED    NOT NULL,
    appointment_id      INT UNSIGNED        NULL,
    patient_id          INT UNSIGNED    NOT NULL,
    provider_id         INT UNSIGNED    NOT NULL,   -- role_id must = Provider; enforced in AppointmentService.php
    pharmacist_id       INT UNSIGNED        NULL,
    -- AES-encrypted
    medications         TEXT            NOT NULL,
    instructions        TEXT                NULL,
    status              ENUM(
                            'issued',
                            'verified',
                            'dispensed',
                            'cancelled'
                        )               NOT NULL DEFAULT 'issued',
    dispensed_at        DATETIME            NULL,
    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at          DATETIME            NULL,
    created_by          INT UNSIGNED        NULL,
    updated_by          INT UNSIGNED        NULL,
    PRIMARY KEY (id),
    INDEX idx_prescriptions_tenant   (tenant_id),
    INDEX idx_prescriptions_patient  (patient_id),
    INDEX idx_prescriptions_provider (provider_id),
    INDEX idx_prescriptions_status   (status),
    CONSTRAINT fk_prescriptions_tenant      FOREIGN KEY (tenant_id)      REFERENCES tenants      (id),
    CONSTRAINT fk_prescriptions_appt        FOREIGN KEY (appointment_id) REFERENCES appointments (id),
    CONSTRAINT fk_prescriptions_patient     FOREIGN KEY (patient_id)     REFERENCES patients     (id),
    CONSTRAINT fk_prescriptions_provider    FOREIGN KEY (provider_id)    REFERENCES users        (id),
    CONSTRAINT fk_prescriptions_pharmacist  FOREIGN KEY (pharmacist_id)  REFERENCES users        (id),
    CONSTRAINT fk_prescriptions_created_by  FOREIGN KEY (created_by)     REFERENCES users        (id),
    CONSTRAINT fk_prescriptions_updated_by  FOREIGN KEY (updated_by)     REFERENCES users        (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- 9. MESSAGES  (appointment notes / basic chat)
--    content stored AES-encrypted
-- ============================================================
CREATE TABLE messages (
    id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    tenant_id           INT UNSIGNED    NOT NULL,
    appointment_id      INT UNSIGNED        NULL,
    sender_id           INT UNSIGNED    NOT NULL,
    receiver_id         INT UNSIGNED        NULL,   -- NULL = clinical note / broadcast
    content             TEXT            NOT NULL,   -- AES-encrypted
    is_read             TINYINT(1)      NOT NULL DEFAULT 0,
    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at          DATETIME            NULL,
    PRIMARY KEY (id),
    INDEX idx_messages_tenant      (tenant_id),
    INDEX idx_messages_appointment (appointment_id),
    INDEX idx_messages_sender      (sender_id),
    CONSTRAINT fk_messages_tenant      FOREIGN KEY (tenant_id)      REFERENCES tenants      (id),
    CONSTRAINT fk_messages_appointment FOREIGN KEY (appointment_id) REFERENCES appointments (id),
    CONSTRAINT fk_messages_sender      FOREIGN KEY (sender_id)      REFERENCES users        (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- 10. INVOICES
-- ============================================================
CREATE TABLE invoices (
    id                  INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    tenant_id           INT UNSIGNED        NOT NULL,
    patient_id          INT UNSIGNED        NOT NULL,
    appointment_id      INT UNSIGNED            NULL,
    issued_by           INT UNSIGNED        NOT NULL,
    total_amount        DECIMAL(10,2)       NOT NULL DEFAULT 0.00,
    discount_amount     DECIMAL(10,2)       NOT NULL DEFAULT 0.00,
    tax_amount          DECIMAL(10,2)       NOT NULL DEFAULT 0.00,
    final_amount        DECIMAL(10,2)       NOT NULL DEFAULT 0.00,
    status              ENUM(
                            'draft',
                            'issued',
                            'partially_paid',
                            'paid',
                            'cancelled'
                        )                   NOT NULL DEFAULT 'draft',
    due_date            DATE                    NULL,
    notes               TEXT                    NULL,
    created_at          DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at          DATETIME                NULL,
    created_by          INT UNSIGNED            NULL,
    updated_by          INT UNSIGNED            NULL,
    PRIMARY KEY (id),
    INDEX idx_invoices_tenant  (tenant_id),
    INDEX idx_invoices_patient (patient_id),
    INDEX idx_invoices_status  (status),
    CONSTRAINT fk_invoices_tenant      FOREIGN KEY (tenant_id)      REFERENCES tenants      (id),
    CONSTRAINT fk_invoices_patient     FOREIGN KEY (patient_id)     REFERENCES patients     (id),
    CONSTRAINT fk_invoices_appt        FOREIGN KEY (appointment_id) REFERENCES appointments (id),
    CONSTRAINT fk_invoices_issuer      FOREIGN KEY (issued_by)      REFERENCES users        (id),
    CONSTRAINT fk_invoices_created_by  FOREIGN KEY (created_by)     REFERENCES users        (id),
    CONSTRAINT fk_invoices_updated_by  FOREIGN KEY (updated_by)     REFERENCES users        (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- 11. PAYMENTS
-- ============================================================
CREATE TABLE payments (
    id                  INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    tenant_id           INT UNSIGNED        NOT NULL,
    invoice_id          INT UNSIGNED        NOT NULL,
    patient_id          INT UNSIGNED        NOT NULL,
    amount              DECIMAL(10,2)       NOT NULL,
    payment_method      ENUM(
                            'cash',
                            'card',
                            'upi',
                            'bank_transfer',
                            'insurance',
                            'other'
                        )                   NOT NULL DEFAULT 'cash',
    transaction_ref     VARCHAR(150)            NULL,
    status              ENUM(
                            'pending',
                            'completed',
                            'failed',
                            'refunded'
                        )                   NOT NULL DEFAULT 'pending',
    paid_at             DATETIME                NULL,
    created_at          DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by          INT UNSIGNED            NULL,
    updated_by          INT UNSIGNED            NULL,
    PRIMARY KEY (id),
    INDEX idx_payments_tenant  (tenant_id),
    INDEX idx_payments_invoice (invoice_id),
    INDEX idx_payments_patient (patient_id),
    INDEX idx_payments_status  (status),
    CONSTRAINT fk_payments_tenant      FOREIGN KEY (tenant_id)  REFERENCES tenants  (id),
    CONSTRAINT fk_payments_invoice     FOREIGN KEY (invoice_id) REFERENCES invoices (id),
    CONSTRAINT fk_payments_patient     FOREIGN KEY (patient_id) REFERENCES patients (id),
    CONSTRAINT fk_payments_created_by  FOREIGN KEY (created_by) REFERENCES users    (id),
    CONSTRAINT fk_payments_updated_by  FOREIGN KEY (updated_by) REFERENCES users    (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- 12. SETTINGS  (per-tenant key-value config)
-- ============================================================
CREATE TABLE settings (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    tenant_id     INT UNSIGNED    NOT NULL,
    key_name      VARCHAR(100)    NOT NULL,
    value         TEXT                NULL,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by    INT UNSIGNED        NULL,
    updated_by    INT UNSIGNED        NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_settings_tenant_key (tenant_id, key_name),
    INDEX idx_settings_tenant (tenant_id),
    CONSTRAINT fk_settings_tenant      FOREIGN KEY (tenant_id)  REFERENCES tenants (id),
    CONSTRAINT fk_settings_created_by  FOREIGN KEY (created_by) REFERENCES users   (id),
    CONSTRAINT fk_settings_updated_by  FOREIGN KEY (updated_by) REFERENCES users   (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- SEED DATA — Roles
-- ============================================================
INSERT INTO roles (name, label) VALUES
    ('admin',        'Admin'),
    ('provider',     'Provider'),
    ('nurse',        'Nurse'),
    ('patient',      'Patient'),
    ('pharmacist',   'Pharmacist'),
    ('receptionist', 'Receptionist');


SET FOREIGN_KEY_CHECKS = 1;
-- ============================================================
-- END OF SCHEMA
-- ============================================================
