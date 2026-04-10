-- Migration: 20260410_130000_upgrade_audit_logs_table.sql
-- Purpose: add missing columns to older hy_audit_logs tables created before centralized audit rollout

START TRANSACTION;

ALTER TABLE hy_audit_logs
    ADD COLUMN IF NOT EXISTS page_key VARCHAR(150) NOT NULL DEFAULT '' AFTER staff_id,
    ADD COLUMN IF NOT EXISTS response_code SMALLINT NOT NULL DEFAULT 200 AFTER user_agent,
    ADD KEY IF NOT EXISTS idx_hy_audit_logs_page_key (page_key);

COMMIT;