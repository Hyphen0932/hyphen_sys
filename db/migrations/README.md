# Database Migrations

This directory stores forward-only SQL migrations for schema changes that must be applied to an existing database volume.

## Naming

Use sortable file names:

```text
YYYYMMDD_HHMMSS_short_description.sql
```

Example:

```text
20260408_143000_add_user_last_login.sql
```

## Rules

- Put only schema changes here: `CREATE TABLE`, `ALTER TABLE`, `CREATE INDEX`, data backfills required by a schema change.
- Prefer forward-only migrations. Do not rely on editing old migration files after they have been applied.
- Keep each migration focused on one release unit.
- Test every migration in the development environment before applying it to production.

## Create a New Migration

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\new-migration.ps1 -Name "add user last login"
```

## Preview Pending Migrations

Development:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\apply-migrations.ps1 -Environment dev -DryRun
```

Production:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\apply-migrations.ps1 -Environment prod -DryRun
```

## Apply Migrations

Development:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\apply-migrations.ps1 -Environment dev
```

Production:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\apply-migrations.ps1 -Environment prod
```

Applied migrations are recorded in the `hy_schema_migrations` table.