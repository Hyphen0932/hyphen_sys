# AI Async Queue Architecture Guide

## Purpose

This guide defines the asynchronous AI execution architecture used for `system_advance_search`.

It is the recommended deployment pattern when the system must support:

- long-running AI requests without freezing the page
- queued server-side processing
- multiple workers
- future multi-model and multi-mode routing
- higher user volume than a simple synchronous request path can tolerate

This guide should be used together with:

- `pages/sys_doc/ai_service_model_deployment_guide.md`
- `docker-compose.ai.yml`
- `pages/sys_admin/system_advance_search.php`
- `pages/sys_admin/action/sys_advance_search_api.php`

## Why The Old Synchronous Flow Was Not Enough

The original flow was:

1. browser submits question
2. PHP waits for AI service
3. AI service waits for model inference
4. browser receives final result only after all processing is finished

This has 3 practical problems:

- the page appears blocked while the request is still running
- long model latency can trigger HTTP timeouts
- future multi-model and multi-step workflows would pile up on the same request-response path

For this reason, the system now uses a queued asynchronous architecture.

## Current Async Architecture

The current production-oriented flow is:

1. user submits a question from `pages/sys_admin/system_advance_search.php`
2. PHP creates a job row in `hy_ai_jobs`
3. PHP pushes the `job_id` into a Redis queue
4. PHP returns immediately to the browser with `job_id`
5. browser polls the job status endpoint
6. Python worker consumes the queued job
7. worker calls the existing AI service `/query`
8. worker writes result or error back to MySQL
9. browser polls again and renders the final answer

This design removes long AI work from the browser request lifecycle.

## Active Components

### 1. Frontend polling page

File:

- `pages/sys_admin/system_advance_search.php`

Responsibilities:

- collect user question
- submit the async job request
- receive `job_id`
- poll for job status every few seconds
- render answer, SQL, and optional rows after completion

### 2. PHP async job API

File:

- `pages/sys_admin/action/sys_advance_search_api.php`

Current actions:

- `create_job`
- `get_job`
- `query_sync` as a fallback path

Responsibilities:

- auth and permission enforcement
- runtime table and row-limit enforcement
- create MySQL job record
- enqueue Redis message
- expose job status to frontend polling

### 3. MySQL persistence

Files:

- `db/migrations/20260414_150000_create_ai_job_tables.sql`
- `build/ai_jobs.php`

Tables:

- `hy_ai_jobs`
- `hy_ai_job_events`

Responsibilities:

- persistent job state tracking
- result persistence
- error persistence
- job history and audit trail

### 4. Redis queue

File:

- `docker-compose.ai.yml`

Responsibilities:

- queue incoming AI jobs
- decouple request ingestion from AI execution
- allow future worker scaling and queue partitioning

### 5. Python worker

File:

- `ai_service/app/worker.py`

Responsibilities:

- block on Redis queue
- claim queued jobs in MySQL
- call the AI service
- write final result or failure state back to MySQL
- write job events to `hy_ai_job_events`

### 6. Existing AI service

Files:

- `ai_service/app/main.py`
- `ai_service/app/workflow.py`

Responsibilities:

- keep the actual NL2SQL and answer-generation logic
- remain focused on execution, not queue orchestration

## Current Deployment Layout

The async AI stack in Docker now includes:

- `app`
- `db`
- `phpmyadmin` in development
- `ai-service`
- `redis`
- `ai-worker`

The recommended development access pattern remains:

- platform: `http://127.0.0.1:8080/hyphen_sys`
- phpMyAdmin: `http://127.0.0.1:8081`
- AI service: `http://127.0.0.1:8001`
- Redis: internal container service only

## Environment Variables

Relevant variables now include the queue and worker settings below.

Existing AI runtime variables:

- `AI_SERVICE_BASE_URL=http://ai-service:8000`
- `OLLAMA_BASE_URL=http://host.docker.internal:11434`
- `OLLAMA_MODEL=qwen2.5-coder:7b`
- `AI_ALLOWED_TABLES=hy_users,hy_user_menu,hy_user_pages,hy_user_permissions`
- `AI_RESULT_ROW_LIMIT=50`
- `AI_MEMORY_WINDOW=6`

New async variables:

- `AI_REDIS_HOST=redis`
- `AI_REDIS_PORT=6379`
- `AI_REDIS_DB=0`
- `AI_JOB_QUEUE_NAME=queue:ai:nl2sql`
- `AI_WORKER_QUEUES=queue:ai:nl2sql`
- `AI_WORKER_BLOCK_TIMEOUT=5`
- `AI_WORKER_HTTP_TIMEOUT=120`

## Current Database Design

### `hy_ai_jobs`

This is the main job state table.

Key fields:

- `job_id`: external ID used by frontend polling
- `staff_id`: job owner
- `conversation_id`: logical conversation grouping
- `job_type`: current type, default `nl2sql`
- `mode_key`: current mode, default `nl2sql`
- `model_key`: model used for execution
- `question_text`: original user question
- `request_payload_json`: full worker input payload
- `result_payload_json`: persisted response payload
- `status`: `queued`, `running`, `completed`, `failed`, `timeout`, `cancelled`
- `queue_name`: Redis queue used for execution
- `attempt_count`: worker processing attempts
- `error_message`: final failure detail when needed
- `queued_at`, `started_at`, `finished_at`: lifecycle timestamps

### `hy_ai_job_events`

This is the event trail table.

Use cases:

- worker traceability
- queue debugging
- execution audit
- future monitoring dashboards

## Current Frontend Behavior

