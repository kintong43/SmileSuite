# SmileSuite system design

**Authors:** Kenji B. Rique and Samuael D. Luz — BSIT III F1  
**Courses:** Web Systems and Technologies 2; System Integration and Architecture 1  
**Revision:** 27 October 2026

This design describes the coursework implementation and its boundaries. Requirement/objective identifiers refer to the [SRS](SRS.md). Figures are generated documentation artifacts, not screenshots or evidence of a completed live-provider transaction. Editable sources are in [diagrams/src](diagrams/src); PNG and SVG versions are retained for review and reuse.

## 1. Use cases

![Patient use cases](diagrams/png/usecase-patient.png)

*Figure 1. Patient account, appointment, intake, and content use cases.*

![Clinic staff use cases](diagrams/png/usecase-staff.png)

*Figure 2. Clinic operational and assigned-care use cases. “Clinic staff” is a diagram grouping; each action still requires its specific role and resource policy.*

![Management use cases](diagrams/png/usecase-management.png)

*Figure 3. Clinic management, CMS, and separate platform administration. “Authorized manager” groups several distinct identities and does not grant a universal role.*

| Use case | Authorized actor | Main result / important restriction | Trace |
| --- | --- | --- | --- |
| UC-01 Register account | Clinic applicant / patient | Create clinic owner or tenant-local patient account; validate subdomain/account uniqueness. | OBJ-01 / FR-01 |
| UC-02 Sign in and manage profile | Clinic member | Authenticate, update own allowed fields, verify changed email, or change password and sign in again. | OBJ-01 / FR-02 |
| UC-03 Book appointment | Verified patient / authorized scheduling staff | Select service, practitioner, date, slot, and chair; atomically reserve eligible resources. | OBJ-02 / FR-03 |
| UC-04 Reschedule or cancel visit | Own patient / authorized staff | Revalidate availability/policy and expected version; preserve an event history. | OBJ-02 / FR-04 |
| UC-05 Submit and view own intake | Patient | Submit versioned intake and view own submissions without internal-note access. | OBJ-03 / FR-05 |
| UC-06 Browse content and clinic store | Public visitor / clinic user as applicable | Read published content; use the clinic's optional store. Booking works without commerce. | OBJ-04 / FR-08 |
| UC-07 View clinic schedule | Authorized clinic staff | See operational appointments in the current clinic. | OBJ-02 / FR-03 |
| UC-08 Register walk-in patient | Authorized clinic staff | Create a clinic patient profile without requiring a linked user account. | OBJ-02 / FR-06 |
| UC-09 Record appointment status | Authorized clinic staff | Apply allowed lifecycle transition with version/event tracking. | OBJ-02 / FR-04 |
| UC-10 Read assigned clinical records | Assigned / delegated practitioner | Read only clinically authorized patient records; receptionist access is not clinical authorization. | OBJ-03 / FR-05 |
| UC-11 Write clinical note amendment | Authorized practitioner | Save a new note version/amendment rather than overwrite signed history. | OBJ-03 / FR-05 |
| UC-12 Review notification delivery | Authorized staff | Inspect delivery state and retry eligible work without viewing secret provider credentials. | OBJ-02 / FR-09 |
| UC-13 Configure clinic and staff | Owner / specifically authorized staff | Maintain resources, services, hours, blocks, invitations, and roles within permitted scope. | OBJ-01 / FR-06 |
| UC-14 View and export reports | Owner / receptionist | Filter local dates and export aggregate counts/scheduled value, excluding patient records. | OBJ-04 / FR-10 |
| UC-15 Manage clinic subscription | Owner | View billing and provider contracts; live billing remains gated. | OBJ-04 / FR-07 |
| UC-16 Manage published content | Scope-specific Directus editor | Edit/publish pages, announcements, and service descriptions for one scope. | OBJ-04 / FR-11 |
| UC-17 Publish safe CMS projections | Authorized operator via CLI | Synchronize staff display names/roles, services, and aggregate reports without patient/credential content. | OBJ-04 / FR-11 |
| UC-18 Administer tenant metadata | Separate platform administrator | Manage audited operational lifecycle/billing metadata without routine clinical access. | OBJ-01 / FR-12 |

For UC-03, the actor signs in to a resolved clinic and selects an eligible service/practitioner/date. Laravel calculates candidate availability; on submission it rechecks policy, tenant ownership, resource eligibility, schedule blocks, and occupancy inside the booking transaction. PostgreSQL rejects overlapping non-cancelled practitioner/chair intervals. Success returns the appointment and records event/outbox work. An unavailable slot, stale request, or unauthorized patient fails without creating a partial reservation.

For UC-16, a CMS editor signs in to Directus using a scope-specific identity, edits an allowed content collection, and marks the entry published. Laravel selects the corresponding server-side scope token and requests only allowlisted fields. Draft, expired, foreign-scope, or invalid responses are excluded. A CMS outage produces an unavailable message/fallback rather than access to another scope.

## 2. Context diagram

![SmileSuite context diagram](diagrams/png/context.png)

*Figure 4. SmileSuite as a single system with external actors and data flows. [Editable Mermaid](diagrams/src/context.mmd) and [flow register](diagrams/src/context.json).*

Patients send account, booking, and intake information and receive their permitted appointments/content. Clinic staff exchange operational or authorized care updates; owners exchange configuration and report requests. Platform administrators exchange operational metadata. Directus supplies published content and receives safe projections. Provider boundaries carry billing/storefront contracts and minimal email notices. These arrows describe interfaces; Stripe/Shopify arrows do not establish live account configuration or successful transactions.

