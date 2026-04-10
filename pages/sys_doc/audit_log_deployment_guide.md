# Audit Log Deployment Guide

## Purpose

This guide defines the deployment and verification flow for the centralized system audit log.

The audit design in this project is not based on manual `INSERT` statements inside every module. Instead, JSON endpoints are expected to enter through a shared bootstrap layer so request and response data can be recorded automatically.

Core files:

- `build/api_bootstrap.php`
- `build/audit_middleware.php`
- `pages/sys_admin/system_audit_log.php`
- `pages/sys_admin/action/system_audit_log_api.php`

## Active Environment Rule

For this project, audit deployment must be validated against the Docker development stack.

Use this rule of thumb:

- system platform: `http://127.0.0.1:8080/hyphen_sys`
- phpMyAdmin: `http://127.0.0.1:8081`
- database verification: Docker `db` container
- migration command: `powershell -ExecutionPolicy Bypass -File .\scripts\apply-migrations.ps1 -Environment dev`

Do not use XAMPP local MySQL as the source of truth for audit verification.

## Architecture

The centralized audit flow has 3 parts:

1. `build/api_bootstrap.php`
   This standardizes JSON response setup, request payload parsing, session bootstrapping, auth checks, and middleware startup.

2. `build/audit_middleware.php`
   This captures request context, watches response output, calculates execution time, and inserts the final audit row into `hy_audit_logs`.

3. `pages/sys_admin/action/system_audit_log_api.php`
   This reads audit records for the admin UI and must stay compatible with deployed schema variations.

## Current Endpoint Coverage

Current endpoints already wired into the centralized audit flow:

- `build/login.php`
- `build/logout.php`
- `api/email/email_notifications.php`
- `pages/sys_admin/action/sys_users_crud.php`
- `pages/sys_admin/action/sys_menu_crud.php`
- `pages/sys_admin/action/sys_users_email_noti.php`
- `pages/sys_admin/action/system_audit_log_api.php`
- `pages/template/sys_admin/action/sample_module_crud.php`

This means the following user actions should now be auditable when executed through the Docker app on `8080`:

- login
- logout
- user create or update
- menu create or update
- email notification API actions

## Database Schema

The fresh centralized schema expected by this implementation is the simplified `hy_audit_logs` table recreated by the migration set on 2026-04-10.

Important columns:

- `staff_id`
- `page_key`
- `api_endpoint`
- `action`
- `method`
- `request_data`
- `response_data`
- `status`
- `error_message`
- `ip_address`
- `user_agent`
- `response_code`
- `execution_time_ms`
- `created_at`

## Migrations

Relevant migration files:

- `db/migrations/20260410_120000_create_audit_logs_table.sql`
- `db/migrations/20260410_130000_upgrade_audit_logs_table.sql`
- `db/migrations/20260410_140000_add_execution_time_to_audit_logs.sql`
- `db/migrations/20260410_150000_upgrade_legacy_audit_logs_columns.sql`

Apply them with:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\apply-migrations.ps1 -Environment dev
```

## How To Recreate The Audit Table Cleanly

Use this only when the Docker development audit table is already polluted by older schema experiments and you explicitly want a clean rebuild.

### 1. Drop the Docker dev audit table

```powershell
docker compose -p hyphen_sys_dev --env-file .env.dev -f docker-compose.yml -f docker-compose.dev.yml exec -T db mariadb -uroot -pHyphenRoot_2026_3Nk8Vs2P hyphen_sys -e "DROP TABLE IF EXISTS hy_audit_logs;"
```

### 2. Remove audit migration history for that table

```powershell
docker compose -p hyphen_sys_dev --env-file .env.dev -f docker-compose.yml -f docker-compose.dev.yml exec -T db mariadb -uroot -pHyphenRoot_2026_3Nk8Vs2P hyphen_sys -e "DELETE FROM hy_schema_migrations WHERE version IN ('20260410_120000_create_audit_logs_table','20260410_130000_upgrade_audit_logs_table','20260410_140000_add_execution_time_to_audit_logs','20260410_150000_upgrade_legacy_audit_logs_columns');"
```

### 3. Re-run the dev migrations

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\apply-migrations.ps1 -Environment dev
```

