# AI Async Queue Operations Guide

## Purpose

This guide defines the operational checks, maintenance workflow, and troubleshooting process for the asynchronous AI queue stack.

This document is for day-2 operations, not initial architecture design.

Use it when you need to:

- confirm that Redis, AI worker, and job polling are healthy
- troubleshoot stuck AI jobs
- investigate failed or timed-out jobs
- inspect queue and worker behavior
- plan routine cleanup and monitoring

Read this together with:

- `pages/sys_doc/ai_async_queue_architecture_guide.md`
- `pages/sys_doc/ai_service_model_deployment_guide.md`

## Active Environment Rule

For this project, queue operations and troubleshooting should be validated against the Docker development stack unless the issue is production-specific.

Use this operational baseline:

- platform: `http://127.0.0.1:8080/hyphen_sys`
- phpMyAdmin: `http://127.0.0.1:8081`
- AI service: `http://127.0.0.1:8001`
- Redis: Docker internal service `redis`
- worker: Docker service `ai-worker`
- database source of truth: Docker `db`

Do not use XAMPP local MySQL or any local Redis installation as the source of truth for queue troubleshooting.

## Current Async Stack

The current operational chain is:

1. `pages/sys_admin/system_advance_search.php`
2. `pages/sys_admin/action/sys_advance_search_api.php`
3. `hy_ai_jobs` and `hy_ai_job_events`
4. Redis queue
5. `ai-worker`
6. `ai-service`
7. Ollama on the Windows host

This means queue problems can come from any of these layers, not only Redis itself.

## Core Runtime Files

Operationally important files:

- `docker-compose.ai.yml`
- `ai_service/app/worker.py`
- `ai_service/app/config.py`
- `build/ai_jobs.php`
- `pages/sys_admin/action/sys_advance_search_api.php`
- `db/migrations/20260414_150000_create_ai_job_tables.sql`

## Core Tables

### `hy_ai_jobs`

This is the main operational truth table.

Important fields to watch:

- `job_id`
- `staff_id`
- `status`
- `queue_name`
- `worker_id`
- `attempt_count`
- `error_message`
- `queued_at`
- `started_at`
- `finished_at`
- `updated_at`

### `hy_ai_job_events`

This is the event trail for queue debugging.

Typical event progression:

- `queued`
- `running`
- `completed`

Possible failure progression:

- `queued`
- `running`
- `failed`

Timeout progression:

- `queued`
- `running`
- `timeout`

## Normal Healthy Flow

For a healthy request, you should observe:

1. frontend receives a `job_id`
2. `hy_ai_jobs.status = queued`
3. Redis queue receives one job payload
4. worker claims the job
5. `hy_ai_jobs.status = running`
6. worker calls `/query`
7. `hy_ai_jobs.status = completed`
8. frontend polling reads the completed result

If the final user page does not update, but `hy_ai_jobs` is already `completed`, the problem is usually in frontend polling or the PHP status endpoint, not Redis.

## Daily Health Checks

### 1. Confirm container status

```powershell
docker compose -p hyphen_sys_dev --env-file .env.dev -f docker-compose.yml -f docker-compose.dev.yml -f docker-compose.ai.yml ps
```

Expected:

- `app` is up
- `db` is healthy
- `redis` is healthy
- `ai-service` is up
- `ai-worker` is up

### 2. Confirm AI service health

```powershell
Invoke-RestMethod -Method Get -Uri http://localhost:8001/health | ConvertTo-Json -Depth 4
```

Expected:

- `service = ok`
- `database = ok`
- `ollama = ok`

### 3. Confirm async tables exist

Check in Docker MariaDB or phpMyAdmin:

- `hy_ai_jobs`
- `hy_ai_job_events`

### 4. Confirm worker can stay up

```powershell
docker compose -p hyphen_sys_dev --env-file .env.dev -f docker-compose.yml -f docker-compose.dev.yml -f docker-compose.ai.yml ps ai-worker
```

If the worker is restarting repeatedly, inspect logs immediately.

## Common Operational Commands

### View worker logs

```powershell
docker compose -p hyphen_sys_dev --env-file .env.dev -f docker-compose.yml -f docker-compose.dev.yml -f docker-compose.ai.yml logs -f ai-worker
```

### View Redis logs

