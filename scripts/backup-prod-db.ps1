param(
    [string]$Label = 'manual'
)

$ErrorActionPreference = 'Stop'

$workspaceRoot = Split-Path -Parent $PSScriptRoot
$projectName = 'hyphen_sys_prod'
$envFileName = '.env.prod'
$backupDirectory = Join-Path $workspaceRoot 'backups\prod'

function Read-EnvFile {
    param(
        [string]$Path
    )

    $values = @{}
    foreach ($rawLine in Get-Content -Path $Path) {
        $line = $rawLine.Trim()
        if ($line -eq '' -or $line.StartsWith('#')) {
            continue
        }

        $separatorIndex = $line.IndexOf('=')
        if ($separatorIndex -lt 1) {
            continue
        }

        $key = $line.Substring(0, $separatorIndex).Trim()
        $value = $line.Substring($separatorIndex + 1).Trim()
        $values[$key] = $value
    }

    return $values
}

function Wait-ForDatabase {
    param(
        [int]$TimeoutSeconds = 90
    )

    $containerId = (& docker compose -p $projectName --env-file $envFileName -f docker-compose.yml -f docker-compose.prod.yml ps -q db).Trim()
    if ([string]::IsNullOrWhiteSpace($containerId)) {
        throw 'Production database container was not created.'
    }

    $deadline = (Get-Date).AddSeconds($TimeoutSeconds)
    while ((Get-Date) -lt $deadline) {
        $status = (& docker inspect -f "{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}" $containerId 2>$null).Trim()
        if ($status -eq 'healthy' -or $status -eq 'running') {
            return
        }

        Start-Sleep -Seconds 2
    }

    throw "Production database container did not become ready within $TimeoutSeconds seconds."
}

Push-Location $workspaceRoot
try {
    $envFilePath = Join-Path $workspaceRoot $envFileName
    if (!(Test-Path -Path $envFilePath)) {
        throw "Environment file not found: $envFilePath"
    }

    $envValues = Read-EnvFile -Path $envFilePath
    $databaseName = $envValues['MYSQL_DATABASE']
    $rootPassword = $envValues['MYSQL_ROOT_PASSWORD']

    if ([string]::IsNullOrWhiteSpace($databaseName) -or [string]::IsNullOrWhiteSpace($rootPassword)) {
        throw "MYSQL_DATABASE or MYSQL_ROOT_PASSWORD is missing in $envFileName"
    }

    $safeLabel = ($Label.ToLower() -replace '[^a-z0-9_-]+', '_').Trim('_')
    if ([string]::IsNullOrWhiteSpace($safeLabel)) {
        $safeLabel = 'manual'
    }

    if (!(Test-Path -Path $backupDirectory)) {
        New-Item -ItemType Directory -Path $backupDirectory -Force | Out-Null
    }

    Write-Host 'Ensuring production database container is running...'
    & docker compose -p $projectName --env-file $envFileName -f docker-compose.yml -f docker-compose.prod.yml up -d db | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw 'Unable to start the production database container.'
    }

    Wait-ForDatabase

    $timestamp = Get-Date -Format 'yyyyMMdd_HHmmss'
    $backupFileName = "${timestamp}_${databaseName}_${safeLabel}.sql"
    $backupFilePath = Join-Path $backupDirectory $backupFileName

    Write-Host "Creating production backup: $backupFilePath"

    $dumpArgs = @(
        'compose', '-p', $projectName,
        '--env-file', $envFileName,
        '-f', 'docker-compose.yml',
        '-f', 'docker-compose.prod.yml',
        'exec', '-T', 'db',
        'mariadb-dump',
        '--single-transaction',
        '--quick',
        '--routines',
        '--triggers',
        '-uroot', "-p$rootPassword", $databaseName
    )

    & docker @dumpArgs | Out-File -FilePath $backupFilePath -Encoding utf8
    if ($LASTEXITCODE -ne 0) {
        if (Test-Path -Path $backupFilePath) {
            Remove-Item -Path $backupFilePath -Force
        }
        throw 'Failed to create production database backup.'
    }

    Write-Host 'Production database backup completed.'
    Write-Host "Backup file: $backupFilePath"
}
finally {
    Pop-Location
}