-- Migration: 20260408_160000_create_email_configurations_table.sql
-- Purpose: create database-backed email transport configuration table

START TRANSACTION;

CREATE TABLE IF NOT EXISTS hy_email_configurations (
    id INT NOT NULL AUTO_INCREMENT,
    provider VARCHAR(50) NOT NULL DEFAULT 'gmail',
    host VARCHAR(255) NOT NULL,
    port INT NOT NULL DEFAULT 587,
    encryption VARCHAR(20) NOT NULL DEFAULT 'tls',
    username VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    from_address VARCHAR(255) NOT NULL,
    from_name VARCHAR(255) NOT NULL,
    reply_to VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by VARCHAR(50) NULL,
    updated_by VARCHAR(50) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_hy_email_configurations_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;