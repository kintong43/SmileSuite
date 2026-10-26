# Directus CMS integration

SmileSuite uses **Directus 11.17.4**, pinned in `docker/directus/package.json` and its lockfile. Laravel calls its real REST API using server-only, scoped tokens. The CMS database and database role are separate from Laravel. The prepared Windows instance listens only on `127.0.0.1:8055` and uses database `smilesuite_cms11`; the Docker alternative creates its own isolated database service.

## Data and authority

| Collection | Editor/integration use |
| --- | --- |
| `ss_pages` | About, features and other public pages; draft/published status |
| `ss_announcements` | Announcements with optional publication windows |
| `ss_services` | Curated public service descriptions, with source service reference |
| `ss_directory` | Staff display names, clinic roles and source references |
| `ss_reports` | Month-to-date appointment/completed counts and scheduled value in the clinic's current currency |

The CMS does not store patient profiles, intake, clinical notes, passwords, MFA secrets, or Laravel integration credentials. “User data” in the course requirement is represented by a safe staff directory. Laravel retains application account administration and clinical access. Reports are aggregate operational snapshots, and scheduled value is not collected revenue. Editors must keep patient information out of free-text content.

CMS scope keys are disjoint: `platform` for the platform and `clinic-<subdomain>` for each clinic, including a possible `clinic-platform`. Each scope has its own editor identity, server token, role and policy. Directus permissions restrict reads/updates/deletes to the scope, create presets supply its mandatory key, and fields/validation prevent tenant overrides. Laravel also supplies the scope filter, filters returned rows, and allowlists response fields. Server tokens never enter Blade or Vite output. Public content is escaped plain text; rich HTML execution is unsupported.

Public routes are `/about`, `/features`, `/announcements`, and `/content/{slug}` on the platform and clinic hosts. `/cms` is an authenticated owner status page. CMS outages show an honest fallback; they do not enter the booking write path. Request timeouts are bounded, redirects are refused, and non-local plain HTTP endpoints are rejected.

## Prepared Windows workspace

Start the application as described in the README. In a separate terminal, start the already initialized CMS:

```powershell
./scripts/start-cms.ps1 -RuntimeConfig .runtime/cms-runtime.json -NodeExecutable .runtime/node22.exe
```

The ignored runtime JSON contains generated local CMS database and administrator credentials. `.runtime/cms-credentials.json` contains the separate `editors` and `tokens` objects for `platform`, `clinic-harbor`, and `clinic-cedar`. Open this file privately when signing in; never commit, project, or send its values. The application `.env` already contains its server token map. No credentials are published in these documents.

Open [Directus](http://127.0.0.1:8055/admin) with the appropriate editor identity. Open [Harbor content](http://harbor.smilesuite.localhost:8000/about) to inspect its published page. The platform and Cedar use their own scopes. The CMS startup script stays in the foreground; Ctrl+C stops that CMS process.

The local CMS uses Node 22 with `--no-node-snapshot` because the Windows `isolated-vm` dependency has a compatible prebuilt module. Laravel's frontend still uses Node 24. The Node 22 binary was downloaded from the official Node release and checked against its published SHA-256. The local native module and runtime are ignored artifacts, not part of the upload.

## Setup on another machine

The simplest isolated CMS setup uses the included Docker configuration. Copy `docker/directus/.env.example` to `docker/directus/.env`, then privately populate a strong database password, secret, and administrator password. Review the pinned version's license before any commercial deployment. Do not point Directus at the Laravel database.

```powershell
docker compose --env-file docker/directus/.env -f docker/directus/compose.yaml up -d
```

Docker is not installed in the prepared Windows environment, so this alternative has not been executed here. A native setup instead needs Node 22, PostgreSQL, a dedicated CMS database/role, and `npm ci --prefix docker/directus` with its required native modules. Set `DB_CLIENT`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USER`, `DB_PASSWORD`, `KEY`, `SECRET`, `ADMIN_EMAIL`, and `ADMIN_PASSWORD`, then run `scripts/start-cms.ps1 -Bootstrap` once. Use a syntactically valid synthetic email such as `cms-admin@example.com` for a local administrator.

With a fresh Directus instance running, use a separate operator terminal to provision the five collections, scoped policies, starter pages, editors and tokens:

```powershell
$env:CMS_URL = 'http://127.0.0.1:8055'
$env:CMS_TENANTS_JSON = '["platform","clinic-harbor","clinic-cedar"]'
# Set CMS_ADMIN_EMAIL and CMS_ADMIN_PASSWORD privately in this terminal.
node scripts/cms-bootstrap.mjs
```

The bootstrap administrator credential is provisioning-only. Set Laravel's `CMS_URL`, `CMS_ADMIN_URL`, and `CMS_TOKENS_JSON` in its ignored `.env`; the latter is the compact JSON `tokens` object from the generated credentials file, enclosed in single quotes. Laravel must use the scoped tokens, not an administrator token. Clear cached configuration after changing it. Bootstrap is intended for the local coursework instance, not an automatic production tenant-provisioning service.

Publish safe snapshots with the actual clinic subdomain:

```powershell
php artisan cms:sync harbor --no-interaction
php artisan cms:sync cedar --no-interaction
```

Sync adds/updates service titles, staff display names/roles and the current report snapshot. Editors curate descriptions and publish service entries; new service projections begin as drafts. It does not remove old entries automatically or provide a live staff/booking ledger. An operator must remove obsolete directory/service snapshots and refresh reports when presenting. Clinical booking terms remain authoritative in Laravel. A partial provider failure is reported and may be safely retried.

## Executed evidence — 27 October 2026

The real local REST and browser checks, using generated synthetic identities, verified:

1. Separate Harbor editor login succeeds in the API and Directus browser interface.
2. An unfiltered editor collection request returns only `clinic-harbor` rows.
3. Direct reads and updates targeting a known Cedar page return HTTP 403.
4. Unauthenticated collection reads return 403; submitting another tenant key is rejected.
5. An editor creates a published page; it immediately appears on Harbor's Laravel site without a code change and returns 404 on Cedar's site.
6. The editor updates and deletes its own temporary proof page. Proof content is cleaned up.
7. Both clinics' safe projections sync successfully: three services, three staff directory entries and one aggregate report per clinic.
8. The Directus editor's page list displays Harbor content without Cedar content.

Automated regression coverage in `tests/Feature/CmsTest.php` additionally checks HTML escaping, token/field redaction, disjoint scopes, draft/expired/future filtering, unavailable/redirect handling, HTTP URL restrictions, owner authorization, and projection payloads. The browser tests cover public navigation and the owner CMS screen. Test evidence is summarized in [VERIFICATION.md](VERIFICATION.md).

Directus 12.5.1 was evaluated locally, but its custom permission license gate rejected the required policies. No gate was bypassed; the committed implementation pins 11.17.4 and uses a fresh database. Future upgrades need explicit license/API and isolation review. This local proof establishes the course's working CMS/HTTP integration, not a production rollout or live Stripe/Shopify transaction.

References: [Directus access control](https://directus.com/docs/guides/auth/access-control), [Directus v11 releases](https://github.com/directus/directus/releases/tag/v11.17.4), [Directus starter license guidance](https://github.com/directus-labs/starters/blob/main/README.md), [official Node 22 checksums](https://nodejs.org/download/release/v22.23.4/SHASUMS256.txt).
