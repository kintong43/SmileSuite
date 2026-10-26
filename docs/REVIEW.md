# Focused security and correctness review

## Resolution recorded by implementer

The findings below were reproduced and corrected after review. MFA uses an identity/IP limiter for both guards; clinic confirmation and enrollment are account-bound and ordinary login clears them. Clinical note writes reauthorize the exact encounter under the tenant lock; subsequent notes require an explicit latest-version amendment. Dated blocks are normalized to UTC using the same explicit-offset validator as booking. Billing checks expiry locally even when reconciliation fails. Session revocation now compares an account-token stamp on every authenticated clinic request and therefore also applies to Redis/file session drivers. Regression evidence is in `HttpSecurityTest`, `BillingTest` and `SchedulingTest`; final integrated results are recorded in `IMPLEMENTATION.md`.

Reviewed October 27, 2026. Scope: authentication/MFA, tenant resolution and HTTP resource boundaries, clinical authorization, scheduling inputs, commerce, and notification delivery. Read-only source review; the root agent owns current integrated tests. No migrations, database resets, or application changes were performed for this review. One database-free model serialization check was executed.

## Findings sent for correction

### R1 — P1: MFA rate limit can be bypassed with an ignored request field

At review start both clinic and platform `POST /security` used `throttle:login`. The login limiter included the submitted `email` value, although MFA actions validate only `code`. An attacker who has passed the password step can submit different arbitrary `email` fields with each six-digit guess, obtaining independent five-attempt buckets. The login action also trimmed email while the limiter did not, allowing whitespace variants to bypass its account bucket.

Affected entry points: `routes/web.php`, `routes/platform.php`; limiter: `app/Providers/AppServiceProvider.php`; consumers: `AuthController::verifyMfa`, `PlatformController::verifyMfa`.

Correction requested: dedicated MFA limiter using trusted host/account identity/IP; normalize login identifiers consistently and add an overall IP bucket. Root has begun this correction. Verify six attempts with distinct ignored emails still return 429, separately for both guards.

### R2 — P1: Schedule block offsets are discarded before persistence

`ClinicSettingsController::block` passes raw offset-bearing strings directly into `ScheduleBlock::create`; its `immutable_datetime` casts serialize dates with the connection date format, stripping their offsets without converting them to UTC. With the configured UTC PostgreSQL session, `2026-12-25T09:00:00+08:00` becomes `09:00Z`, rather than the intended `01:00Z`. Closures, practitioner leave, and chair maintenance therefore protect the wrong hours, allowing reservations during the intended blocked interval.

Database-free confirmation against the actual model: set its date format to `Y-m-d H:i:s`, assign the timestamp above to `starts_at`, and read `getAttributes()`; result is `{"starts_at":"2026-12-25 09:00:00"}`.

Affected code: `app/Http/Controllers/ClinicSettingsController.php::block`, `app/Models/ScheduleBlock.php`. Correction requested: require an explicit offset, validate local offsets/DST consistently with booking, convert both endpoints to UTC before assigning them, and compare the normalized instants. Verify through HTTP that a Manila 09:00–10:00 block removes the intended 09:00 slot, plus gap/ambiguous input cases.

### R3 — P2: A second new encounter note fails with a database exception

`ClinicalController::note` sets `note_version` to 1 whenever `supersedes_id` is absent. The schema has `UNIQUE(tenant_id,appointment_id,note_version)`. After signing the first note, the displayed form still permits “New note”; submitting it again for the same encounter reaches an unhandled PostgreSQL unique violation instead of a usable validation response. A retried first submission has the same problem.

Affected code: `app/Http/Controllers/ClinicalController.php::note`, `resources/views/clinical.blade.php`, core migration's `clinical_notes` constraint. Correction requested: enforce one amendment chain per encounter with a validation response requiring the latest note, or explicitly allocate encounter-wide versions under the existing lock. Preserve append-only clinical records. Verify the second submission gives a validation error or a supported new version without overwriting prior content.

## Earlier findings checked

- Clinic MFA confirmation now binds to `mfa_user_id`, ordinary login clears prior confirmation/enrollment, and enrollment binds to an account with a ten-minute deadline. This closes the previously identified account-switch confirmation issue in the reviewed source.
- Note creation rechecks `permittedAppointmentIds` after acquiring the tenant row lock, consistent with scheduling reassignment serialization. Having a current encounter no longer alone authorizes a historical completed encounter.
- `EntitlementService::allowsNewBookings` checks both explicit grace expiry and the seven-day limit from `past_due_since`; scheduling delegates to that check. Persisted enabled flags no longer alone extend grace during provider outages.
- Root additionally identified session revocation's dependency on database-backed session storage and is adding an account token stamp for file/Redis compatibility. That change requires final verification after integration.

## Boundaries reviewed without an additional finding

Exact clinic host resolution establishes context before tenant-scoped authentication and model binding. Clinical reads apply patient ownership or practitioner care scope; owners/receptionists do not obtain chart content from their operational roles. Commerce mutations bind cart lookup to tenant/session/generation, validate canonical Shopify endpoints, encrypt private tokens/cart IDs, and restrict connection changes to owners. Notification jobs restore tenant context from primitive IDs, lock and deduplicate delivery, suppress obsolete event versions, and use minimal calendar/mail content.

These are source-review observations, not claims of exhaustive penetration testing or live-provider validation.

## Release evidence remains separate

Live Stripe/Shopify authorization and contract runs, infrastructure isolation, approved health-data policies, provider arrangements, recovery/restore drills, measured load/capacity, and full manual accessibility evidence remain deployment gates. Their absence is not presented here as a newly discovered application exploit. Final completion requires the root agent's integrated suite and regression evidence for corrections above.
