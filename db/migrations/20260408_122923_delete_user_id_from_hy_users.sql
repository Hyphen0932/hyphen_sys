-- Migration: 20260408_122923_delete_user_id_from_hy_users.sql
-- Purpose: describe the schema change here

START TRANSACTION;

-- delete user_id column from hy_users table
ALTER TABLE hy_users
DROP COLUMN IF EXISTS user_id;

COMMIT;