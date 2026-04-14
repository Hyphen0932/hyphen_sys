-- Migration: 20260414_150000_create_ai_job_tables.sql
-- Purpose: create asynchronous AI job tables for polling-based query execution

START TRANSACTION;

CREATE TABLE IF NOT EXISTS hy_ai_jobs (
    id BIGINT NOT NULL AUTO_INCREMENT,
    job_id CHAR(36) NOT NULL,
    staff_id VARCHAR(50) NOT NULL,
    conversation_id VARCHAR(100) NOT NULL,
    job_type VARCHAR(50) NOT NULL DEFAULT 'nl2sql',
    mode_key VARCHAR(50) NOT NULL DEFAULT 'nl2sql',
    model_key VARCHAR(150) NOT NULL,
    question_text TEXT NOT NULL,
    request_payload_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`request_payload_json`)),
    result_payload_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (`result_payload_json` IS NULL OR json_valid(`result_payload_json`)),
    status ENUM('queued', 'running', 'completed', 'failed', 'timeout', 'cancelled') NOT NULL DEFAULT 'queued',
    priority INT NOT NULL DEFAULT 100,
    include_rows TINYINT(1) NOT NULL DEFAULT 0,
    row_count INT NOT NULL DEFAULT 0,
    attempt_count INT NOT NULL DEFAULT 0,
    queue_name VARCHAR(100) NOT NULL DEFAULT 'queue:ai:nl2sql',
    worker_id VARCHAR(100) DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    queued_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL DEFAULT NULL,
    finished_at TIMESTAMP NULL DEFAULT NULL,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hy_ai_jobs_job_id (job_id),
    KEY idx_hy_ai_jobs_staff_status (staff_id, status, queued_at),
    KEY idx_hy_ai_jobs_queue_status (queue_name, status, queued_at),
    KEY idx_hy_ai_jobs_mode_status (mode_key, status, queued_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS hy_ai_job_events (
    id BIGINT NOT NULL AUTO_INCREMENT,
    job_id CHAR(36) NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    message_text TEXT DEFAULT NULL,
    payload_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (`payload_json` IS NULL OR json_valid(`payload_json`)),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_hy_ai_job_events_job (job_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;