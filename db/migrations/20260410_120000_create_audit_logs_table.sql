-- Migration: 20260410_120000_create_audit_logs_table.sql
-- Purpose: create system audit log table for centralized API middleware logging

START TRANSACTION;

CREATE TABLE IF NOT EXISTS hy_audit_logs (
    id BIGINT NOT NULL AUTO_INCREMENT,
    staff_id VARCHAR(50) NOT NULL DEFAULT '',
    page_key VARCHAR(150) NOT NULL DEFAULT '',
    api_endpoint VARCHAR(255) NOT NULL,
    action VARCHAR(150) NOT NULL,
    method VARCHAR(10) NOT NULL,
    request_data LONGTEXT NULL,
    response_data LONGTEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'success',
    error_message TEXT NULL,
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    user_agent VARCHAR(1000) NOT NULL DEFAULT '',
    response_code SMALLINT NOT NULL DEFAULT 200,
    execution_time_ms INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_hy_audit_logs_staff_id (staff_id),
    KEY idx_hy_audit_logs_page_key (page_key),
    KEY idx_hy_audit_logs_action (action),
    KEY idx_hy_audit_logs_status (status),
    KEY idx_hy_audit_logs_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;