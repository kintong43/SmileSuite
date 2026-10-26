# SmileSuite software requirements specification

**Authors:** Kenji B. Rique and Samuael D. Luz — BSIT III F1  
**Courses:** Web Systems and Technologies 2; System Integration and Architecture 1  
**Revision:** 27 October 2026

## 1. Introduction

This SRS defines the coursework MVP for a multi-tenant dental clinic web application. Its readers are the students, instructor, reviewers, clinic stakeholders, and future maintainers. It translates [the revised system plan](../smilesuite_system_plan_v2.md) into traceable requirements and aligns with the [course requirement audit](REQUIREMENTS.md).

The application is a Laravel 13 / PHP 8.4 system with PostgreSQL 16 and a Blade, Tailwind, Alpine, and Vite frontend. Directus 11.17.4 is a separate CMS connected through server-side HTTP. Lockfiles record exact dependency versions. Implemented routes and source code are implementation evidence; the acceptance checks below describe expected behavior and do not themselves certify a passing execution.

## 2. Objectives

| ID | Objective |
| --- | --- |
| OBJ-01 | Provide clinic-specific accounts, self-profile management, and controlled administration with tenant isolation. |
| OBJ-02 | Coordinate patients, eligible practitioners, services, and chairs through a consistent appointment lifecycle. |
| OBJ-03 | Keep patient-submitted intake and practitioner notes versioned and accessible only to authorized users. |
| OBJ-04 | Support clinic information, subscription/commerce boundaries, CMS integration, and useful aggregate operational reports. |

## 3. Scope and users

Each tenant represents one clinic location with a unique subdomain. Clinic roles are owner, practitioner, receptionist, and patient; an account can hold multiple clinic roles. A separate platform administrator identity manages platform metadata without routine clinical access. Directus editors are separate CMS identities restricted to `platform` or `clinic-<subdomain>`.

The MVP includes clinic onboarding, authentication and profile management, staff/resources/services configuration, scheduling, own-patient portal, intake and notes, notifications, subscription state, optional storefront, CMS content, and reports. Appointment payments occur at the clinic. The current demonstration uses synthetic data. Live Stripe billing, live Shopify operation, real patient data, external email delivery, deployment recovery, and production readiness require separate evidence and applicable release gates.

Excluded features are multi-location tenants, custom domains, SMS, online clinical deposits, prescriptions, imaging/full dental charting, insurance processing, and embedded cross-system purchase history. The CMS is not a patient-record repository or application account-management backend.

## 4. Functional requirements

The IDs below match the [use case source](diagrams/src/usecase.json).

| ID | Requirement and acceptance condition | Objective / primary use cases |
| --- | --- | --- |
| FR-01 | Register clinics with a unique allowed subdomain and owner account; register patients in the resolved clinic. Reject invalid, duplicate, or reserved identities and require applicable verification before booking. | OBJ-01; UC-01 |
| FR-02 | Support sign-in, sign-out, password reset, staff MFA, and own-profile updates. Email change requires the current password and renewed verification; password change requires a confirmed password of at least 12 characters with letters/numbers, revokes sessions, and requires sign-in again. Reject submitted role/tenant/account ownership fields. | OBJ-01; UC-02 |
| FR-03 | Display the clinic schedule and calculate available slots from service eligibility, hours, shifts, breaks, blocks, buffers, and chair/practitioner occupancy. Book one patient/service/practitioner/chair per appointment. Reject stale/unavailable slots and concurrent resource overlap. | OBJ-02; UC-03, UC-07 |
| FR-04 | Reschedule and change appointment state through permitted transitions, subject to policy and booking rules. Preserve price/duration/buffer snapshots and append a versioned event; reject unauthorized or stale changes. Cancellation releases occupancy. | OBJ-02; UC-04, UC-09 |
| FR-05 | Let patients submit and view their own intake. Let assigned/authorized practitioners read permitted records and create versioned notes/amendments. Reject unrelated-patient access; do not expose internal notes automatically through the patient portal. | OBJ-03; UC-05, UC-10, UC-11 |
| FR-06 | Let authorized staff register walk-ins and configure clinic profile, services, chairs, practitioner eligibility, hours, blocks, and staff invitations/roles. Retain historical references when resources are deactivated. | OBJ-01, OBJ-02; UC-08, UC-13 |
| FR-07 | Show clinic subscription state and derived limits; support checkout/portal contracts and verified, deduplicated billing-event reconciliation. In this coursework environment live billing remains disabled. | OBJ-04; UC-15 |
| FR-08 | Display public pages, announcements, service information, and the clinic store. The optional store uses the clinic's connection and hosted checkout; commerce unavailability must not prevent appointment operations. No live merchant transaction is implied by a fixture. | OBJ-04; UC-06 |
| FR-09 | Queue minimal appointment notices and expose delivery state/retry to authorized users. Establish tenant context for background work and avoid clinical narrative in notification content. Local log mail is not external delivery. | OBJ-02; UC-12 |
| FR-10 | Permit owners/receptionists to filter operational reports by inclusive local dates, at most 366 days, and export aggregate CSV. Group by status, service, practitioner, date, and currency; exclude patient and appointment-level information. Reject unauthorized roles/invalid ranges and escape spreadsheet-formula prefixes. | OBJ-04; UC-14 |
| FR-11 | Integrate Directus collections `ss_pages`, `ss_announcements`, `ss_services`, `ss_directory`, and `ss_reports`. Apply scope-specific tokens and CMS permissions; render only published/in-window public content. Publish only safe staff/service/aggregate projections; show an unavailable state if CMS access fails. | OBJ-04; UC-16, UC-17 |
| FR-12 | Provide separate platform login/MFA and operational tenant/billing metadata with audited lifecycle changes. Platform administration must not confer routine patient-chart access. | OBJ-01; UC-18 |

