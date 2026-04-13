# AI Service Model Deployment Guide

## Purpose

This guide defines the deployment, verification, and extension pattern for the local AI data-query service used by Hyphen System.

Current production-ready scope in this repository:

- local inference through `Ollama`
- current default model: `qwen2.5-coder:7b`
- workflow orchestration through `LangGraph`
- prompt and model integration through `LangChain`
- schema-aware database question answering through the Docker MariaDB stack
- PHP page integration through `pages/sys_admin/system_advance_search.php`

Use this guide whenever you want to:

- redeploy the current AI stack
- switch to another Ollama model
- add a stronger local model later
- expand table coverage
- replace Ollama with another LLM provider in the future

## Active Environment Rule

For this project, AI deployment and verification must be validated against the Docker development stack.

Use this rule of thumb:

- system platform: `http://127.0.0.1:8080/hyphen_sys`
- phpMyAdmin: `http://127.0.0.1:8081`
- AI service public port: `http://127.0.0.1:8001`
- database verification: Docker `db` container
- AI runtime location: Windows host `Ollama`

Do not treat XAMPP local MySQL as the source of truth for AI query verification.

## Current Architecture

The deployed AI flow has 4 layers:

1. `pages/sys_admin/system_advance_search.php`
   This is the admin UI where the user enters natural-language questions and sees the generated answer, SQL, and optional raw rows.

2. `pages/sys_admin/action/sys_advance_search_api.php`
   This is the same-origin PHP proxy. It handles auth, permission checks, payload validation, and forwards the request to the Python AI service.

3. `ai_service/`
   This is the Python AI service running in Docker. It provides `/health` and `/query` endpoints.

4. `Ollama` on the Windows host
   This serves the local model to the Docker AI service through `host.docker.internal:11434`.

Current key files:

- `docker-compose.ai.yml`
- `ai_service/Dockerfile`
- `ai_service/requirements.txt`
- `ai_service/app/config.py`
- `ai_service/app/workflow.py`
- `ai_service/app/prompts.py`
- `ai_service/app/schema_rag.py`
- `ai_service/app/sql_guard.py`
- `ai_service/app/memory_store.py`
- `pages/sys_admin/system_advance_search.php`
- `pages/sys_admin/action/sys_advance_search_api.php`

## Current Workflow Design

The AI service is intentionally not a broad autonomous agent.

It is a controlled workflow for database Q and A:

1. load conversation memory
2. build schema context from allowed database tables
3. generate SQL from the user question
4. validate the SQL as read-only and table-safe
5. execute the SQL on MariaDB
6. summarize the result in Chinese

This design is preferred over a free-form agent because it is easier to verify, safer to restrict, and easier to debug.

## Current Safety Boundaries

The current implementation already applies the following controls:

- only `SELECT` and `WITH ... SELECT` are allowed
- multi-statement SQL is rejected
- unauthorized tables are rejected
- dangerous keywords such as `INSERT`, `UPDATE`, `DELETE`, `DROP`, `ALTER`, and `TRUNCATE` are rejected
- non-aggregate queries receive a default `LIMIT`
- the PHP proxy requires user authentication and `view` permission for `sys_admin/system_advance_search`

Current allowed tables by default:

- `hy_users`
- `hy_user_menu`
- `hy_user_pages`
- `hy_user_permissions`

## Initial Deployment Steps

### 1. Install and run Ollama on the Windows host

Confirm that `ollama.exe` exists and is callable from PowerShell.

Pull the current recommended model:

```powershell
ollama pull qwen2.5-coder:7b
```

Start the Ollama runtime if it is not already running:

```powershell
ollama serve
```

### 2. Confirm AI-related environment values

Relevant environment variables are defined in:

- `.env.dev`
- `.env.prod`
- `.env.dev.example`
- `.env.prod.example`

Important keys:

- `AI_SERVICE_BASE_URL=http://ai-service:8000`
- `OLLAMA_BASE_URL=http://host.docker.internal:11434`
- `OLLAMA_MODEL=qwen2.5-coder:7b`
- `AI_ALLOWED_TABLES=hy_users,hy_user_menu,hy_user_pages,hy_user_permissions`
- `AI_RESULT_ROW_LIMIT=50`
- `AI_MEMORY_WINDOW=6`

