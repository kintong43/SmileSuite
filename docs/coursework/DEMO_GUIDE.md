# SmileSuite demonstration guide

**Presenters:** Kenji B. Rique and Samuael D. Luz — BSIT III F1  
**Courses:** Web Systems and Technologies 2; System Integration and Architecture 1  
**Revision:** 27 October 2026

Use synthetic records throughout. This sequence demonstrates expected review steps; it is not an execution log. Consult [README](../../README.md) for environment preparation and [CMS.md](CMS.md) for the actual CMS setup and evidence. Do not reset a shared database to prepare a demonstration.

## 1. Prepare the local environment

Confirm application dependencies/assets and the seeded Harbor/Cedar development databases are ready using the README. In the prepared Windows workspace, start the database and web application:

```powershell
./scripts/start-local.ps1
```

Use separate terminals for queue/scheduler work if demonstrating notification processing:

```powershell
.runtime/php/php.exe artisan queue:work --tries=3 --timeout=60
```

```powershell
.runtime/php/php.exe artisan schedule:work
```

Directus 11.17.4 runs separately on `127.0.0.1:8055`, using the `smilesuite_cms11` database and a separate role. Follow [CMS.md](CMS.md) to set its local secret environment variables, start it with `./scripts/start-cms.ps1`, and provision content/policies if needed. Do not use `-Bootstrap` on every presentation or disclose credentials/tokens on screen. Laravel must have scope-specific `CMS_TOKENS_JSON` configuration for `platform`, `clinic-harbor`, and `clinic-cedar`.

