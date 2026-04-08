param(
    [Parameter(Mandatory = $true)]
    [string]$Name
)

$ErrorActionPreference = 'Stop'

$workspaceRoot = Split-Path -Parent $PSScriptRoot
$migrationDirectory = Join-Path $workspaceRoot 'db\migrations'

if (!(Test-Path -Path $migrationDirectory)) {
    New-Item -ItemType Directory -Path $migrationDirectory | Out-Null
}

$timestamp = Get-Date -Format 'yyyyMMdd_HHmmss'
$safeName = $Name.ToLower() -replace '[^a-z0-9]+', '_'
$safeName = $safeName.Trim('_')

if ([string]::IsNullOrWhiteSpace($safeName)) {
    throw 'Migration name must contain at least one letter or number.'
}

$fileName = "$timestamp`_$safeName.sql"
$filePath = Join-Path $migrationDirectory $fileName

$content = @"
-- Migration: $fileName
-- Purpose: describe the schema change here

START TRANSACTION;

-- Example:
-- ALTER TABLE hy_users ADD COLUMN example_flag TINYINT(1) NOT NULL DEFAULT 0 AFTER status;

COMMIT;
"@

Set-Content -Path $filePath -Value $content -NoNewline
Write-Host "Created migration: $filePath"