### 3. Start the Docker development stack

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy-dev.ps1
```

This starts:

- `app`
- `db`
- `phpmyadmin`
- `ai-service`

### 4. Confirm the AI service is healthy

```powershell
Invoke-RestMethod -Method Get -Uri http://localhost:8001/health | ConvertTo-Json -Depth 4
```

Expected status:

- `service = ok`
- `database = ok`
- `ollama = ok`

### 5. Verify end-to-end AI query flow

Direct AI service test:

```powershell
Invoke-RestMethod -Method Post -Uri http://localhost:8001/query -ContentType 'application/json' -Body '{"question":"有多少 active 用户？","conversation_id":"demo-1","include_rows":true}' | ConvertTo-Json -Depth 6
```

System page test:

- open `http://127.0.0.1:8080/hyphen_sys/pages/sys_admin/system_advance_search`
- ask `有多少 active 用户？`
- confirm the page shows the answer, SQL, and returned row count

## How To Add Another Ollama Model

If the next model is still served by Ollama, this is the low-risk path.

### 1. Pull the new model on the host

Examples:

```powershell
ollama pull qwen2.5:14b
ollama pull deepseek-r1:14b
ollama pull llama3:8b
```

### 2. Update the model name

Change `OLLAMA_MODEL` in `.env.dev` or `.env.prod`.

Example:

```text
OLLAMA_MODEL=qwen2.5:14b
```

### 3. Rebuild or restart the AI service

```powershell
docker compose -p hyphen_sys_dev --env-file .env.dev -f docker-compose.yml -f docker-compose.dev.yml -f docker-compose.ai.yml up -d --build ai-service
```

### 4. Re-run health and query verification

Use the same `/health` and `/query` checks defined above.

### 5. Review SQL quality before broader rollout

At minimum, test these prompt types:

- count question
- filtered list question
- page lookup question
- permission lookup question

If the new model becomes too conservative or too hallucination-prone, review:

- `ai_service/app/prompts.py`
- `ai_service/app/workflow.py`
- `ai_service/app/sql_guard.py`

## How To Add More Tables

Only expand table coverage after verifying the schema and business meaning.

### 1. Update allowed tables

Edit `AI_ALLOWED_TABLES` in `.env.dev` or `.env.prod`.

Example:

```text
AI_ALLOWED_TABLES=hy_users,hy_user_menu,hy_user_pages,hy_user_permissions,hy_audit_logs
```

### 2. Rebuild or restart the AI service

```powershell
docker compose -p hyphen_sys_dev --env-file .env.dev -f docker-compose.yml -f docker-compose.dev.yml -f docker-compose.ai.yml up -d --build ai-service
```

### 3. Add prompt examples for the new table

Update `ai_service/app/prompts.py` with examples that reflect the real business questions users will ask.

### 4. Validate the table schema at runtime

The schema retrieval logic is in `ai_service/app/schema_rag.py` and reads metadata from MariaDB.

Do not add a table to `AI_ALLOWED_TABLES` unless:

- the table exists in Docker MariaDB
- the fields are understood by the team
- the output is appropriate for the target users

## How To Replace Ollama With Another Provider Later

This is a code change, not just an environment change.

Current model calls are implemented through `ChatOllama` inside `ai_service/app/workflow.py`.

If you later want to use another backend, such as OpenAI-compatible APIs, Azure, or another local runtime, the main change points are:

- `ai_service/app/config.py`
- `ai_service/app/workflow.py`
- `ai_service/requirements.txt`
- `docker-compose.ai.yml`

Recommended rule:

- keep the external API contract unchanged
- preserve `/health`
- preserve `/query`
- preserve the PHP proxy contract in `pages/sys_admin/action/sys_advance_search_api.php`

This way the system page does not need to change just because the model backend changes.

## Prompt, Memory, and RAG Extension Points

### Prompt layer

File:

- `ai_service/app/prompts.py`

Use this file when you want to:

