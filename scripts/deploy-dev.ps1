param(
    [switch]$SkipMigration,
    [switch]$NoBuild,
    [switch]$ShowLogs
)

$ErrorActionPreference = 'Stop'

$workspaceRoot = Split-Path -Parent $PSScriptRoot
$projectName = 'hyphen_sys_dev'
$envFile = '.env.dev'
$composeFiles = @('-f', 'docker-compose.yml', '-f', 'docker-compose.dev.yml', '-f', 'docker-compose.ai.yml')

Push-Location $workspaceRoot
try {
    $upArgs = @('compose', '-p', $projectName, '--env-file', $envFile) + $composeFiles + @('up', '-d')
    if (-not $NoBuild) {
        $upArgs += '--build'
    } else {
        $upArgs += '--no-build'
    }

    Write-Host 'Starting development environment...'
    & docker @upArgs
    if ($LASTEXITCODE -ne 0) {
        throw 'Failed to start development environment.'
    }

    if (-not $SkipMigration) {
        Write-Host 'Applying development migrations...'
        & powershell -ExecutionPolicy Bypass -File .\scripts\apply-migrations.ps1 -Environment dev
        if ($LASTEXITCODE -ne 0) {
            throw 'Failed to apply development migrations.'
        }
    }

    Write-Host 'Development deployment completed.'
    Write-Host 'App: http://localhost:8080/hyphen_sys/'
    Write-Host 'phpMyAdmin: http://localhost:8081/'
    Write-Host 'AI Service: http://localhost:8001/'

    if ($ShowLogs) {
        & docker compose -p $projectName --env-file $envFile @composeFiles logs --tail 200
    }
}
finally {
    Pop-Location
}
