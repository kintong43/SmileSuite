# SmileSuite database schema

**Authors:** Kenji B. Rique and Samuael D. Luz — BSIT III F1  
**Courses:** Web Systems and Technologies 2; System Integration and Architecture 1  
**Revision:** 27 October 2026

## Source and database boundaries

The application schema is recorded in a schema-only export of PostgreSQL **16.15**, database `smilesuite`, dated 27 October 2026. It contains 41 tables, including framework support tables; it does not export records or secret values. Migrations remain the executable source for application schema changes.

- [Schema-only PostgreSQL SQL](diagrams/src/schema.sql): physical tables, constraints, indexes, and sequences.
- [Complete physical data dictionary](diagrams/text/data-dictionary.md): every exported field with PostgreSQL type/size, primary/foreign key markers, nullability, defaults, and description, followed by the foreign-key register.
- [Machine-readable ERD](diagrams/src/erd.json) and [complete Mermaid ERD](diagrams/src/erd-full.mmd).
- [System-design ERD figures](SYSTEM_DESIGN.md#4-entity-relationship-diagrams): readable domain views with captions.

Application/test/CMS data are separate: `smilesuite` holds operational records; `smilesuite_testing` is disposable test storage; Directus 11.17.4 uses `smilesuite_cms11` with a separate database role. The application SQL export describes the application database, not Directus's internal tables.

## Principal relational tables

The following actual tables satisfy the requirement for at least five relational tables. Field lists are selected summaries; the linked dictionary gives their full physical definitions. Unless stated otherwise, `id` is the primary key and a mandatory `tenant_id` references `tenants.id`.

| Table | Selected columns and keys | Purpose / relationships |
| --- | --- | --- |
| `tenants` | `id` bigint PK; `name`, unique `subdomain`, `timezone`, `currency`, `lifecycle_status`, `billing_status` | Platform clinic registry; parent of tenant-owned records. |
| `users` | `id` bigint PK; `tenant_id` FK; `name`, `email`, `password`, `status`, verification/MFA/session-revocation fields | Clinic-local identity; unique `(tenant_id, email)` and `(tenant_id, id)`. Password is a hash. |
| `user_roles` | `id` bigint PK; `(tenant_id, user_id)` FK; `role` | Multiple allowlisted clinic roles per account; unique tenant/user/role. |
| `patient_profiles` | `id` bigint PK; nullable `user_id`; `first_name`, `last_name`, `date_of_birth`, `phone`, `email`, `status` | Patient demographics/contact; nullable tenant-consistent user link supports walk-ins. A linked user has at most one profile per tenant. |
| `practitioners` | `id` bigint PK; `(tenant_id, user_id)` FK; `display_title`, `specialization`, `active` | One practitioner record per linked clinic user. |
| `operatories` | `id` bigint PK; `name`, `active` | Clinic treatment chairs; appointment history retains its reference. |
| `services` | `id` bigint PK; `name`, `category`, `description`, `duration_minutes`, buffers, `price_minor`, `currency`, `active` | Operational booking catalog; durations/prices are validated. |
| `practitioner_services` | `id` bigint PK; tenant-consistent `practitioner_id`, `service_id` FKs | Practitioner/service many-to-many eligibility with a unique pair per tenant. |
| `operatory_services` | `id` bigint PK; tenant-consistent `operatory_id`, `service_id` FKs | Chair/service many-to-many compatibility with a unique pair per tenant. |
| `appointments` | `id` bigint PK; unique `public_code`; composite tenant FKs to `patient_id`, `practitioner_id`, `service_id`, `operatory_id`; UTC instants, `status`, snapshots, `version` | One patient/service/practitioner/chair reservation with buffers and historical price/duration/currency. |
| `appointment_events` | `id` bigint PK; composite tenant appointment/actor FKs; `prior_state`, `new_state`, `reason`, `occurred_at`, `version` | Append-only change history; unique tenant/appointment/version. |
| `intake_submissions` | `id` bigint PK; patient/appointment/author/superseded-record FKs; `form_version`, `answers`, `submitted_at` | Versioned patient intake; protected answers are encrypted by the model. |
| `clinical_notes` | `id` bigint PK; patient/appointment/practitioner/superseded-record FKs; `content`, `note_version`, `signed_at` | Separate protected clinical notes; amendments preserve prior versions. |
| `care_assignments` | `id` bigint PK; tenant-consistent patient/practitioner/grantor FKs; validity/revocation fields | Explicit clinical delegation in addition to current appointment assignment. |

Additional domain tables cover clinic hours, practitioner shifts/breaks, dated schedule blocks, staff invitations, consent records, idempotency requests, subscriptions/items, entitlements, checkout sessions, Shopify connections/carts, webhook events, outbox messages, notification deliveries, and tenant/platform audits. Platform identities are in `platform_admins`, separate from clinic `users`. Framework tables for sessions, queues, failed jobs, cache, migrations, and password-reset tokens remain in the full dictionary.

## Cardinality and referential integrity

One tenant owns many users, patients, services, resources, and appointments. One user has many role rows and at most one linked patient profile/practitioner record. Patients, practitioners, services, and chairs each have many appointments, while each appointment references exactly one of each. Practitioner/service and chair/service eligibility use junction tables. Appointments have many event records; patients have many intake/note versions.

Tenant-owned relationships use composite keys such as `appointments(tenant_id, patient_id)` → `patient_profiles(tenant_id, id)`. The additional unique `(tenant_id, id)` candidate keys let PostgreSQL reject cross-clinic references even if an identifier is guessed. Clinical links additionally bind the patient to the referenced appointment/version where defined in the physical foreign-key register. Tenant filtering and policies are still required for reads and action authorization.

History-bearing relationships commonly use `ON DELETE RESTRICT`; the exact rule for every relationship is in the dictionary/SQL. Resource deactivation preserves historical references. Nullability is deliberate for optional relationships, including an unlinked walk-in patient and optional appointment/superseded-version references.

## Important constraints and representation choices

- Appointment times are timezone-aware UTC instants; recurring hours use local times plus the clinic timezone. Reporting derives local-day boundaries before UTC filtering.
- `appointments_practitioner_no_overlap` and `appointments_operatory_no_overlap` are GiST exclusion constraints over tenant/resource and half-open occupied intervals `[start, end)`. They apply when status is not `cancelled`. `btree_gist` supports equality in those constraints.
- Appointment checks require occupied start ≤ clinical start < clinical end ≤ occupied end, positive duration/version, nonnegative buffers/prices, an allowed state, three-letter uppercase currency, and `pay_at_clinic` payment method.
- `schedule_blocks` checks require a valid time interval and exactly the target permitted by clinic/practitioner/operatory block type. Resource references are actual constrained columns.
- Tenant subdomains are unique, normalized, syntactically checked, and reject reserved names. User email is normalized and unique within the tenant. Roles are owner, practitioner, receptionist, or patient.
- Prices use integer minor units with currency; the existing display convention uses two decimal places. Appointment snapshots preserve the booked terms after service changes. Unlike currencies are never totaled together.
- Report scheduled value sums non-cancelled appointment snapshots, including no-shows. It is not collected revenue or an immutable accounting ledger.

The design separates identities, resources, eligibility, bookings, and clinical versions to avoid repeating entity details in each transaction. Appointment snapshots and CMS projections are deliberate copies for historical terms/presentation, not a claim that every field is a fully normalized master record. No formal whole-schema normal-form certification is asserted.

## CMS collection schema

Directus supplies its own system/authentication tables in its separate database. The five coursework collections below each have a UUID `id` primary key and a mandatory `tenant_key` scope. Scope is `platform` or `clinic-<subdomain>` and is enforced through Directus permissions/presets/validation and the Laravel integration. It is not a cross-database foreign key.

| Collection | Content/projection fields | Authority / permitted data |
| --- | --- | --- |
| `ss_pages` | `slug`, `title`, `body`, `status` | Editor-managed public pages; only published content is rendered. |
| `ss_announcements` | `title`, `body`, `status`, `publish_from`, `publish_to` | Editor-managed announcements with optional publication window. |
| `ss_services` | `title`, `body`, `status`, `service_ref` | Public service descriptions / safe projection; does not replace operational booking terms. |
| `ss_directory` | `display_name`, JSON `roles`, `resource_id` | Staff display names/roles and opaque source reference; no patient directory or account credentials. |
| `ss_reports` | `period`, JSON `aggregates` | Month-to-date appointment/completed counts and scheduled value in the current clinic currency; no appointment-level patient rows. |

CMS content must contain no patient identifiers/contact details, intake, consent, clinical notes, or application password hashes/MFA secrets/tokens. [CMS.md](CMS.md) records provisioning, synchronization, and permission verification. Local CMS setup success does not establish production data handling or provider readiness.