```powershell
docker compose -p hyphen_sys_dev --env-file .env.dev -f docker-compose.yml -f docker-compose.dev.yml -f docker-compose.ai.yml logs -f redis
```

### View AI service logs

```powershell
docker compose -p hyphen_sys_dev --env-file .env.dev -f docker-compose.yml -f docker-compose.dev.yml -f docker-compose.ai.yml logs -f ai-service
```

### Rebuild worker after code changes

```powershell
docker compose -p hyphen_sys_dev --env-file .env.dev -f docker-compose.yml -f docker-compose.dev.yml -f docker-compose.ai.yml up -d --build ai-worker ai-service
```

### Restart Redis only

```powershell
docker compose -p hyphen_sys_dev --env-file .env.dev -f docker-compose.yml -f docker-compose.dev.yml -f docker-compose.ai.yml restart redis
```

## Useful SQL Checks

### Find recent jobs

```sql
SELECT job_id, staff_id, status, queue_name, worker_id, attempt_count, queued_at, started_at, finished_at
FROM hy_ai_jobs
ORDER BY id DESC
LIMIT 50;
```

### Find stuck running jobs

```sql
SELECT job_id, staff_id, status, worker_id, queued_at, started_at, updated_at
FROM hy_ai_jobs
WHERE status = 'running'
  AND started_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)
ORDER BY started_at ASC;
```

### Find queued jobs that are not moving

```sql
SELECT job_id, staff_id, status, queue_name, queued_at, updated_at
FROM hy_ai_jobs
WHERE status = 'queued'
  AND queued_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
ORDER BY queued_at ASC;
```

### Read one job's event trail

```sql
SELECT event_type, message_text, created_at
FROM hy_ai_job_events
WHERE job_id = 'replace-with-job-id'
ORDER BY id ASC;
```

## Common Failure Patterns

### Problem 1: page remains on queued forever

Most likely causes:

- Redis is down
- worker is down
- queue name mismatch between PHP and worker

What to check:

1. `docker compose ... ps`
2. `redis` health
3. `ai-worker` container state
4. `hy_ai_jobs.queue_name`
5. environment values:
   - `AI_JOB_QUEUE_NAME`
   - `AI_WORKER_QUEUES`

Typical diagnosis:

- if jobs are inserted but never move to `running`, the worker did not consume them

### Problem 2: job changes to running but never completes

Most likely causes:

- AI service is stuck
- Ollama is slow or unavailable
- worker is waiting on a model response
- worker finished badly and did not write final state

What to check:

1. `ai-service` health endpoint
2. `ai-worker` logs
3. `hy_ai_job_events`
4. `hy_ai_jobs.updated_at`

Typical diagnosis:

- if events contain `running` but no `completed`, `failed`, or `timeout`, then the worker likely got stuck mid-execution

### Problem 3: job is completed in MySQL but UI still looks stuck

Most likely causes:

- frontend polling issue
- PHP `get_job` endpoint issue
- stale browser page state

What to check:

1. query `hy_ai_jobs` directly
2. call the `get_job` endpoint directly
3. browser devtools network tab

Typical diagnosis:

- if MySQL shows `completed` and the PHP endpoint returns completed payload, the issue is in browser rendering or polling logic

### Problem 4: job fails immediately after queueing

Most likely causes:

- Redis enqueue failed
- invalid request payload stored by PHP
- worker could not decode job payload

What to check:

1. `error_message` in `hy_ai_jobs`
2. `queued` and `failed` events in `hy_ai_job_events`
3. PHP endpoint response message

### Problem 5: job fails after running

Most likely causes:

- AI service returned non-JSON output
- AI service returned HTTP error
- model response timed out
- database writeback failed

What to check:

1. `ai-worker` logs
2. `ai-service` logs
3. `hy_ai_jobs.error_message`
4. `hy_ai_job_events`

## Redis-Specific Checks

The current stack uses Redis only as the queue transport layer.

Important reminder:

- final result state lives in MySQL, not Redis
- queue loss or queue restart does not automatically delete completed jobs already written to MySQL

Operational checks:

### Ping Redis inside the container

```powershell
docker compose -p hyphen_sys_dev --env-file .env.dev -f docker-compose.yml -f docker-compose.dev.yml -f docker-compose.ai.yml exec -T redis redis-cli ping
```

