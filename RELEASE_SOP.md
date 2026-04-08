# Release SOP

## Scope

Use this SOP when a change is ready to move from development into production.

## 1. Finish Development

- Complete the code change in the development environment.
- If the release changes the schema, create a migration in [db/migrations/README.md](db/migrations/README.md).
- Run the development stack and verify the feature manually.

## 2. Validate in Development

- Rebuild the development stack if the PHP code changed.
- Run pending development migrations.
- Verify the affected screens, login flow, permissions, and any upload or database write paths touched by the change.

Suggested commands:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy-dev.ps1
```

## 3. Prepare the Release

- Commit the code.
- Merge the approved code into the branch used for production deployment.
- Confirm whether the release is:
  - Code only
  - Code plus schema migration

## 4. Backup Production

- Back up the production database before any schema migration.
- Record the release time and commit hash.

Suggested command:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\backup-prod-db.ps1 -Label "before_release"
```

## 5. Deploy to Production

If the release includes migrations:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\apply-migrations.ps1 -Environment prod
```

Then deploy the application:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy-prod.ps1 -SkipMigration
```

If you prefer one command that runs both migration and deployment, use:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy-prod.ps1
```

## 6. Verify Production

- Open the production site.
- Check container status.
- Review logs for `app` and `db`.
- Test the specific feature that was released.
- If the release touched permissions or login, test at least one affected account.

Suggested commands:

```powershell
docker compose -p hyphen_sys_prod --env-file .env.prod -f docker-compose.yml -f docker-compose.prod.yml ps
docker compose -p hyphen_sys_prod --env-file .env.prod -f docker-compose.yml -f docker-compose.prod.yml logs --tail 200 app db
```

## 7. Temporary Production Admin Access

If you need database inspection during release verification, start phpMyAdmin only for the maintenance window:

```powershell
docker compose -p hyphen_sys_prod --env-file .env.prod -f docker-compose.yml -f docker-compose.prod.yml -f docker-compose.prod.admin.yml up -d phpmyadmin
```

When finished:

```powershell
docker compose -p hyphen_sys_prod --env-file .env.prod -f docker-compose.yml -f docker-compose.prod.yml -f docker-compose.prod.admin.yml stop phpmyadmin
docker compose -p hyphen_sys_prod --env-file .env.prod -f docker-compose.yml -f docker-compose.prod.yml -f docker-compose.prod.admin.yml rm -f phpmyadmin
```

## 8. Rollback Notes

- If the release is code only, redeploy the previous known-good commit.
- If the release includes a schema change, rollback must be planned separately. Do not assume every migration is reversible.
- If data integrity is at risk, restore from backup instead of improvising manual SQL changes.