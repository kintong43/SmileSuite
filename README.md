# SmileSuite

SmileSuite is a local, synthetic-data MVP for independent dental clinics: clinic accounts, scheduling, versioned intake and encounter notes, tenant billing, and optional clinic-owned Shopify storefronts. It uses Laravel 13, PHP 8.4, PostgreSQL 16, Blade, Alpine, Tailwind and Vite. The dependency lockfiles are the installed version record.

This implementation is for development and review. It does not establish production readiness, regulatory approval, approved prices, or permission to use real patient data. Live billing, live commerce and real-patient-data flags default to disabled. See [the revised system plan](docs/smilesuite_system_plan_v2.md), particularly its Section 12 decision register, and [implementation evidence](docs/IMPLEMENTATION.md).

## Clone and run

For the prepared repository's first upload, see [GitHub handoff](docs/GITHUB.md).

Prerequisites: PHP 8.4 with `pdo_pgsql`, `intl`, `mbstring`, `zip` and `bcmath`; Composer 2; Node 24; and PostgreSQL 16 with `btree_gist` available. The repository includes application source and lockfiles. Install dependencies locally after cloning.

```sh
git clone https://github.com/kintong43/SmileSuite.git
cd SmileSuite
composer install
npm ci
```

Copy `.env.example` to `.env` (`Copy-Item .env.example .env` in PowerShell, or `cp .env.example .env` in a Unix shell). Configure the PostgreSQL host, port and credentials for your machine, and create separate `smilesuite` and disposable `smilesuite_testing` databases. The example port `55432` matches the prepared Windows runtime; a standard PostgreSQL installation usually uses `5432`. Configure the matching test variables when running tests.

