# Dependency record

Baseline resolved on October 26, 2026 and verified locally on October 27, 2026. The lockfiles, rather than ranges below, define reproducible application dependencies.

| Component | Installed version / contract |
| --- | --- |
| PHP portable runtime | 8.4.27 NTS x64; official archive SHA-256 checked before extraction |
| Laravel framework | 13.38.0 |
| Laravel application skeleton | 13.11.0 |
| Laravel Cashier | 16.9.0, Tenant custom billable model and scoped subscription models |
| Stripe PHP | 21.3.2 |
| Stripe API / webhook contract | 2026-08-26.dahlia (Stripe SDK constant; authorized endpoint verification still required) |
| Laravel Fortify | 1.41.0; authenticator provider, application-owned tenant authentication routes |
| Predis | 3.7.0 |
| PostgreSQL portable runtime | 16.15; btree_gist installed by schema migration |
| Shopify Storefront API | 2026-10; private token header; fixture validation only |
| Vite | 8.3.5 |
| Frontend | Tailwind 4, Alpine 3, self-hosted Manrope; exact versions in package-lock.json |
| Browser verification | Playwright Chromium with axe-core; exact versions in package-lock.json |

Local PHP does not replace the installed XAMPP PHP 8.2. Use `.runtime/php/php.exe` or the supplied PowerShell scripts. Integration tests require PostgreSQL; SQLite is deliberately rejected by the core migration.

Deployments must resolve current patched runtime/container images, scan dependencies and record actual image digests. Redis 7.4 is configured for container services, but this machine has no Docker/Redis runtime; those services and CI have not been executed here. Cashier and Stripe API versions must match the registered webhook endpoint during an authorized live-provider test. A resolved dependency set is not a claim of live-provider compatibility or production security approval.
