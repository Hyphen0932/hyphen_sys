-- Migration: 20260410_150000_upgrade_legacy_audit_logs_columns.sql
-- Purpose: add centralized middleware columns to legacy hy_audit_logs tables when they are missing

START TRANSACTION;

ALTER TABLE hy_audit_logs
    ADD COLUMN IF NOT EXISTS api_endpoint VARCHAR(255) NOT NULL DEFAULT '' AFTER page_key,
    ADD COLUMN IF NOT EXISTS action VARCHAR(150) NOT NULL DEFAULT '' AFTER api_endpoint,
    ADD COLUMN IF NOT EXISTS method VARCHAR(10) NOT NULL DEFAULT '' AFTER action,
    ADD COLUMN IF NOT EXISTS request_data LONGTEXT NULL AFTER method,
    ADD COLUMN IF NOT EXISTS response_data LONGTEXT NULL AFTER request_data,
    ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'success' AFTER response_data,
    ADD COLUMN IF NOT EXISTS error_message TEXT NULL AFTER status,
    ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45) NOT NULL DEFAULT '' AFTER error_message,
    ADD COLUMN IF NOT EXISTS user_agent VARCHAR(1000) NOT NULL DEFAULT '' AFTER ip_address,
    ADD KEY IF NOT EXISTS idx_hy_audit_logs_action (action),
    ADD KEY IF NOT EXISTS idx_hy_audit_logs_status (status),
    ADD KEY IF NOT EXISTS idx_hy_audit_logs_created_at (created_at);

COMMIT;