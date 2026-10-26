[CmdletBinding()]
param([switch]$Bootstrap, [string]$NodeExecutable = 'node', [string]$RuntimeConfig)
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
if ($RuntimeConfig) {
    $settings = Get-Content -LiteralPath $RuntimeConfig -Raw | ConvertFrom-Json
    foreach ($property in $settings.PSObject.Properties) {
        [Environment]::SetEnvironmentVariable($property.Name, [string]$property.Value, 'Process')
    }
}
if (Test-Path -LiteralPath $NodeExecutable) { $NodeExecutable = (Resolve-Path -LiteralPath $NodeExecutable).Path }
if (-not $env:DB_DATABASE -or -not $env:DB_USER -or -not $env:DB_PASSWORD -or -not $env:KEY -or -not $env:SECRET) {
    throw 'Set DB_DATABASE, DB_USER, DB_PASSWORD, KEY and SECRET for the separate CMS database. Never use the Laravel database role.'
}
if (-not $env:DB_CLIENT) { $env:DB_CLIENT = 'pg' }
if (-not $env:DB_HOST) { $env:DB_HOST = '127.0.0.1' }
if (-not $env:DB_PORT) { $env:DB_PORT = '55432' }
$env:HOST = '127.0.0.1'
$env:PORT = '8055'
$env:PUBLIC_URL = 'http://127.0.0.1:8055'
$env:TELEMETRY = 'false'
$env:WEBSOCKETS_ENABLED = 'false'
Push-Location (Join-Path $projectRoot 'docker/directus')
try {
    if ($Bootstrap) {
        & $NodeExecutable --no-node-snapshot node_modules/directus/cli.js bootstrap
        if ($LASTEXITCODE -ne 0) { throw 'Directus database bootstrap failed.' }
    }
    & $NodeExecutable --no-node-snapshot node_modules/directus/cli.js start
    if ($LASTEXITCODE -ne 0) { throw 'Directus exited with an error.' }
} finally {
    Pop-Location
}
