[CmdletBinding()]
param(
    [ValidateRange(1024, 65535)][int]$Port = 8000,
    [switch]$DatabaseOnly
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$phpExecutable = Join-Path $projectRoot '.runtime/php/php.exe'
$pgControl = Join-Path $projectRoot '.runtime/postgres/pgsql/bin/pg_ctl.exe'
$pgData = Join-Path $projectRoot '.runtime/pgdata'
$required = @($phpExecutable, $pgControl, (Join-Path $pgData 'PG_VERSION'))
if (-not $DatabaseOnly) {
    $required += @((Join-Path $projectRoot '.env'), (Join-Path $projectRoot 'vendor/autoload.php'), (Join-Path $projectRoot 'public/build/manifest.json'))
}
foreach ($requiredPath in $required) {
    if (-not (Test-Path -LiteralPath $requiredPath)) {
        throw "Required local file is missing: $requiredPath. Follow README.md setup; this script does not download dependencies."
    }
}
if ((Get-Content -LiteralPath (Join-Path $pgData 'PG_VERSION') -Raw).Trim() -ne '16') {
    throw 'The local PostgreSQL data directory must use PostgreSQL 16.'
}

Push-Location $projectRoot
try {
    & $pgControl -D $pgData status
    $statusCode = $LASTEXITCODE
    if ($statusCode -eq 3) {
        & $pgControl -D $pgData -l (Join-Path $projectRoot '.runtime/postgres.log') -o '-h 127.0.0.1 -p 55432' -w start
        if ($LASTEXITCODE -ne 0) { throw 'PostgreSQL did not start. Review .runtime/postgres.log.' }
    } elseif ($statusCode -ne 0) {
        throw "Cannot inspect the PostgreSQL data directory (pg_ctl status returned $statusCode)."
    }
    if ($DatabaseOnly) { return }
    & $phpExecutable -r 'foreach (["pdo_pgsql", "intl", "mbstring", "zip", "bcmath"] as $extension) { if (!extension_loaded($extension)) { fwrite(STDERR, "Missing PHP extension: ".$extension.PHP_EOL); exit(1); } }'
    if ($LASTEXITCODE -ne 0) { throw 'Required PHP extensions are unavailable.' }
    Write-Host "SmileSuite: http://smilesuite.localhost:$Port (Ctrl+C stops the web server; PostgreSQL remains running)."
    & $phpExecutable artisan serve --host=127.0.0.1 --port=$Port
    if ($LASTEXITCODE -ne 0) { throw "Web server exited with code $LASTEXITCODE." }
} finally {
    Pop-Location
}