## 3. Architecture

![SmileSuite architecture](diagrams/png/architecture.png)

*Figure 5. Browser, Laravel application, application database, separate CMS/API/database, worker, and external-provider boundaries. [Editable Mermaid](diagrams/src/architecture.mmd) and [component register](diagrams/src/architecture.json).*

The browser renders Blade pages styled with Tailwind and enhanced with Alpine. Laravel controllers delegate application logic to services and enforce authentication, MFA, host-derived tenancy, validation, and resource policies. Eloquent tenant scopes and PostgreSQL composite foreign keys reinforce tenant ownership. Protected clinical fields use application encryption; credentials remain hashed or protected server-side as appropriate.

The application uses PostgreSQL 16 (`smilesuite`); tests use a separate disposable database. The current local queue is database-backed, with outbox/delivery records tracking background work. Local mail is logged. Deployment scaffolding includes Redis, but a local demonstration does not prove that deployment or recovery configuration.

Directus **11.17.4** runs separately at `http://127.0.0.1:8055` with database `smilesuite_cms11` and its own database role. Its five `ss_*` collections contain CMS content and safe projections. The request host determines `platform` or `clinic-<subdomain>`; the Laravel client uses a corresponding secret token, applies a scope filter, and checks returned row scope. Directus permissions independently enforce the scope. CMS editing accounts are separate from Laravel clinic identities. CMS administration is not a path to application patient tables.

Stripe subscription and Shopify storefront clients are external contracts with fixture paths for development. Clinic appointment fees remain pay-at-clinic; clinic Shopify merchants retain checkout/fulfillment responsibility. Live-provider flags and real-patient-data access remain disabled until their release gates are satisfied. See [CMS setup/evidence](CMS.md) and [implementation evidence](../IMPLEMENTATION.md).

## 4. Entity relationship diagrams

The ERD is split by domain so physical field/key relationships remain readable. Dashed/optional connections and crow's-foot cardinalities come from the schema metadata, including composite tenant foreign keys. [The complete Mermaid ERD](diagrams/src/erd-full.mmd), [machine-readable schema](diagrams/src/erd.json), [schema-only SQL](diagrams/src/schema.sql), and [full data dictionary](diagrams/text/data-dictionary.md) accompany these figures.

![Core identity ERD](diagrams/png/erd-core.png)

*Figure 6. Identity and clinic ownership.*

![Scheduling resources ERD](diagrams/png/erd-scheduling-resources.png)

*Figure 7. Services, practitioners, chairs, and eligibility relations.*

![Scheduling calendar ERD](diagrams/png/erd-scheduling-calendar.png)

*Figure 8. Recurring hours, shifts, breaks, and dated blocks.*

![Scheduling bookings ERD](diagrams/png/erd-scheduling-bookings.png)

*Figure 9. Appointments, state history, and idempotency.*

![Clinical ERD](diagrams/png/erd-clinical.png)

*Figure 10. Intake, notes, consent, and care assignments.*

![Billing ERD](diagrams/png/erd-integrations-billing.png)

*Figure 11. Subscriptions, entitlements, checkout references, and provider event inbox.*

![Commerce and delivery ERD](diagrams/png/erd-integrations-delivery.png)

*Figure 12. Clinic commerce connections/carts and notification delivery.*

![Platform ERD](diagrams/png/erd-platform.png)

*Figure 13. Separate platform identities and operational audits.*

The eight domain figures focus on application relationships; framework queue/cache/session tables are preserved in the physical schema and dictionary. CMS collections belong to a separate database and have no cross-database patient foreign keys. [DATABASE_SCHEMA.md](DATABASE_SCHEMA.md) explains principal tables, cardinalities, constraints, and CMS fields.

## 5. UI wireframes

These grayscale wireframes describe the information hierarchy of the principal screens. They are design references, not an assertion of pixel-identical implementation. Their editable screen inventory is [wireframes.json](diagrams/src/wireframes.json).

![Login wireframe](diagrams/png/wireframe-login.png)

*Figure 14. Clinic sign-in and registration entry.*

![Dashboard wireframe](diagrams/png/wireframe-dashboard.png)

*Figure 15. Clinic overview and operational navigation.*

![Booking wireframe](diagrams/png/wireframe-booking.png)

*Figure 16. Service, practitioner, date, and available-slot selection.*

![Profile wireframe](diagrams/png/wireframe-profile.png)

*Figure 17. Own-profile fields and credential update controls.*

![Reports wireframe](diagrams/png/wireframe-reports.png)

*Figure 18. Date filters, aggregate summary, breakdowns, and CSV export.*

![Public content wireframe](diagrams/png/wireframe-content.png)

*Figure 19. Public CMS pages, announcements, and service information.*

![CMS dashboard wireframe](diagrams/png/wireframe-cms.png)

*Figure 20. Owner-facing CMS connection/scope summary and editor entry.*

The primary patient path is clinic entry → sign-in/register → portal → appointment selection → booking confirmation, with own intake/profile available after authentication. Staff move from sign-in/MFA to overview/schedule and permitted patient/configuration/report screens. CMS editing opens the separate Directus interface. Forms must retain understandable labels, validation, and keyboard focus; responsive behavior and access controls require runtime checks in addition to these diagrams.
