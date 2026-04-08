-- Migration: 20260408_121102_add_user_id_to_hy_users.sql
-- Purpose: describe the schema change here
START TRANSACTION;

ALTER TABLE hy_users
ADD COLUMN IF NOT EXISTS user_id VARCHAR(10) NULL AFTER staff_id;

COMMIT;