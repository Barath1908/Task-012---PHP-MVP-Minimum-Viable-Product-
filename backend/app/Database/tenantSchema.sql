SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- ROLES
-- ============================================================
CREATE TABLE IF NOT EXISTS roles (
    id         TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(50)      NOT NULL UNIQUE,
    label      VARCHAR(50)      NOT NULL,
    created_at DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO roles (name, label) VALUES
    ('admin',        'Admin'),
    ('provider',     'Provider'),
    ('nurse',        'Nurse'),
    ('patient',      'Patient'),
    ('pharmacist',   'Pharmacist'),
    ('receptionist', 'Receptionist');

-- ============================================================
-- USERS
-- No tenant_id — this DB belongs to one tenant only
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    role_id       TINYINT UNSIGNED NOT NULL,
    first_name    VARCHAR(255)     NOT NULL,
    last_name     VARCHAR(255)     NOT NULL,
    email         VARCHAR(255)     NOT NULL,
    email_hash    VARCHAR(64)      NOT NULL UNIQUE,
    phone         VARCHAR(50)          NULL,
    password_hash VARCHAR(255)     NOT NULL,
    is_active     TINYINT(1)       NOT NULL DEFAULT 1,
    created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at    DATETIME             NULL,
    PRIMARY KEY (id),
    INDEX idx_users_role (role_id),
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- REFRESH TOKENS
-- ============================================================
CREATE TABLE IF NOT EXISTS refresh_tokens (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    token_hash VARCHAR(255) NOT NULL UNIQUE,
    expires_at DATETIME     NOT NULL,
    revoked    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_refresh_user (user_id),
    CONSTRAINT fk_refresh_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- STAFF
-- No tenant_id
-- ============================================================
CREATE TABLE IF NOT EXISTS staff (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id        INT UNSIGNED NOT NULL UNIQUE,
    specialization VARCHAR(100)     NULL,
    qualification  VARCHAR(150)     NULL,
    license_number VARCHAR(80)      NULL,
    is_active      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at     DATETIME         NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_staff_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- PATIENTS
-- No tenant_id
-- ============================================================
CREATE TABLE IF NOT EXISTS patients (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id           INT UNSIGNED     NULL,
    first_name        VARCHAR(255) NOT NULL,
    last_name         VARCHAR(255) NOT NULL,
    date_of_birth     VARCHAR(255)     NULL,
    age               VARCHAR(50)      NULL,
    gender            VARCHAR(50)      NULL,
    phone             VARCHAR(255)     NULL,
    email             VARCHAR(255)     NULL,
    address           TEXT             NULL,
    blood_group       TEXT             NULL,
    allergies         TEXT             NULL,
    medical_history   TEXT             NULL,
    emergency_contact TEXT             NULL,
    is_active         TINYINT(1)   NOT NULL DEFAULT 1,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at        DATETIME         NULL,
    PRIMARY KEY (id),
    INDEX idx_patients_user (user_id),
    CONSTRAINT fk_patients_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- APPOINTMENTS
-- No tenant_id
-- ============================================================
CREATE TABLE IF NOT EXISTS appointments (
    id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    patient_id       INT UNSIGNED  NOT NULL,
    provider_id      INT UNSIGNED  NOT NULL,
    scheduled_at     DATETIME      NOT NULL,
    duration_minutes SMALLINT      NOT NULL DEFAULT 30,
    status           ENUM(
                         'pending',
                         'confirmed',
                         'in_progress',
                         'completed',
                         'cancelled',
                         'no_show'
                     )             NOT NULL DEFAULT 'pending',
    reason           TEXT              NULL,
    notes            TEXT              NULL,
    created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at       DATETIME          NULL,
    PRIMARY KEY (id),
    INDEX idx_appt_patient  (patient_id),
    INDEX idx_appt_provider (provider_id),
    INDEX idx_appt_schedule (scheduled_at),
    INDEX idx_appt_status   (status),
    CONSTRAINT fk_appt_patient  FOREIGN KEY (patient_id)  REFERENCES patients (id),
    CONSTRAINT fk_appt_provider FOREIGN KEY (provider_id) REFERENCES users    (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- PRESCRIPTIONS
-- No tenant_id
-- ============================================================
CREATE TABLE IF NOT EXISTS prescriptions (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    appointment_id INT UNSIGNED     NULL,
    patient_id     INT UNSIGNED NOT NULL,
    provider_id    INT UNSIGNED NOT NULL,
    pharmacist_id  INT UNSIGNED     NULL,
    medications    TEXT         NOT NULL,
    instructions   TEXT             NULL,
    status         ENUM(
                       'issued',
                       'verified',
                       'dispensed',
                       'cancelled'
                   )            NOT NULL DEFAULT 'issued',
    dispensed_at   DATETIME         NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at     DATETIME         NULL,
    PRIMARY KEY (id),
    INDEX idx_rx_patient  (patient_id),
    INDEX idx_rx_provider (provider_id),
    INDEX idx_rx_status   (status),
    CONSTRAINT fk_rx_appointment FOREIGN KEY (appointment_id) REFERENCES appointments (id),
    CONSTRAINT fk_rx_patient     FOREIGN KEY (patient_id)     REFERENCES patients     (id),
    CONSTRAINT fk_rx_provider    FOREIGN KEY (provider_id)    REFERENCES users        (id),
    CONSTRAINT fk_rx_pharmacist  FOREIGN KEY (pharmacist_id)  REFERENCES users        (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- MESSAGES
-- No tenant_id
-- ============================================================
CREATE TABLE IF NOT EXISTS messages (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    appointment_id INT UNSIGNED     NULL,
    sender_id      INT UNSIGNED NOT NULL,
    content        TEXT         NOT NULL,
    is_note        TINYINT(1)   NOT NULL DEFAULT 0,
    is_read        TINYINT(1)   NOT NULL DEFAULT 0,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at     DATETIME         NULL,
    PRIMARY KEY (id),
    INDEX idx_msg_appointment (appointment_id),
    INDEX idx_msg_sender      (sender_id),
    CONSTRAINT fk_msg_appointment FOREIGN KEY (appointment_id) REFERENCES appointments (id),
    CONSTRAINT fk_msg_sender      FOREIGN KEY (sender_id)      REFERENCES users        (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- INVOICES
-- No tenant_id
-- ============================================================
CREATE TABLE IF NOT EXISTS invoices (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    patient_id      INT UNSIGNED  NOT NULL,
    appointment_id  INT UNSIGNED      NULL,
    issued_by       INT UNSIGNED  NOT NULL,
    total_amount    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    tax_amount      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    final_amount    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status          ENUM(
                        'draft',
                        'issued',
                        'partially_paid',
                        'paid',
                        'cancelled'
                    )             NOT NULL DEFAULT 'draft',
    due_date        DATE              NULL,
    notes           TEXT              NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      DATETIME          NULL,
    PRIMARY KEY (id),
    INDEX idx_inv_patient (patient_id),
    INDEX idx_inv_status  (status),
    CONSTRAINT fk_inv_patient      FOREIGN KEY (patient_id)     REFERENCES patients     (id),
    CONSTRAINT fk_inv_appointment  FOREIGN KEY (appointment_id) REFERENCES appointments (id),
    CONSTRAINT fk_inv_issuer       FOREIGN KEY (issued_by)      REFERENCES users        (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- PAYMENTS
-- No tenant_id
-- ============================================================
CREATE TABLE IF NOT EXISTS payments (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    invoice_id      INT UNSIGNED  NOT NULL,
    patient_id      INT UNSIGNED  NOT NULL,
    amount          DECIMAL(10,2) NOT NULL,
    payment_method  ENUM(
                        'cash',
                        'card',
                        'upi',
                        'bank_transfer',
                        'insurance',
                        'other'
                    )             NOT NULL DEFAULT 'cash',
    transaction_ref VARCHAR(150)      NULL,
    status          ENUM(
                        'pending',
                        'completed',
                        'failed',
                        'refunded'
                    )             NOT NULL DEFAULT 'pending',
    paid_at         DATETIME          NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_pay_invoice (invoice_id),
    INDEX idx_pay_patient (patient_id),
    INDEX idx_pay_status  (status),
    CONSTRAINT fk_pay_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id),
    CONSTRAINT fk_pay_patient FOREIGN KEY (patient_id) REFERENCES patients (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;