```sh
php artisan key:generate
php artisan migrate
php artisan db:seed
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Open [the platform](http://smilesuite.localhost:8000) and choose Harbor or Cedar to try a synthetic clinic. Never use the example credentials or demo records for production. Preserve `.env` and its application key once encrypted records exist. See [implementation evidence](docs/IMPLEMENTATION.md) for feature coverage and remaining release work.

## Use the prepared Windows workspace

The prepared workspace has PHP 8.4.27 in `.runtime/php`, PostgreSQL 16.15 in `.runtime/postgres/pgsql`, and an initialized data directory `.runtime/pgdata`. These portable binaries, databases and `.env` are machine-local and are not distributed in Git. Startup scripts do not download or install them.

```powershell
./scripts/start-local.ps1
```

The script checks required paths, starts PostgreSQL on `127.0.0.1:55432` only when that data directory is stopped, then runs the web server in the current terminal. Ctrl+C stops the web server; PostgreSQL remains available. Use `./scripts/start-local.ps1 -DatabaseOnly` to start only the database, or `-Port 8001` to change the web port (also update `APP_URL` in your local environment).

Open [SmileSuite](http://smilesuite.localhost:8000), [Harbor clinic](http://harbor.smilesuite.localhost:8000/login), or [Cedar clinic](http://cedar.smilesuite.localhost:8000/login). Browsers normally resolve `.localhost` names to loopback. If your machine does not, add these four names to its hosts file with `127.0.0.1`: `smilesuite.localhost`, `harbor.smilesuite.localhost`, `cedar.smilesuite.localhost`, and `admin.smilesuite.localhost`.

Each seeded clinic has demo buttons for owner, practitioner, receptionist and patient. Those shortcuts require both the local environment and sandbox flag. They use synthetic records and bypass staff MFA for local demonstrations. Regular staff sign-in requires authenticator setup/challenge. Platform administration has no demo bypass.

For a platform operator, create a separate identity with a secret password prompt (minimum 12 characters, letters and numbers), then enroll an authenticator at [platform sign-in](http://admin.smilesuite.localhost:8000/platform/login):

```powershell
.runtime/php/php.exe artisan platform:admin operator@example.test "Operator Name"
```

The command creates an account and does not reset existing accounts. Platform administration exposes operational tenant and billing metadata with audited lifecycle changes; it grants no clinical-record access.

## First setup on another development machine

Install PHP 8.4 with `pdo_pgsql`, `intl`, `mbstring`, `zip` and `bcmath`, Composer 2, Node 24/npm, and PostgreSQL 16 with `btree_gist` available. Linux queue workers also need `pcntl`. This application requires PostgreSQL; do not replace it with SQLite. The portable scripts expect the exact paths above and an already initialized PostgreSQL 16 cluster; use your normal service manager if using a system installation.

Make PHP 8.4 the PHP executable used by Composer. In the prepared Windows workspace this can be done for the current shell:

```powershell
$env:Path = "$(Join-Path (Get-Location) '.runtime/php');$env:Path"
php -v
composer -V
composer install
npm ci
npm run build
if (-not (Test-Path -LiteralPath .env)) { Copy-Item -LiteralPath .env.example -Destination .env }
php artisan key:generate
```

Generate `APP_KEY` only for a new environment. Keep an existing environment's key; changing it loses access to encrypted records and invalidates encrypted sessions. Never commit `.env` or copy development secrets into a deployment.

With PostgreSQL running, create separate development and test databases once. The example local-only credentials are `postgres` / `smilesuite-local`, port `55432`; match your own cluster's settings in `.env`. Database creation commands prompt for the password and fail harmlessly if a database already exists:

```powershell
.runtime/postgres/pgsql/bin/createdb.exe -h 127.0.0.1 -p 55432 -U postgres -W smilesuite
.runtime/postgres/pgsql/bin/createdb.exe -h 127.0.0.1 -p 55432 -U postgres -W smilesuite_testing
php artisan migrate
php artisan db:seed
./scripts/test.ps1
./scripts/start-local.ps1
```

Use equivalent system `createdb` commands when not using the portable runtime. Migrations provision the required PostgreSQL extension and constraints, so the migration role must have suitable privileges. Seeding is limited to local/testing and creates Harbor/Cedar synthetic fixtures. Schema changes are explicit; neither the web server nor container startup runs migrations or seeds automatically.

Run these in separate terminals when testing background behavior:

```powershell
.runtime/php/php.exe artisan queue:work --tries=3 --timeout=60
.runtime/php/php.exe artisan schedule:work
```

The local `.env.example` uses file sessions/cache, a database queue, log mail and host-only cookies over HTTP. Queued work requires the worker above. Mail remains in the configured log rather than being delivered. The scheduler runs registered scheduled commands; starting it alone does not create business jobs.

## Verification

```powershell
./scripts/start-local.ps1 -DatabaseOnly
./scripts/test.ps1
./scripts/test.ps1 -Filter PlatformTest -SkipMigrations
npm run build
```

`test.ps1` sets PostgreSQL database `smilesuite_testing`, clears cached configuration, applies pending migrations without resetting the database, then runs PHPUnit. It restores process environment variables afterward. It does not create the test database. Run tests serially against a dedicated local test database, away from another developer's run. Do not use `migrate:fresh` on shared databases. CI provisions a fresh PostgreSQL 16 service, applies the same migrations and runs the integration suite; Redis 7.4 is also available to CI. CI uses disposable synthetic-only keys and credentials.

The CI definition is [`.github/workflows/ci.yml`](.github/workflows/ci.yml). It installs lockfile dependencies with Composer/npm, builds assets and runs tests on PHP 8.4. The suite refreshes the disposable test schema. Final local verification passed 114 PostgreSQL tests with 566 assertions and three browser workflows. With the local server running, run `npx playwright install chromium` followed by `npx playwright test` for the browser checks. See the implementation evidence for scope and limitations.

## Container deployment candidate

`Dockerfile` builds PHP 8.4 Apache with the required extensions and Composer 2, and builds assets using Node 24. `compose.yaml` defines separate web, queue, scheduler, PostgreSQL 16 and Redis 7.4 services. Redis backs host-only encrypted sessions, cache and queues through Predis. Redis 7.4 is an extended supported release according to [Redis version management](https://redis.io/docs/latest/operate/oss_and_stack/install/version-mgmt/); pull current patched images, review licenses and pin verified image digests for a controlled release.

This is deployment scaffolding. A successful local test run does not verify container builds, external providers, TLS, recovery or production operation. Docker must be installed separately. The web port binds only to `127.0.0.1:8080` by default; PostgreSQL and Redis have no published host ports.

Create a protected `.env.production` file, already ignored by Git and Docker, or inject equivalent values from your deployment secret manager. Provide these required values without reusing the local example password:

| Variable | Value to supply |
| --- | --- |
| `APP_KEY` | A generated, stable Laravel encryption key for this environment |
| `COMPOSE_DB_PASSWORD` | Unique database password |
| `COMPOSE_REDIS_PASSWORD` | Unique Redis password |
| `DEPLOY_APP_URL` | HTTPS platform URL |
| `DEPLOY_TENANCY_DOMAIN` | Base platform domain |
| `DEPLOY_PLATFORM_HOSTS` | Exact comma-separated platform and administrator hosts |
| `DEPLOY_ADMIN_HOST` | Separate administrator hostname |

Generate a new key with `php artisan key:generate --show` in a secure operator terminal and store it as a secret. Restrict secret-file access to the deploying account. Compose stops if required values are missing. It forces `APP_ENV=production`, disables sandbox shortcuts and all live/data flags, and enables secure HttpOnly cookies. An HTTP-only browser connection to the container is therefore unsuitable for authentication.

For an approved staging environment, build and migrate explicitly before starting all services:

```sh
docker compose --env-file .env.production build
docker compose --env-file .env.production up -d postgres redis
docker compose --env-file .env.production run --rm app php artisan migrate --force
docker compose --env-file .env.production up -d app queue scheduler
docker compose --env-file .env.production exec app php artisan platform:admin operator@example.test "Operator Name"
```

Do not seed a production environment. Preserve the `private-storage`, `postgres-data` and `redis-data` volumes across releases. Private storage is outside the web document root; do not expose it using a public storage symlink. Docker volumes do not supply backup or encryption-at-rest guarantees: configure host/cloud encryption, access controls, database backups and recovery copies separately. Back up encryption keys through a separate restricted channel and prove that keys, PostgreSQL and protected files can be restored together.

Place an HTTPS reverse proxy on the same host in front of `127.0.0.1:8080`. Provision DNS and certificates for the platform, administrator and permitted clinic subdomains, preserve the original Host header, and overwrite forwarding headers at the proxy boundary. Before external use, configure Laravel to trust only that proxy's actual addresses and supported forwarding headers, then verify HTTPS redirects, secure host-only cookies, absolute URLs and unknown-host rejection. The repository does not provision DNS/certificates or automatically trust arbitrary proxies. Restrict the backend port at the host firewall.

Deploy the same image to web, queue and scheduler; restart all three after an image/configuration change. Use a controlled migration window with a tested backup and compatible rollback plan. Establish worker supervision, queue failure alerts, session/cache isolation, secret rotation, email delivery and audit retention. `MAIL_MAILER=log` in the candidate Compose file is an explicit delivery limitation; configure and validate an approved mail provider before relying on verification/invitation email.

## Gates before any live pilot

Resolve the plan's Section 12 items with accountable owners: commercial scope and tiers (D1), jurisdictions and privacy/health obligations (D2), retention and clinical access/records-release rules (D3), Stripe entity/prices/taxes/trials (D4), authorized Shopify integration (D5), hosting/providers/encryption/recovery evidence (D6), and staffing/on-call/release authority (D7). The proposed recovery targets are not proven recovery capability. Keep real patient data and live providers disabled until the applicable gate is closed.
