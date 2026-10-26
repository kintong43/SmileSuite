# SmileSuite implementation and evidence

Status: local synthetic-data MVP implemented from `smilesuite_system_plan_v2.md`. This record separates working application behavior from production and provider approvals. The original download was read and copied into `docs/`; it was not modified. The requested folder was empty and had no Git repository.

## Delivered application

| Area | Implemented behavior |
| --- | --- |
| Tenant foundation | Exact registered clinic hosts; reserved/unique subdomains; isolated tenant accounts and combined roles; immutable tenant context; fail-closed model scopes; composite tenant foreign keys; tenant-safe jobs; unknown-host rejection |
| Identity | Clinic login, patient registration and verification, tenant-bound reset tokens and invitations; staff authenticator enrollment/challenge; account-bound MFA and request throttles; host-only cookies; token-based revocation independent of session backend |
| Platform | Separate identity/guard/host, MFA, metadata-only registry, audited lifecycle changes, interactive create-only operator command; no chart bypass |
| Configuration | Clinic timezone/currency/booking windows, services, duration/buffer/price terms, chairs and practitioners, eligibility, hours/shifts/breaks, closures/leave, staff role changes and historical deactivation |
| Scheduling | Advisory slots with UTC/DST handling, practitioner and chair compatibility, hours and full buffers, lead/horizon/cancellation rules, verified patient ownership, walk-in profiles, per-clinic serialization, idempotent booking, rescheduling/version checks, audited status transitions, dual PostgreSQL exclusion constraints |
| Clinical | Own patient intake versions and download; encrypted answers and signed note versions; assigned-care policy; appointment-specific access; explicit delegation/revocation; audit of reads, exports and changes; owners/receptionists have no implicit clinical access |
| Billing | Tenant as Cashier billable model, scoped subscription models, signature-verified encrypted durable event inbox, quarantine, retries and authoritative reconciliation, derived limits, local grace expiry, gated checkout/portal |
| Commerce | Optional tenant-specific Shopify connection, canonical hostname validation, server-only encrypted token, generation-based cache/cart invalidation, session-bound encrypted cart identity, variant mutations and provider-returned checkout URL validation; distinct Harbor/Cedar fictional catalogs |
| Notifications | Transactional appointment outbox, recoverable dispatch leases, primitive-ID tenant-safe jobs, bounded retry/backoff, obsolete-version suppression, delivery visibility and audited manual retry, minimal email and UTC calendar attachments with stable UID/sequence |
| Interface | Public landing/onboarding, clinic authentication, staff overview/schedule/patients/services/settings/subscription/store, patient appointments/booking/intake, responsive menu and locally served fonts/assets |
| Packaging | PowerShell startup/test scripts, lockfiles, PostgreSQL/Redis CI definition, container build and Compose candidate, dependency record, operational documentation |

These descriptions identify implemented controls, not an assertion that every release acceptance criterion has been independently demonstrated.

## Local runtime and access

- Platform: `http://smilesuite.localhost:8000`.
- Clinics: `http://harbor.smilesuite.localhost:8000/login`, `http://cedar.smilesuite.localhost:8000/login`.
- Platform administration: `http://admin.smilesuite.localhost:8000/platform/login`.
- Run `./scripts/start-local.ps1` from the project folder. The prepared portable runtime does not alter XAMPP or globally installed PHP.
- The local clinic login pages expose clearly labeled synthetic role buttons. Normal staff login requires MFA. Platform accounts are created with `platform:admin`; no administrator password was invented or shared.
- Development data remains in the local PostgreSQL `smilesuite` database; tests use the separate disposable `smilesuite_testing` database. PHP integration tests never substitute SQLite.
- Log email and a database queue are local defaults. Start the worker and scheduler as documented in README for background delivery. No external email was sent.

## Verification commands

```powershell
./scripts/test.ps1
npm run build
npx playwright install chromium
npx playwright test
.runtime/php/php.exe vendor/bin/pint --test
.runtime/php/php.exe artisan schedule:list
```

The browser suite expects a running prepared local server and seeded Harbor/Cedar workspaces. It books then cancels synthetic reservations and adds clearly fictional intake versions. Its screenshots/results are under ignored `test-results/`. PHP suites may refresh the disposable test schema and must not run concurrently against a shared test database. True concurrency checks spawn two PHP processes with separate PostgreSQL backend IDs and a shared readiness barrier; application locks and both raw exclusion constraints are exercised.

Final combined verification on October 27, 2026:

| Check | Observed result |
| --- | --- |
| `php artisan test --compact` using project PHP | **114 tests passed, 566 assertions**, 28.843 seconds |
| `npx playwright test` | **3 passed**, including staff navigation/delivery screen, actual patient booking/cancellation/intake, cross-host session isolation, 390px reflow, dashboard axe checks and keyboard skip-link |
| `npm run build` | Passed with Vite 8.3.5; built assets in `public/build` |
| `pint --test app config routes tests database bootstrap/app.php` | Passed |
| `php artisan view:cache` | All Blade templates compiled |
| Composer strict validation | Passed after lock metadata refresh |
| Composer advisory scan | No advisories reported for installed dependencies |
| `npm audit --omit=dev` | No vulnerabilities reported |
| Scheduler registration | Outbox every minute, inbox recovery every five minutes, provider reconciliation daily |

An intermediate combined run collided with a worker's independent test-schema refresh; that failed run was discarded. The final combined run above ran without a concurrent database-resetting test process. No unresolved failure from the combined suite remains.

## Recovery exercise

A custom-format `pg_dump` of the synthetic development database was restored using `pg_restore --exit-on-error` into the separate local `smilesuite_restore_check` database. The application database was unchanged. The exercise recovered two clinics, fourteen appointment rows and two encrypted intake versions. Reading the restored intake through the normal encrypted model cast with the existing application key succeeded. Backup plus restore elapsed 2.18 seconds on this tiny local dataset.

The archive is inside ignored `.runtime/backups/`; no real patient records were used. This is a local database/key-readability smoke test. It does not establish production RPO/RTO, point-in-time recovery, storage/key escrow recovery, encrypted infrastructure, or full-volume disaster recovery. Session revocation and external-provider reconciliation after a production restore still require an operator drill.

## Review and corrections

An independent source review identified account-switch MFA reuse, historical encounter writes outside current scope, and persisted grace flags outliving expiry. Follow-up review identified an ignored-field MFA throttle bypass, discarded dated-block offsets, and a duplicate encounter-note version error. Regression tests reproduced and then verified corrections. Root additionally added session token stamps because deleting database session rows alone does not revoke Redis/file-backed sessions. `docs/REVIEW.md` retains findings and resolution notes.

Automated dashboard accessibility checks initially found low-contrast secondary text. Text colors and size were adjusted; mobile checks exposed an absolutely positioned table label escaping its scroll container, which was fixed at the container. The browser suite includes automated WCAG-tagged checks, keyboard skip-link behavior, labeled login controls and 390-pixel reflow. This is sampled automated evidence, not a whole-product manual accessibility assessment or certification.

## Remaining release evidence and deliberate limitations

- Section 12 decisions D1–D7 remain accountable business/clinical/privacy/operations decisions. No prices, jurisdiction, agreements, pilot date, staffing, or regulatory certification were invented.
- Live Stripe/Shopify transactions and authorized-store contracts have not run. Fixtures verify application contracts. Price IDs, taxes, trials, portal configuration and merchant setup require real provider review. Downgrade scheduling is not offered in the UI; provider-side over-limit changes restrict new resources/bookings while preserving existing records.
- Docker and Redis are unavailable in this host environment. Docker/Compose and CI are scaffolding whose runtime execution remains unverified. Local tests prove PostgreSQL behavior and session revocation logic, not Redis deployment behavior.
- No public DNS, certificates, hosting or production deployment was created. Production proxy trust, HTTPS, host rejection at the edge, private infrastructure, email delivery, key management, monitoring, and least-privilege migration/runtime identities require deployment verification.
- The local restore exercise is small. Representative load/capacity tests, approved backup retention, point-in-time recovery and full protected-object/key restore evidence remain release work.
- Staff chart corrections and signed notes use amendments. A broader records-release workflow for internal notes, clinic offboarding/export, retention/deletion automation, approved walk-in account matching, minors/guardian workflows and emergency access need their separately approved policies. No email-based auto-linking or unrestricted chart access is present.
- Branding uses clinic name and existing safe presentation settings. Logo upload/re-encoding, custom domains, SMS, multi-location, prescriptions, imaging, clinical deposits, embedded purchase history/SSO and patient-specific product recommendations are not enabled.
- External mail cannot be guaranteed exactly once. A provider send followed by a database commit failure can cause a duplicate during recovery; durable state, row locks and retry controls reduce duplicates without claiming to eliminate that window.

Keep `LIVE_BILLING_ENABLED`, `LIVE_COMMERCE_ENABLED` and `REAL_PATIENT_DATA_ENABLED` false until the corresponding gates and evidence are complete.