Expected result:

- `PONG`

### Inspect queue length

```powershell
docker compose -p hyphen_sys_dev --env-file .env.dev -f docker-compose.yml -f docker-compose.dev.yml -f docker-compose.ai.yml exec -T redis redis-cli LLEN queue:ai:nl2sql
```

Interpretation:

- small transient value is normal
- continuously growing value means workers are not keeping up or are not consuming

### Inspect queued payloads

```powershell
docker compose -p hyphen_sys_dev --env-file .env.dev -f docker-compose.yml -f docker-compose.dev.yml -f docker-compose.ai.yml exec -T redis redis-cli LRANGE queue:ai:nl2sql 0 10
```

Use this only for troubleshooting. Do not manually alter queue contents in normal operation unless you fully understand the impact.

## Worker-Specific Checks

### Confirm queue configuration

The current worker depends on:

- `AI_REDIS_HOST`
- `AI_REDIS_PORT`
- `AI_REDIS_DB`
- `AI_JOB_QUEUE_NAME`
- `AI_WORKER_QUEUES`
- `AI_WORKER_BLOCK_TIMEOUT`
- `AI_WORKER_HTTP_TIMEOUT`

If PHP writes to one queue and the worker listens to another, jobs will stay queued forever.

### Confirm worker claim behavior

Healthy progression in `hy_ai_jobs`:

- `queued`
- `running` with `worker_id`
- `completed` or `failed` or `timeout`

If `worker_id` never appears, the worker never claimed the job.

If `worker_id` appears but the job never finishes, inspect the worker runtime path and AI service call.

## AI Service And Model Checks

Remember that some queue issues are actually model issues.

Typical examples:

- Ollama process is not running on the host
- model is loaded but extremely slow
- model returns malformed output
- AI service itself is healthy but one specific query is too expensive

When queue behavior looks normal but jobs are slow, test the AI service directly:

```powershell
Invoke-RestMethod -Method Post -Uri http://localhost:8001/query -ContentType 'application/json' -Body '{"question":"How many active users?","conversation_id":"ops-test-1","include_rows":true}' | ConvertTo-Json -Depth 6
```

If this direct call is slow, the worker is probably not the root cause.

## Cleanup And Maintenance

### Recommended first cleanup rule

Run periodic cleanup for old finished jobs.

Suggested starting rule:

- keep `completed`, `failed`, and `timeout` jobs for `7` to `30` days
- delete matching old rows from `hy_ai_job_events`

### Recommended first stuck-job rule

Create a future sweeper job that marks very old `running` jobs as `timeout` when they exceed an operational threshold.

Suggested first threshold:

- `running` longer than `15` minutes

Do not do this blindly until you have measured actual long-running workloads.

## Recommended Monitoring Additions

These are not mandatory for phase 1, but should be added as usage grows.

Recommended metrics:

- queue length by queue name
- number of queued jobs
- number of running jobs
- average completion time by mode
- failure count by model
- timeout count by model
- oldest queued job age
- oldest running job age

Recommended future admin views:

- AI job history page
- AI job detail page
- stuck job dashboard
- worker status page

## Expansion Guidance

As the system expands to multi-model and multi-mode execution, operations should also expand by queue and worker type.

Recommended future queue split examples:

- `queue:ai:nl2sql`
- `queue:ai:rag`
- `queue:ai:summary`
- `queue:ai:agent`
- `queue:ai:high_priority`

Recommended future worker split examples:

- worker for fast SQL jobs
- worker for medium RAG jobs
- isolated worker for long agent jobs

This avoids one slow mode starving all other requests.

## Emergency Recovery Guidance

If the queue pipeline is broken and users need immediate service restoration, the safest short-term fallback is:

1. keep the database and app running
2. stop using async submission from the UI only if necessary
3. temporarily switch the page back to synchronous fallback logic
4. restore the queue path after root cause is understood

Do not manually rewrite random job statuses unless you first confirm what the worker and AI service are doing.

## Summary

For operations, always troubleshoot in this order:

1. container health
2. Redis availability
3. worker state
4. AI service health
5. `hy_ai_jobs` current row
6. `hy_ai_job_events` event trail
7. frontend polling behavior

This order keeps queue issues, model issues, and UI issues separated so the root cause can be found quickly.