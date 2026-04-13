-- Migration: 20260413_110000_create_nl2sql_configuration_tables.sql
-- Purpose: create database-backed NL2SQL global configuration and per-user policy tables

START TRANSACTION;

CREATE TABLE IF NOT EXISTS hy_nl2sql_configurations (
    id INT NOT NULL AUTO_INCREMENT,
    provider VARCHAR(50) NOT NULL DEFAULT 'ollama',
    model_name VARCHAR(100) NOT NULL DEFAULT 'qwen2.5-coder:7b',
    allowed_tables_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`allowed_tables_json`)),
    result_row_limit INT NOT NULL DEFAULT 50,
    prompt_notes TEXT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by VARCHAR(50) NULL,
    updated_by VARCHAR(50) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_hy_nl2sql_configurations_active (is_active, is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS hy_nl2sql_user_policies (
    id INT NOT NULL AUTO_INCREMENT,
    staff_id VARCHAR(50) NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    allowed_tables_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`allowed_tables_json`)),
    max_row_limit INT NULL,
    can_view_sql TINYINT(1) NOT NULL DEFAULT 1,
    can_include_rows TINYINT(1) NOT NULL DEFAULT 1,
    notes TEXT NULL,
    created_by VARCHAR(50) NULL,
    updated_by VARCHAR(50) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hy_nl2sql_user_policies_staff_id (staff_id),
    KEY idx_hy_nl2sql_user_policies_enabled (is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO hy_nl2sql_configurations (
    provider,
    model_name,
    allowed_tables_json,
    result_row_limit,
    prompt_notes,
    is_enabled,
    is_active,
    created_by,
    updated_by
)
SELECT
    'ollama',
    'qwen2.5-coder:7b',
    JSON_ARRAY('hy_users', 'hy_user_menu', 'hy_user_pages', 'hy_user_permissions'),
    50,
    'Keep answers grounded in the returned rows and use the existing Hyphen System terminology.',
    1,
    1,
    'system',
    'system'
WHERE NOT EXISTS (
    SELECT 1 FROM hy_nl2sql_configurations WHERE is_active = 1
);

COMMIT;