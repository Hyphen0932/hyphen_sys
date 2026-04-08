param(
    [switch]$SkipMigration,
    [switch]$NoBuild,
    [switch]$ShowLogs
)

$ErrorActionPreference = 'Stop'

$workspaceRoot = Split-Path -Parent $PSScriptRoot
$projectName = 'hyphen_sys_prod'
$envFile = '.env.prod'
$composeFiles = @('-f', 'docker-compose.yml', '-f', 'docker-compose.prod.yml')

Push-Location $workspaceRoot
try {
    if (-not $SkipMigration) {
        Write-Host 'Applying production migrations...'
        & powershell -ExecutionPolicy Bypass -File .\scripts\apply-migrations.ps1 -Environment prod
        if ($LASTEXITCODE -ne 0) {
            throw 'Failed to apply production migrations.'
        }
    }

    $upArgs = @('compose', '-p', $projectName, '--env-file', $envFile) + $composeFiles + @('up', '-d')
    if (-not $NoBuild) {
        $upArgs += '--build'
    } else {
        $upArgs += '--no-build'
    }

    Write-Host 'Starting production environment...'
    & docker @upArgs
    if ($LASTEXITCODE -ne 0) {
        throw 'Failed to start production environment.'
    }

    Write-Host 'Production deployment completed.'
    Write-Host 'App: http://localhost:8088/hyphen_sys/'
    Write-Host 'phpMyAdmin is disabled by default in production.'

    if ($ShowLogs) {
        & docker compose -p $projectName --env-file $envFile @composeFiles logs --tail 200
    }
}
finally {
    Pop-Location
}