Detailed report semantics are in [REPORTS.md](REPORTS.md); profile behavior is in [PROFILE.md](PROFILE.md); CMS setup and permission evidence are in [CMS.md](CMS.md).

## 5. Nonfunctional requirements

| ID | Requirement | Verification criterion |
| --- | --- | --- |
| NFR-01 Security | Hash passwords; protect form submissions with CSRF; validate input; regenerate/revoke sessions as appropriate; require staff/platform MFA outside the local demo shortcut. Keep secrets server-side. | Negative authentication/authorization/validation tests and session inspection; no secret values in browser responses, committed files, or CMS projections. |
| NFR-02 Tenant isolation | Resolve a known host before clinic access, apply tenant scopes and policies, and enforce tenant-consistent relationships in PostgreSQL. A missing/unknown tenant must fail closed. | Harbor/Cedar read/write, guessed-ID, background-job, and cross-tenant foreign-key rejection checks. |
| NFR-03 Data integrity | Use relational keys/checks, atomic writes, idempotency, appointment versions, and PostgreSQL exclusion constraints for resource conflicts. | Concurrent booking creates only one conflicting reservation; invalid relationships/state/ranges are rejected. |
| NFR-04 Privacy | Encrypt protected clinical fields, authorize clinical exports, and keep clinical text out of public content, operational reports, provider payloads, and routine logs. | Inspect representative exports/projections and test assigned-care/patient boundaries with synthetic data. |
| NFR-05 Usability | Offer responsive public/authenticated layouts with labeled forms, keyboard operation, visible focus, understandable validation, and readable narrow-screen content. | Browser walkthrough at desktop/mobile widths and accessibility checks; do not claim WCAG certification from automated checks alone. |
| NFR-06 Resilience | Bound CMS/provider request timeouts; preserve booking when commerce/CMS is unavailable; persist outbox/delivery state for retries. | Simulated provider failure and retry/deduplication checks; visible unavailable state without leaking errors/secrets. |
| NFR-07 Reporting correctness | Filter UTC instants using the clinic's local-day boundaries, including DST; keep unlike currencies separate. | Local-midnight/DST and multi-currency cases; scheduled value is never labeled collected revenue. |
| NFR-08 Maintainability | Use existing Laravel conventions, migrations, lockfiles, tests, and documented configuration boundaries. | A fresh prepared development environment can install locked dependencies and apply migrations; affected checks pass in the evidence record. |
| NFR-09 Operations | Separate development/test/CMS databases, protect configuration/keys, and establish verified backups and restoration before production. | Record an actual restore exercise and approved recovery objectives before claiming recovery capability. No measured availability/performance target is asserted here. |

## 6. Interfaces and data

Users access platform, clinic, and administrator hosts through a browser. Laravel reads/writes the `smilesuite` PostgreSQL database. The CMS has a separate `smilesuite_cms11` PostgreSQL database and local service at port 8055; its tokens are selected by trusted host-derived scope. Stripe and Shopify have separate server-side integration boundaries. Queue workers process tenant-aware outbox work; the local mail transport logs messages.

The [schema and full physical dictionary](DATABASE_SCHEMA.md) define application tables and relationships. CMS descriptions never override operational booking prices or eligibility. Reports reflect current appointment states and snapshots; they are not an accounting ledger. See [system design](SYSTEM_DESIGN.md) for diagrams and [demo guide](DEMO_GUIDE.md) for a review sequence.

## 7. Acceptance and remaining decisions

Review the public pages, clinic sign-in/profile, booking, authorized clinical workflows, reports/export, and real local Directus content round trip using synthetic data. Confirm negative cases for a second clinic, disallowed roles, invalid dates, draft content, and CMS failure. Record executed results separately; a route, screenshot, or requirement is not proof of all failure modes.

Commercial prices, real-data/privacy/retention approvals, authorized live provider accounts, production hosting/recovery, and release ownership remain governed by [system-plan decisions D1–D7](../smilesuite_system_plan_v2.md#12-decision-register-and-launch-gates). Instructor GitHub access remains pending the instructor's username; it is not an application acceptance result.
