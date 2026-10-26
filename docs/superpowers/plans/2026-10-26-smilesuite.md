# SmileSuite Implementation Plan

> **For agentic workers:** Use superpowers:subagent-driven-development for bounded modules and native integration. Follow the user-provided system plan.

**Goal:** Build a runnable synthetic-data SmileSuite MVP in the requested folder.

**Architecture:** Modular Laravel monolith. Exact clinic host resolution precedes tenant-local identity and resource authorization. PostgreSQL constraints, a tenant scheduling lock, and a transactional outbox protect reservations.

**Tech Stack:** Laravel 13, project-local PHP 8.4, PostgreSQL 16, Blade, Tailwind, Alpine, Vite, Redis in deployed environments, Cashier Stripe.

**Spec:** docs/smilesuite_system_plan_v2.md

## Global Constraints
- Synthetic data only; no live payment, patient-data or launch approval inferred.
- One location and optional Shopify store per tenant; appointment payments at clinic.
- Fail closed without tenant context; composite tenant foreign keys; separate platform identity.
- No SQLite integration substitution. Use PostgreSQL 16 with btree_gist.
- Private commerce credentials remain server-side. Shopify API 2026-10.
- No clinical content in email, calendar, commerce, logs, or operational appointment views.

## Review Focus
- Unknown hosts, worker reuse, reset tokens, and resource IDs must never cross tenants.
- Simultaneous conflicting bookings must produce one reservation; retries must be idempotent.
- Same-clinic patient substitutions and practitioner access after reassignment must fail.
- Billing duplicates and reordered deliveries must reconcile authoritative state.
- Shopify errors and queue outages must preserve appointment operations.

## Tasks
- [x] 1. Scaffold pinned dependencies; configure PostgreSQL, session/host security, CI and local bootstrap scripts.
- [x] 2. Implement tenant identity, resource schema and policies, separate platform guard, two synthetic clinics, and isolation tests.
- [x] 3. Implement scheduling guard, availability, snapshots, idempotent booking, versioned changes, resource configuration and concurrent database tests.
- [x] 4. Implement patient intake, practitioner notes, clinical access auditing and care assignments with authorization tests.
- [x] 5. Implement Cashier-compatible tenant billing, signature-verified durable inbox and reconciliation; optional server-side Shopify connections/catalog/cart with contract fixtures and tests.
- [x] 6. Build staff dashboard, schedule, patients, services, settings and patient portal; wire forms, responsive layouts, notifications/outbox and minimal calendar updates.
- [x] 7. Run integrated tests and asset build, review security boundaries, document actual coverage, operations and outstanding external release evidence.

Completed for the local synthetic-data implementation. These checkmarks do not close the source plan's production/pilot gates. See `docs/IMPLEMENTATION.md` for 114 passing tests, browser checks, review corrections, and external evidence still required.

## Implementation Decisions
- User explicitly requested implementation of the supplied detailed design. Proceed with its working defaults; do not introduce another approval checkpoint for reversible local development.
- Empty target folder has no Git history or pre-existing application to preserve. Work directly in the requested folder.
- Install portable development runtimes inside ignored `.runtime/`; do not modify XAMPP or global PHP.
- Local mail uses log transport and local queue can use the database; deployed environments use Redis. Database tests always use real PostgreSQL.
- Use a calm, high-contrast teal and warm-neutral operations UI with accessible native controls.

## Verification / Evidence
Record commands and results in docs/IMPLEMENTATION.md. Clearly separate implemented local behavior from live-provider, recovery, load, and production approval evidence.
