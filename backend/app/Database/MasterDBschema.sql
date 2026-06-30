-- Run this in your MySQL to create the master DB

CREATE DATABASE IF NOT EXISTS healthcare_master_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE healthcare_master_db;

CREATE TABLE tenants (
    id            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    company_name  VARCHAR(150)     NOT NULL,
    subdomain     VARCHAR(100)     NOT NULL UNIQUE,
    is_active     TINYINT(1)       NOT NULL DEFAULT 1,
    db_name       VARCHAR(150)     NOT NULL,
    theme         ENUM('dark','light','warm') NOT NULL DEFAULT 'dark',
    plan_type     ENUM('free','pro','enterprise') NOT NULL DEFAULT 'free',
    admin_email   VARCHAR(255)     NOT NULL,
    created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at    DATETIME             NULL,
    PRIMARY KEY (id),
    INDEX idx_subdomain (subdomain)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;