### 4. Confirm the new table exists and starts empty

```powershell
docker compose -p hyphen_sys_dev --env-file .env.dev -f docker-compose.yml -f docker-compose.dev.yml exec -T db mariadb -uroot -pHyphenRoot_2026_3Nk8Vs2P hyphen_sys -e "SELECT COUNT(*) AS total FROM hy_audit_logs;"
```

Expected result right after recreation:

- `total = 0`

## Verification Checklist After Deployment

Use the Docker-served system on `8080`.

### 1. Login test

- open `http://127.0.0.1:8080/hyphen_sys`
- sign in with a valid user
- verify a `login` action appears in `hy_audit_logs`

### 2. Logout test

- click sign out from the platform UI
- verify a `logout` action appears in `hy_audit_logs`

### 3. User update test

- edit an existing user from `System Users`
- save the change
- verify an `update_user` action appears in `hy_audit_logs`

### 4. Audit page test

- open `pages/sys_admin/system_audit_log.php`
- confirm rows are returned without JSON parsing errors
- confirm filters work for date, status, action, and endpoint

## Direct Docker Verification Queries

Check recent audit rows:

```powershell
docker compose -p hyphen_sys_dev --env-file .env.dev -f docker-compose.yml -f docker-compose.dev.yml exec -T db mariadb -uroot -pHyphenRoot_2026_3Nk8Vs2P hyphen_sys -e "SELECT id, staff_id, api_endpoint, action, method, status, error_message, created_at FROM hy_audit_logs ORDER BY id DESC LIMIT 20;"
```

Check table structure:

```powershell
docker compose -p hyphen_sys_dev --env-file .env.dev -f docker-compose.yml -f docker-compose.dev.yml exec -T db mariadb -uroot -pHyphenRoot_2026_3Nk8Vs2P -N -B hyphen_sys -e "SHOW CREATE TABLE hy_audit_logs;"
```

## How To Onboard A New JSON Endpoint

Any new JSON endpoint should follow the shared bootstrap pattern.

Recommended skeleton:

```php
<?php
include_once '../../../build/api_bootstrap.php';

hyphen_api_bootstrap([
    'allowed_methods' => ['POST'],
    'audit' => [
        'page_key' => 'sys_admin/example_module',
    ],
]);

$action = trim((string) ($_POST['action'] ?? ''));

switch ($action) {
    case 'create_item':
        hyphen_require_ability('add', 'sys_admin/example_module_new', null, true);
        create_item($conn);
        break;

    default:
        json_response(false, 'Unsupported action.', [], 400);
}
```

Do not manually write audit rows inside every action handler unless there is a very specific exception case.

## Notes About Logout

`build/logout.php` is not a JSON endpoint, but it has been explicitly wired to `AuditMiddleware` before session destruction so logout can still be recorded.

That implementation depends on this order:

1. start middleware while the authenticated session still exists
2. issue redirect header
3. flush audit log
4. clear session and destroy cookie

If you change logout flow later, preserve this order or logout entries may disappear.

## Common Failure Patterns

- verifying against XAMPP local DB instead of Docker DB
- checking phpMyAdmin on `8081` but using the app on a different stack
- adding a new action file without `build/api_bootstrap.php`
- assuming a browser action reached `8080` when it actually hit another environment
- changing the audit table manually and leaving schema drift behind
- swallowing a backend exception and assuming the action succeeded just because the UI refreshed

## Recommended Quick Test Before Release

1. Run the dev migration command.
2. Confirm `hy_audit_logs` exists inside Docker `db`.
3. Login on `8080` and confirm a `login` row is written.
4. Update a user and confirm an `update_user` row is written.
5. Logout and confirm a `logout` row is written.
6. Open the audit page and confirm the latest rows are visible.

If these 6 checks pass in Docker dev, the audit deployment is ready for the next environment.