-- Migration: 20260410_140000_add_execution_time_to_audit_logs.sql
-- Purpose: add execution_time_ms to older hy_audit_logs tables used before centralized middleware rollout

START TRANSACTION;

ALTER TABLE hy_audit_logs
    ADD COLUMN IF NOT EXISTS execution_time_ms INT NOT NULL DEFAULT 0 AFTER response_code;

COMMIT;