- add stronger SQL examples
- improve Chinese answer tone
- bias the planner toward certain tables
- add new retry instructions for difficult models

### Memory layer

File:

- `ai_service/app/memory_store.py`

Current state:

- in-process short memory only
- suitable for local dev and basic follow-up questions

Recommended future upgrade:

- move memory to Redis or MariaDB if multi-user or persistent memory becomes necessary

### Schema RAG layer

File:

- `ai_service/app/schema_rag.py`

Current state:

- reads table and column metadata from MariaDB
- ranks candidate tables from the user question

Recommended future upgrades:

- attach business descriptions to tables and columns
- add curated aliases for internal terms
- persist a schema glossary for less literal business vocabulary

## PHP Integration Pattern

The preferred browser integration pattern is:

1. page UI in PHP
2. same-origin PHP action endpoint
3. server-to-server request into `ai-service`

Do not make the browser call the Docker AI service directly unless there is a deliberate reason to expose it for external consumers.

Benefits of the current proxy design:

- simpler auth handling
- no browser CORS dependency
- easier permission enforcement
- easier future provider swap without touching front-end code

## Recommended Read-Only Database Account

The current default AI DB connection can mirror the application DB credentials, but that should be treated as transitional.

Recommended future hardening:

1. create a dedicated MariaDB read-only user
2. grant only `SELECT`
3. restrict it to the approved tables if possible
4. use that account in:
   `AI_DB_USER`
   `AI_DB_PASSWORD`

## Verification Checklist After Any Model Change

Use the Docker-served system on `8080` and the AI service on `8001`.

### 1. Runtime health test

- `GET /health` returns `service ok`, `database ok`, and `ollama ok`

### 2. Simple aggregate test

- ask `有多少 active 用户？`
- confirm a count SQL is generated
- confirm the answer matches MariaDB

### 3. Filtered list test

- ask for `System Admin` users with `staff_id` and `email`
- confirm the result uses `hy_users`

### 4. Page lookup test

- ask which pages belong to `menu_id 99`
- confirm the result uses `hy_user_pages`

### 5. UI integration test

- open `pages/sys_admin/system_advance_search.php`
- run a question through the page
- confirm answer, SQL, and row rendering behave correctly

### 6. Failure-path test

- ask for an unsupported table or unsupported business concept
- confirm the service fails safely instead of generating dangerous SQL

## Common Failure Patterns

- Ollama is installed but not running
- the new model was not pulled before changing `OLLAMA_MODEL`
- `OLLAMA_BASE_URL` points to the wrong host
- `AI_SERVICE_BASE_URL` is missing in the app container
- the AI service is healthy but the model is too weak for the current prompt style
- allowed tables were expanded without adding examples or business context
- direct DB verification was done against XAMPP instead of Docker `db`
- the browser page was tested without a valid authenticated session

## Troubleshooting Commands

Check the combined Docker config:

```powershell
docker compose -p hyphen_sys_dev --env-file .env.dev -f docker-compose.yml -f docker-compose.dev.yml -f docker-compose.ai.yml config
```

Check AI service logs:

```powershell
docker compose -p hyphen_sys_dev --env-file .env.dev -f docker-compose.yml -f docker-compose.dev.yml -f docker-compose.ai.yml logs -f ai-service
```

Check app logs:

```powershell
docker compose -p hyphen_sys_dev --env-file .env.dev -f docker-compose.yml -f docker-compose.dev.yml -f docker-compose.ai.yml logs -f app
```

Restart only the AI service:

```powershell
docker compose -p hyphen_sys_dev --env-file .env.dev -f docker-compose.yml -f docker-compose.dev.yml -f docker-compose.ai.yml up -d --build ai-service
```

Check available local models:

```powershell
ollama list
```

## Recommended Release Rule

Do not treat a model change as successful just because `/health` returns `ok`.

A model change is only ready when all of the following are true:

1. the AI service is healthy
2. at least 4 representative business questions return correct SQL
3. the page integration still works through `system_advance_search`
4. failure cases are rejected safely
5. Docker dev verification matches the Docker database state

If these 5 checks pass, the model deployment is ready for the next environment.