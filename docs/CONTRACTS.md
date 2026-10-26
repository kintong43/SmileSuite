# Internal implementation contracts

All developers share this working folder. Do not overwrite other modules.

- `App\Tenancy\TenantContext`: immutable constructor `__construct(Tenant $tenant)`, public readonly `$tenant`, `id(): int`.
- `App\Tenancy\TenantRunner::run(int|Tenant $tenant, Closure $callback): mixed`: bind context before tenant queries, clear in finally. Middleware resolves exact host before bindings and authentication.
- `App\Models\Tenant`: id, name, subdomain, timezone, currency, lifecycle_status, slot_interval_minutes=15, booking_horizon_days=90, minimum_lead_minutes=120, cancellation_window_hours=24, branding JSON, stripe_id, billing_status. Cashier Billable added by integration owner if needed.
- `App\Models\User`: tenant-scoped Authenticatable; name, email normalized, status, email_verified_at; `hasRole(string ...$roles): bool`, `roleNames(): array`. `user_roles` fields tenant_id, user_id, role.
- `App\Models\TenantModel`: base scoped Eloquent model; trusted tenant insertion and immutable tenant ownership. Other models below inherit it. IDs are bigint.
- `PatientProfile`: user_id nullable, first_name, last_name, email nullable, phone nullable, date_of_birth nullable, status=active. `full_name` accessor.
- `Practitioner`: user_id, display_title nullable, specialization nullable, active boolean. Relation `user()`.
- `Operatory`: name, active boolean.
- `Service`: name, category, description nullable, duration_minutes, buffer_before_minutes, buffer_after_minutes, price_minor, currency, active.
- `practitioner_services` and `operatory_services`: tenant_id and respective IDs (composite foreign keys).
- `ClinicHour`, `PractitionerShift`, `PractitionerBreak`: weekday ISO 1–7, local_start, local_end, effective_from/to nullable, practitioner_id for shifts/breaks.
- `ScheduleBlock`: practitioner_id nullable, operatory_id nullable, resource_type clinic/practitioner/operatory, starts_at, ends_at, reason nullable; correctly typed checks/FKs.
- `Appointment`: public_code, patient_id, practitioner_id, service_id, operatory_id, start_at, end_at, occupied_start_at, occupied_end_at, status, price_minor, currency, duration_minutes, buffer_before_minutes, buffer_after_minutes, timezone, version=1, payment_method=pay_at_clinic, checked_in_at/in_chair_at/completed_at nullable. Relations patient(), practitioner(), service(), operatory(). datetime casts.
- `AppointmentEvent`: appointment_id, actor_id nullable, prior_state nullable, new_state, reason nullable, occurred_at, version.
- `IdempotencyRequest`: actor_id, operation, key_hash, request_hash, result_resource_id, expires_at.
- `OutboxMessage`: event_type, resource_id, version, deduplication_key unique per tenant, state=pending, attempts=0, available_at, processed_at nullable, last_error_code nullable.
- `NotificationDelivery`: outbox_message_id, channel=email, state, attempts, sent_at nullable, last_error_code nullable.
- `AuditEvent`: actor_id nullable, action, resource_type, resource_id nullable, metadata JSON default {}, occurred_at. Never medical payload.
- `IntakeSubmission`: patient_id, appointment_id nullable, form_version, answers encrypted array, submitted_at, author_id, supersedes_id nullable.
- `ClinicalNote`: patient_id, appointment_id, practitioner_id, content encrypted, note_version, signed_at nullable, supersedes_id nullable.
- `CareAssignment`: patient_id, practitioner_id, appointment_id nullable, granted_by, valid_from, valid_to nullable, revoked_at nullable, purpose.
- `ConsentRecord`: patient_id, user_id nullable, purpose, notice_version, granted_at, withdrawn_at nullable, capture_method.

Integration owner creates its own later migration for Shopify/Cashier/inbox/entitlements tables and models. Foundation does not create those.

Scheduling owner public interfaces:
- `App\Scheduling\AvailabilityService::slots(Service $service, string $date, ?int $practitionerId = null, ?int $operatoryId = null): array` returns arrays `start_at`, `end_at`, `practitioner_id`, `operatory_id` UTC ISO strings.
- `App\Scheduling\BookingService::book(User $actor, array $data, string $idempotencyKey): Appointment`; fields patient_id,service_id,practitioner_id,operatory_id,start_at; optional override_reason for staff.
- `BookingService::reschedule(User $actor, Appointment $appointment, array $data, int $version, string $reason): Appointment`; same selection data, retain patient/service if omitted.
- `BookingService::transition(User $actor, Appointment $appointment, string $state, int $version, ?string $reason = null): Appointment`.
- `App\Scheduling\ScheduleGuard::mutate(Closure $callback): mixed`: serialized scheduling mutation transaction; rejects config changes conflicting with active reservations.

Root owns bootstrap/app.php, config, auth controllers/routes, clinical authorization/controllers, UI views/assets, outbox delivery, local scripts, CI and documentation. Workers must communicate interface changes promptly.