Open [platform home](http://smilesuite.localhost:8000), [Harbor sign-in](http://harbor.smilesuite.localhost:8000/login), and [Cedar sign-in](http://cedar.smilesuite.localhost:8000/login). If `.localhost` does not resolve on the presentation machine, follow README host setup. Local demo buttons require both local environment and sandbox configuration; regular staff sign-in requires MFA. Platform administration has no demo shortcut.

## 2. Present the problem and public website

Explain that the paying customer is an independent clinic. SmileSuite coordinates clinic-local accounts, scheduling, patient intake, and operations; the proposed revenue source is the clinic's SaaS subscription. Prices and commercial launch terms are not approved.

Show the public home, [About](http://smilesuite.localhost:8000/about), [Features](http://smilesuite.localhost:8000/features), and [Announcements](http://smilesuite.localhost:8000/announcements). Point out the clinic entry links and service/content information. Reduce the browser width to inspect navigation, readable content, labeled controls, and form layout. Reference [BUSINESS_PROPOSAL.md](BUSINESS_PROPOSAL.md) and [SRS.md](SRS.md).

## 3. Show patient account and appointment workflow

1. At Harbor, choose the synthetic patient demo button. Alternatively show registration with an unused synthetic email and explain that local mail is logged; email verification is required before booking.
2. Open **My profile**, change a synthetic contact/name value, save, and reload to show persistence. Explain that email changes require the current password and renewed verification. If showing a password change, do it last: it signs the user out and revokes sessions.
3. Open **My appointments** / `/portal`, then **Book a visit**. Choose a service and future date, inspect the eligible practitioner/chair slots, select one, and confirm.
4. Confirm the appointment appears with clinic-local time and pay-at-clinic terms. Availability is checked again on save; a displayed slot is not an unconditional reservation.
5. Reschedule a permitted future visit, give a reason, and show the changed time. Cancel a permitted appointment and show its status. Avoid promising cancellation inside a restricted clinic window.
6. Open **My intake**, submit clearly synthetic answers and consent, then show the new version/history and own-intake export. Patients do not automatically receive internal practitioner notes.

This covers FR-01–FR-05. PostgreSQL prevents simultaneous practitioner/chair overlaps; the guided UI path alone does not prove the concurrency failure mode.

## 4. Show clinic staff and administration

Sign out and use Harbor's receptionist demo. Open schedule/patients, register a synthetic walk-in, and inspect appointment status actions. Then use the practitioner demo to show only clinically permitted records for an assigned synthetic patient and, if suitable, sign a note and amend its latest version. Explain that a receptionist's operational access does not grant clinical-note access.

Use the owner demo to inspect services, chairs, practitioner eligibility, hours/breaks/blocks, and staff settings. Describe the distinction between deactivating a resource and deleting referenced history. Show `/notifications` for permitted delivery state. With log mail configured, describe the result as a local log/queue demonstration, not delivered email.

## 5. Show reports and export

As owner or receptionist, open **Reports** (`/reports`). Choose an inclusive date range containing the demonstrated visits and select **Update report**. Show appointment/completed counts, scheduled value by currency, and breakdowns by status, service, practitioner, and clinic-local date.

Explain that **scheduled value is not collected revenue**. Cancelled appointments remain in appointment counts but are excluded from scheduled value; no-shows remain included. Current status and booked price snapshots determine the report. Unlike currencies remain separate.

Select **Export CSV** and inspect an aggregate row. Patient names/contact information, intake, notes, and appointment-level rows must be absent. Each breakdown repeats the appointment population; do not total all sections together. Try a reversed range and show validation. Later, use a patient/practitioner session to request `/reports` and confirm access is denied. Details: [REPORTS.md](REPORTS.md).

## 6. Demonstrate the actual CMS integration

1. As Harbor owner, open **Content management** (`/cms`) and confirm the scope is `clinic-harbor` and that the CMS is connected. An unavailable message is a failure state, not evidence of a working round trip.
2. Open the separate [Directus editor](http://127.0.0.1:8055/admin) and sign in privately with the Harbor editor identity. Show the five collections: pages, announcements, services, directory, and reports. These CMS identities are separate from clinic accounts.
3. Edit a Harbor page or announcement with an obvious synthetic marker, publish it, and reload the corresponding Harbor public page. Show the marker returned through the real local HTTP integration. Change it to draft or move its publication window outside the current time and confirm it is hidden.
4. Open the equivalent Cedar page and platform page. Confirm the Harbor marker is absent. Show that the Harbor editor cannot list/change Cedar/platform rows. Retain separate API permission checks in the CMS evidence; browser filtering alone is insufficient proof.
5. To publish safe operational projections, the authorized local operator runs the existing command:

```powershell
.runtime/php/php.exe artisan cms:sync harbor --no-interaction
```

6. Refresh Directus and inspect service descriptions, staff display names/roles, and aggregate report data. Do not show or enter patient data, clinical notes, application password hashes, MFA secrets, or integration tokens in CMS content. CMS service text is informational; operational booking terms remain in Laravel.
7. If demonstrating failure handling, stop only the local CMS service under your control, reload the content/CMS view, and inspect its unavailable state while clinic scheduling remains accessible. Restart CMS afterward. Do not disable the database or shared services to simulate this.

Use [CMS.md](CMS.md) for recorded HTTP/permission evidence and limitations. These are local integration steps, not a claim of live Stripe/Shopify verification.

## 7. Show isolation and integration boundaries

Sign out and use a Cedar demo account. Show its own clinic name, schedule, services, and report scope. Requesting a Harbor-only resource identifier while signed in to Cedar must not disclose the Harbor record. Missing/foreign records may return a denial or not-found response according to the route; do not depend on a specific numeric ID from this guide.

Show billing and store screens with live flags disabled. Explain the intended contracts: clinic-to-platform subscription billing, clinic-owned Shopify storefront and hosted checkout, and pay-at-clinic appointments. Describe any fixture response explicitly as a fixture. Do not run live checkout, charge a card, or claim a real store/order integration from a local screen.

If a separate platform administrator is already prepared, open [platform sign-in](http://admin.smilesuite.localhost:8000/platform/login), authenticate with MFA, and show operational tenant/lifecycle metadata. Do not create credentials during the presentation or claim that platform administration grants clinical access.

## 8. Close with review artifacts and outstanding items

Open [SYSTEM_DESIGN.md](SYSTEM_DESIGN.md) for use case, context, architecture, ERD, and wireframe figures. Open [DATABASE_SCHEMA.md](DATABASE_SCHEMA.md) and the [full dictionary](diagrams/text/data-dictionary.md) to identify actual keys, at least five relational tables, composite tenant foreign keys, and appointment conflict constraints.

Record the date, environment, steps performed, actual results, and observed failures in the appropriate evidence record. Requirements, wireframes, route listings, and this guide do not replace executed checks. Remaining business/privacy/provider/recovery approvals follow the revised plan's D1–D7 gates. Instructor repository access still needs the instructor's GitHub username; do not present an unsent invitation as complete.
