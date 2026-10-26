[CmdletBinding()]
param(
    [string]$Filter,
    [switch]$SkipMigrations
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$phpExecutable = Join-Path $projectRoot '.runtime/php/php.exe'
if (-not (Test-Path -LiteralPath $phpExecutable) -or -not (Test-Path -LiteralPath (Join-Path $projectRoot 'vendor/autoload.php'))) {
    throw 'Portable PHP and Composer dependencies are required. Follow README.md setup first.'
}
if (-not $env:APP_KEY -and -not (Test-Path -LiteralPath (Join-Path $projectRoot '.env'))) {
    throw 'Generate an APP_KEY in .env (or provide APP_KEY in the environment) before testing.'
}

# The test database is deliberately fixed. Never migrate or test against the development database.
$testEnvironment = @{
    APP_ENV = 'testing'; DB_CONNECTION = 'pgsql'; DB_HOST = '127.0.0.1'; DB_PORT = '55432'
    DB_DATABASE = 'smilesuite_testing'; DB_USERNAME = 'postgres'; DB_PASSWORD = 'smilesuite-local'; DB_URL = ''
    CACHE_STORE = 'array'; SESSION_DRIVER = 'array'; QUEUE_CONNECTION = 'sync'; MAIL_MAILER = 'array'
    SMILESUITE_SANDBOX = 'true'; LIVE_BILLING_ENABLED = 'false'; LIVE_COMMERCE_ENABLED = 'false'; REAL_PATIENT_DATA_ENABLED = 'false'
}
$previousEnvironment = @{}
foreach ($key in $testEnvironment.Keys) {
    $previousEnvironment[$key] = [Environment]::GetEnvironmentVariable($key, 'Process')
    [Environment]::SetEnvironmentVariable($key, $testEnvironment[$key], 'Process')
}
Push-Location $projectRoot
try {
    & $phpExecutable artisan config:clear --env=testing
    if ($LASTEXITCODE -ne 0) { throw 'Could not clear stale configuration.' }
    if (-not $SkipMigrations) {
        & $phpExecutable artisan migrate --env=testing --force
        if ($LASTEXITCODE -ne 0) { throw 'Testing database migration failed. Confirm PostgreSQL is running and smilesuite_testing exists.' }
    }
    $testArguments = @('artisan', 'test', '--compact')
    if ($Filter) { $testArguments += "--filter=$Filter" }
    & $phpExecutable @testArguments
    if ($LASTEXITCODE -ne 0) { throw "Tests failed with exit code $LASTEXITCODE." }
} finally {
    Pop-Location
    foreach ($key in $previousEnvironment.Keys) {
        [Environment]::SetEnvironmentVariable($key, $previousEnvironment[$key], 'Process')
    }
}
