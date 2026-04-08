-- Migration: 20260408_123000_create_email_notification_tables.sql
-- Purpose: create email notification template and log tables

START TRANSACTION;

CREATE TABLE IF NOT EXISTS hy_email_notification_templates (
    id INT NOT NULL AUTO_INCREMENT,
    category VARCHAR(100) NOT NULL,
    notification_code VARCHAR(100) NOT NULL,
    template_name VARCHAR(150) NOT NULL,
    email_subject VARCHAR(255) NOT NULL,
    body_html LONGTEXT NOT NULL,
    body_text LONGTEXT NULL,
    variables_json LONGTEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by VARCHAR(50) NULL,
    updated_by VARCHAR(50) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hy_email_notification_templates_code (notification_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS hy_email_notification_logs (
    id BIGINT NOT NULL AUTO_INCREMENT,
    template_id INT NULL,
    notification_code VARCHAR(100) NOT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    cc_json LONGTEXT NULL,
    bcc_json LONGTEXT NULL,
    email_subject VARCHAR(255) NOT NULL,
    body_html LONGTEXT NOT NULL,
    body_text LONGTEXT NULL,
    payload_json LONGTEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'queued',
    error_message TEXT NULL,
    created_by VARCHAR(50) NULL,
    sent_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_hy_email_notification_logs_template_id (template_id),
    KEY idx_hy_email_notification_logs_code (notification_code),
    KEY idx_hy_email_notification_logs_status (status),
    CONSTRAINT fk_hy_email_notification_logs_template_id
        FOREIGN KEY (template_id) REFERENCES hy_email_notification_templates (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;