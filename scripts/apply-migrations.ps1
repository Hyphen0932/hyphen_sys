param(
    [ValidateSet('dev', 'prod')]
    [string]$Environment = 'dev',

    [switch]$DryRun
)

$ErrorActionPreference = 'Stop'

$workspaceRoot = Split-Path -Parent $PSScriptRoot
$migrationDirectory = Join-Path $workspaceRoot 'db\migrations'
$envFileName = ".env.$Environment"
$envFilePath = Join-Path $workspaceRoot $envFileName
$baseComposePath = Join-Path $workspaceRoot 'docker-compose.yml'
$overlayComposePath = Join-Path $workspaceRoot "docker-compose.$Environment.yml"
$projectName = "hyphen_sys_$Environment"

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

function Escape-SqlValue {
    param(
        [string]$Value
    )

    return $Value.Replace("'", "''")
}

function Wait-ForDatabase {
    param(
        [int]$TimeoutSeconds = 90
    )

    $containerId = (& docker compose -p $projectName --env-file $envFileName -f docker-compose.yml -f "docker-compose.$Environment.yml" ps -q db).Trim()
    if ([string]::IsNullOrWhiteSpace($containerId)) {
        throw 'Database container was not created.'
    }

    $deadline = (Get-Date).AddSeconds($TimeoutSeconds)
    while ((Get-Date) -lt $deadline) {
        $status = (& docker inspect -f "{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}" $containerId 2>$null).Trim()
        if ($status -eq 'healthy' -or $status -eq 'running') {
            return
        }

        Start-Sleep -Seconds 2
    }

    throw "Database container did not become ready within $TimeoutSeconds seconds."
}

function Invoke-MariaDbSql {
    param(
        [string]$Sql,
        [switch]$RawOutput
    )

    $arguments = @(
        'compose', '-p', $projectName,
        '--env-file', $envFileName,
        '-f', 'docker-compose.yml',
        '-f', "docker-compose.$Environment.yml",
        'exec', '-T', 'db',
        'mariadb', '-uroot', "-p$rootPassword", $databaseName
    )

    if ($RawOutput) {
        $arguments += @('-N', '-B')
    }

    $result = $Sql | & docker @arguments 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "MariaDB command failed:`n$result"
    }

    return $result
}

if (!(Test-Path -Path $migrationDirectory)) {
    throw "Migration directory not found: $migrationDirectory"
}

if (!(Test-Path -Path $envFilePath)) {
    throw "Environment file not found: $envFilePath"
}

if (!(Test-Path -Path $baseComposePath)) {
    throw "Compose file not found: $baseComposePath"
}

if (!(Test-Path -Path $overlayComposePath)) {
    throw "Compose overlay not found: $overlayComposePath"
}

$envValues = Read-EnvFile -Path $envFilePath
$databaseName = $envValues['MYSQL_DATABASE']
$rootPassword = $envValues['MYSQL_ROOT_PASSWORD']

if ([string]::IsNullOrWhiteSpace($databaseName) -or [string]::IsNullOrWhiteSpace($rootPassword)) {
    throw "MYSQL_DATABASE or MYSQL_ROOT_PASSWORD is missing in $envFileName"
}

Write-Host "Ensuring database container is running for environment '$Environment'..."
& docker compose -p $projectName --env-file $envFileName -f docker-compose.yml -f "docker-compose.$Environment.yml" up -d db | Out-Null
if ($LASTEXITCODE -ne 0) {
    throw 'Unable to start the database container.'
}

Wait-ForDatabase

$createTableSql = @"
CREATE TABLE IF NOT EXISTS hy_schema_migrations (
    id INT NOT NULL AUTO_INCREMENT,
    version VARCHAR(255) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hy_schema_migrations_version (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
"@

Invoke-MariaDbSql -Sql $createTableSql | Out-Null

$appliedVersionsOutput = Invoke-MariaDbSql -Sql 'SELECT version FROM hy_schema_migrations ORDER BY version;' -RawOutput
$appliedVersions = @{}
foreach ($line in $appliedVersionsOutput) {
    $version = "$line".Trim()
    if ($version -ne '') {
        $appliedVersions[$version] = $true
    }
}

$migrationFiles = Get-ChildItem -Path $migrationDirectory -Filter '*.sql' | Sort-Object Name
if ($migrationFiles.Count -eq 0) {
    Write-Host 'No migration files found.'
    exit 0
}

$pendingMigrations = @()
foreach ($migrationFile in $migrationFiles) {
    if (-not $appliedVersions.ContainsKey($migrationFile.BaseName)) {
        $pendingMigrations += $migrationFile
    }
}

if ($pendingMigrations.Count -eq 0) {
    Write-Host 'No pending migrations.'
    exit 0
}

Write-Host "Pending migrations for '$Environment':"
foreach ($migrationFile in $pendingMigrations) {
    Write-Host " - $($migrationFile.Name)"
}

if ($DryRun) {
    Write-Host 'Dry run only. No migrations were applied.'
    exit 0
}

foreach ($migrationFile in $pendingMigrations) {
    Write-Host "Applying migration $($migrationFile.Name)..."
    $migrationSql = Get-Content -Path $migrationFile.FullName -Raw
    Invoke-MariaDbSql -Sql $migrationSql | Out-Null

    $version = Escape-SqlValue -Value $migrationFile.BaseName
    $fileName = Escape-SqlValue -Value $migrationFile.Name
    $insertSql = "INSERT INTO hy_schema_migrations (version, filename) VALUES ('$version', '$fileName');"
    Invoke-MariaDbSql -Sql $insertSql | Out-Null
}

Write-Host 'Migration execution completed successfully.'