The page no longer waits on a long synchronous request.

New behavior:

1. submit question
2. receive `job_id`
3. status changes to `Queued`
4. page polls `get_job`
5. status becomes `Running`
6. final result appears when status becomes `Completed`

Failure states now have explicit UI outcomes:

- `failed`
- `timeout`
- `cancelled`

This is the minimum stable user experience for long-running AI execution.

## Current Worker Behavior

The current worker is intentionally simple and suitable for phase 1.

It does the following:

1. listen to Redis using blocking pop
2. claim only jobs still in `queued`
3. mark job as `running`
4. call the AI service `/query`
5. store final result
6. mark job as `completed`, `failed`, or `timeout`

This keeps orchestration small while still making the browser workflow non-blocking.

## Verification Steps

### 1. Start the development stack

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy-dev.ps1
docker compose -p hyphen_sys_dev --env-file .env.dev -f docker-compose.yml -f docker-compose.dev.yml -f docker-compose.ai.yml up -d --build app ai-service ai-worker redis
```

### 2. Confirm container status

```powershell
docker compose -p hyphen_sys_dev --env-file .env.dev -f docker-compose.yml -f docker-compose.dev.yml -f docker-compose.ai.yml ps
```

Expected services:

- `app`
- `db`
- `phpmyadmin`
- `ai-service`
- `redis`
- `ai-worker`

### 3. Confirm AI service health

```powershell
Invoke-RestMethod -Method Get -Uri http://localhost:8001/health | ConvertTo-Json -Depth 4
```

Expected:

- `service = ok`
- `database = ok`
- `ollama = ok`

### 4. Confirm async job tables exist

Check with phpMyAdmin or query Docker MariaDB:

- `hy_ai_jobs`
- `hy_ai_job_events`

### 5. Verify end-to-end async behavior

Open:

- `http://127.0.0.1:8080/hyphen_sys/pages/sys_admin/system_advance_search`

Test flow:

1. submit a question
2. confirm page state changes to `Queued`
3. confirm it moves to `Running`
4. confirm final answer appears without browser freeze

## Operational Notes

### Timeout strategy

Suggested starting values:

- worker HTTP timeout: `120` seconds
- frontend poll interval: `2` seconds
- frontend user-visible patience window: `3` to `5` minutes

### Result retention

Keep job rows for a practical audit window.

Suggested first policy:

- retain `hy_ai_jobs` for `7` to `30` days
- retain `hy_ai_job_events` for shorter or equal duration
- add a cleanup script later if table growth becomes noticeable

### Security and ownership

Each job is bound to `staff_id`.

The current polling endpoint only returns jobs for the current session user.

This should remain true for all future extensions.

## Recommended Expansion Path

The current implementation is phase 1 only. It is designed to be extended in layers.

### Phase 2: richer user operations

Recommended additions:

- cancel job API
- retry job API
- job history page
- job detail page with lifecycle events

Why:

- users need visibility into slow and failed requests
- admins need a history trail for support and audit

### Phase 3: multi-model support

Recommended additions:

- `hy_ai_models` table
- `model_key` selection rules
- model capability flags such as:
  - `supports_sql`
  - `supports_rag`
  - `supports_agent`
  - `max_timeout_seconds`
  - `is_enabled`

Recommended queue split examples:

- `queue:ai:qwen:nl2sql`
- `queue:ai:llama:rag`
- `queue:ai:deepseek:analysis`

Why:

- different models have different latency and cost profiles
- slow models should not block faster operational models

### Phase 4: multi-mode support

Recommended additions:

- `hy_ai_modes` table
- `hy_ai_routing_rules` table

Example modes:

- `nl2sql`
- `rag`
- `summary`
- `agent`
- `audit_analysis`

Why:

- not every request should go through the same workflow
- each mode may need different prompts, models, and timeouts

### Phase 5: worker specialization

Recommended pattern:

- separate workers by queue
- separate slow workflows from fast workflows
- assign different concurrency or scaling policies by mode

Example:

- fast worker for `nl2sql`
- medium worker for `rag`
- slow isolated worker for `agent`

Why:

- long-running agent tasks should not starve ordinary table queries

### Phase 6: monitoring and operations

Recommended additions:

- queue depth dashboard
- worker heartbeat table or metrics
- average runtime by mode and model
- failure-rate tracking
- stuck job sweeper

Why:

- once user volume grows, debugging by logs only becomes too slow

## Future Architecture Recommendation For 2000 Users

If the system grows toward roughly 2000 users and begins to support multiple models and multiple interaction modes, the preferred pattern is:

- Redis queue for orchestration
- MySQL for persistence and audit
- Python workers for execution
- frontend polling first
- SSE or WebSocket only after polling is proven to be insufficient

This is the correct sequencing because:

- polling is simpler to ship and debug in PHP + Apache
- Redis already provides the essential decoupling
- SSE can be added later without rewriting the job model

## What Not To Do

Avoid these patterns for the long term:

- pushing all AI execution back into synchronous PHP requests
- using one shared queue for every future model and mode forever
- storing only transient queue state without MySQL persistence
- exposing jobs across users without `staff_id` ownership checks

These shortcuts will become operational problems as soon as task latency and model variety increase.

## Summary Recommendation

The current approved direction is:

- `Redis queue + MySQL persistence + Python worker + frontend polling`

This is the recommended phase-1 architecture for Hyphen System because it:

- prevents page freeze during long AI execution
- keeps server-side execution observable and auditable
- supports future multi-model and multi-mode routing
- can later evolve into queue partitioning, worker specialization, and richer monitoring without replacing the whole design