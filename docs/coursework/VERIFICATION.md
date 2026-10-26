# Coursework verification — 27 October 2026

Environment: PHP 8.4.27, Laravel 13.38, PostgreSQL 16.15, Node 24 for the application, Node 22.23.4 and Directus 11.17.4 for the isolated CMS. All records and identities used were synthetic. PHP tests use `smilesuite_testing`; browser and CMS tests use the local demo application/CMS databases. Do not run competing PHP test suites against the same test database.

| Check | Executed result |
| --- | --- |
| Full PHP regression suite | **148 tests, 773 assertions, all passed** |
| Profile regression | **9 tests, 45 assertions, passed**, including old reset-link invalidation and throttled password attempts |
| Reports regression | **17 tests, 68 assertions, passed**, including authorization, isolation, midnight/DST dates, currencies and CSV safety |
| CMS regression | **8 tests, 94 assertions, passed**, including scope collision, escaping, publication windows, provider failure and projection restrictions |
| Browser suite with real CMS enabled | **6 workflows passed**, including existing booking/intake, role navigation and accessibility checks, new profile/report/CMS screens, public content, and actual Directus publishing/isolation |
| Frontend production build | `npm run build` succeeded |
| PHP formatting | `vendor/bin/pint --dirty --format agent` passed |
| Diagram generation | Validated 41 tables / 70 foreign keys; rendered 20 SVG/PNG figures; visually inspected the contact sheet |

Important failures were reproduced and corrected rather than suppressed. A profile password change now invalidates outstanding reset links; email changes also clear prior-email reset tokens. A new test first failed because the sixth password guess returned 422, then passed after adding the existing limiter, returning 429. CMS scope keys now distinguish `platform` from `clinic-platform`. Tests retain these cases.

The actual CMS test signs into a generated scope-specific editor, verifies unfiltered reads and explicit foreign-row denials, publishes a temporary page, checks Harbor visibility/Cedar absence, updates/deletes its own page, rejects tenant-key injection, checks safe projections, and signs into the Directus browser editor. Temporary proof pages are removed in a `finally` block. Both synthetic clinics also completed `cms:sync` with three services, three staff records and one report each. See [CMS evidence](CMS.md).

## Repeat the checks

Use the normal installed PHP executable, or the prepared `.runtime/php/php.exe` on this Windows workspace:

```powershell
php artisan test --compact
npm run build
npx playwright test
```

The five ordinary browser workflows need the application running. The real CMS workflow skips unless explicitly enabled, because it requires Directus and the ignored generated credentials:

```powershell
$env:CMS_LIVE_TEST = '1'
npx playwright test
```

The real CMS case is `tests/browser/cms-live.spec.js`. It targets the seeded Harbor/Cedar hosts and the prepared application port 8000. The ordinary browser workflows support `BROWSER_BASE_URL`; the optional CMS endpoint supports `CMS_URL`. Do not run the CMS test against production or replace the generated synthetic credentials with real clinic identities.

Docker CMS configuration is provided but was not executed because Docker is absent here. No live Stripe charge, real Shopify merchant order, external email delivery, production deployment, full manual accessibility certification, or real-patient-data release is claimed. GitHub Actions after upload is a separate check from these local results. [Git submission status](GIT_LAB_EVIDENCE.md) records the remaining remote steps.
