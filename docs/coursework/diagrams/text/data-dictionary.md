# Physical data dictionary

Source: schema-only export from PostgreSQL 16.15, database `smilesuite`, 27 October 2026. No records, credentials, or patient values are exported. Defaults below are schema definitions.

## appointment_events

Append-only appointment state and version history.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| id | bigint | PK | No | nextval('public.appointment_events_id_seq'::regclass) | Record primary identifier. |
| tenant_id | bigint | FK | No | — | Clinic ownership boundary; assigned from trusted tenant context. |
| appointment_id | bigint | FK | No | — | Related record identifier; see FK register for enforced references. |
| actor_id | bigint | FK | Yes | — | Related record identifier; see FK register for enforced references. |
| prior_state | character varying(32) | — | Yes | — | prior state; physical definition preserved from PostgreSQL. |
| new_state | character varying(32) | — | No | — | new state; physical definition preserved from PostgreSQL. |
| reason | character varying(255) | — | Yes | — | reason; physical definition preserved from PostgreSQL. |
| occurred_at | timestamp with time zone | — | No | — | occurred at; physical definition preserved from PostgreSQL. |
| version | integer | — | No | — | version; physical definition preserved from PostgreSQL. |
| created_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |
| updated_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |

## appointments

Reservations with UTC instants, status, historical price/duration/buffer snapshots.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| id | bigint | PK | No | nextval('public.appointments_id_seq'::regclass) | Record primary identifier. |
| tenant_id | bigint | FK | No | — | Clinic ownership boundary; assigned from trusted tenant context. |
| public_code | character varying(255) | — | No | — | public code; physical definition preserved from PostgreSQL. |
| patient_id | bigint | FK | No | — | Related record identifier; see FK register for enforced references. |
| practitioner_id | bigint | FK | No | — | Related record identifier; see FK register for enforced references. |
| service_id | bigint | FK | No | — | Related record identifier; see FK register for enforced references. |
| operatory_id | bigint | FK | No | — | Related record identifier; see FK register for enforced references. |
| start_at | timestamp with time zone | — | No | — | start at; physical definition preserved from PostgreSQL. |
| end_at | timestamp with time zone | — | No | — | end at; physical definition preserved from PostgreSQL. |
| occupied_start_at | timestamp with time zone | — | No | — | occupied start at; physical definition preserved from PostgreSQL. |
| occupied_end_at | timestamp with time zone | — | No | — | occupied end at; physical definition preserved from PostgreSQL. |
| status | character varying(32) | — | No | 'scheduled'::character varying | status; physical definition preserved from PostgreSQL. |
| price_minor | integer | — | No | — | price minor; physical definition preserved from PostgreSQL. |
| currency | character(3) | — | No | — | currency; physical definition preserved from PostgreSQL. |
| duration_minutes | smallint | — | No | — | duration minutes; physical definition preserved from PostgreSQL. |
| buffer_before_minutes | smallint | — | No | — | buffer before minutes; physical definition preserved from PostgreSQL. |
| buffer_after_minutes | smallint | — | No | — | buffer after minutes; physical definition preserved from PostgreSQL. |
| timezone | character varying(255) | — | No | — | timezone; physical definition preserved from PostgreSQL. |
| version | integer | — | No | 1 | version; physical definition preserved from PostgreSQL. |
| payment_method | character varying(32) | — | No | 'pay_at_clinic'::character varying | payment method; physical definition preserved from PostgreSQL. |
| checked_in_at | timestamp with time zone | — | Yes | — | checked in at; physical definition preserved from PostgreSQL. |
| in_chair_at | timestamp with time zone | — | Yes | — | in chair at; physical definition preserved from PostgreSQL. |
| completed_at | timestamp with time zone | — | Yes | — | completed at; physical definition preserved from PostgreSQL. |
| created_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |
| updated_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |

## audit_events

Tenant-scoped operational and clinical access audit metadata.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| id | bigint | PK | No | nextval('public.audit_events_id_seq'::regclass) | Record primary identifier. |
| tenant_id | bigint | FK | No | — | Clinic ownership boundary; assigned from trusted tenant context. |
| actor_id | bigint | FK | Yes | — | Related record identifier; see FK register for enforced references. |
| action | character varying(255) | — | No | — | action; physical definition preserved from PostgreSQL. |
| resource_type | character varying(255) | — | No | — | resource type; physical definition preserved from PostgreSQL. |
| resource_id | bigint | — | Yes | — | Related record identifier; see FK register for enforced references. |
| metadata | jsonb | — | No | '{}'::jsonb | metadata; physical definition preserved from PostgreSQL. |
| occurred_at | timestamp with time zone | — | No | — | occurred at; physical definition preserved from PostgreSQL. |
| created_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |
| updated_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |

## billing_checkout_sessions

Idempotent provider checkout references.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| id | bigint | PK | No | nextval('public.billing_checkout_sessions_id_seq'::regclass) | Record primary identifier. |
| tenant_id | bigint | FK | No | — | Clinic ownership boundary; assigned from trusted tenant context. |
| generation | integer | — | No | — | generation; physical definition preserved from PostgreSQL. |
| price_id | character varying(255) | — | No | — | Related record identifier; see FK register for enforced references. |
| success_url | text | — | No | — | success url; physical definition preserved from PostgreSQL. |
| cancel_url | text | — | No | — | cancel url; physical definition preserved from PostgreSQL. |
| provider_session_id | character varying(255) | — | Yes | — | Related record identifier; see FK register for enforced references. |
| checkout_url | text | — | Yes | — | checkout url; physical definition preserved from PostgreSQL. |
| expires_at | timestamp(0) with time zone | — | Yes | — | expires at; physical definition preserved from PostgreSQL. |
| created_at | timestamp(0) with time zone | — | Yes | — | Framework record timestamp. |
| updated_at | timestamp(0) with time zone | — | Yes | — | Framework record timestamp. |

## billing_entitlements

Derived server-enforced clinic plan limits.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| id | bigint | PK | No | nextval('public.billing_entitlements_id_seq'::regclass) | Record primary identifier. |
| tenant_id | bigint | FK | No | — | Clinic ownership boundary; assigned from trusted tenant context. |
| tier | character varying(255) | — | No | 'none'::character varying | tier; physical definition preserved from PostgreSQL. |
| bookings_enabled | boolean | — | No | false | bookings enabled; physical definition preserved from PostgreSQL. |
| chair_limit | integer | — | Yes | — | chair limit; physical definition preserved from PostgreSQL. |
| practitioner_limit | integer | — | Yes | — | practitioner limit; physical definition preserved from PostgreSQL. |
| past_due_since | timestamp(0) with time zone | — | Yes | — | past due since; physical definition preserved from PostgreSQL. |
| grace_until | timestamp(0) with time zone | — | Yes | — | grace until; physical definition preserved from PostgreSQL. |
| over_limit | boolean | — | No | false | over limit; physical definition preserved from PostgreSQL. |
| reconciled_at | timestamp(0) with time zone | — | Yes | — | reconciled at; physical definition preserved from PostgreSQL. |
| created_at | timestamp(0) with time zone | — | Yes | — | Framework record timestamp. |
| updated_at | timestamp(0) with time zone | — | Yes | — | Framework record timestamp. |

## cache

Framework database cache entries; local cache driver is file.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| key | character varying(255) | PK | No | — | key; physical definition preserved from PostgreSQL. |
| value | text | — | No | — | value; physical definition preserved from PostgreSQL. |
| expiration | bigint | — | No | — | expiration; physical definition preserved from PostgreSQL. |

## cache_locks

Framework database cache locks.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| key | character varying(255) | PK | No | — | key; physical definition preserved from PostgreSQL. |
| owner | character varying(255) | — | No | — | owner; physical definition preserved from PostgreSQL. |
| expiration | bigint | — | No | — | expiration; physical definition preserved from PostgreSQL. |

## care_assignments

Explicit, time-limited clinical access grants.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| id | bigint | PK | No | nextval('public.care_assignments_id_seq'::regclass) | Record primary identifier. |
| tenant_id | bigint | FK | No | — | Clinic ownership boundary; assigned from trusted tenant context. |
| patient_id | bigint | FK | No | — | Related record identifier; see FK register for enforced references. |
| practitioner_id | bigint | FK | No | — | Related record identifier; see FK register for enforced references. |
| appointment_id | bigint | FK | Yes | — | Related record identifier; see FK register for enforced references. |
| granted_by | bigint | FK | No | — | granted by; physical definition preserved from PostgreSQL. |
| valid_from | timestamp with time zone | — | No | — | valid from; physical definition preserved from PostgreSQL. |
| valid_to | timestamp with time zone | — | Yes | — | valid to; physical definition preserved from PostgreSQL. |
| revoked_at | timestamp with time zone | — | Yes | — | revoked at; physical definition preserved from PostgreSQL. |
| purpose | character varying(255) | — | No | — | purpose; physical definition preserved from PostgreSQL. |
| created_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |
| updated_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |

## clinic_hours

Recurring opening windows.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| id | bigint | PK | No | nextval('public.clinic_hours_id_seq'::regclass) | Record primary identifier. |
| tenant_id | bigint | FK | No | — | Clinic ownership boundary; assigned from trusted tenant context. |
| weekday | smallint | — | No | — | weekday; physical definition preserved from PostgreSQL. |
| local_start | time without time zone | — | No | — | local start; physical definition preserved from PostgreSQL. |
| local_end | time without time zone | — | No | — | local end; physical definition preserved from PostgreSQL. |
| effective_from | date | — | Yes | — | effective from; physical definition preserved from PostgreSQL. |
| effective_to | date | — | Yes | — | effective to; physical definition preserved from PostgreSQL. |
| created_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |
| updated_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |

## clinical_notes

Versioned encrypted practitioner notes.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| id | bigint | PK | No | nextval('public.clinical_notes_id_seq'::regclass) | Record primary identifier. |
| tenant_id | bigint | FK | No | — | Clinic ownership boundary; assigned from trusted tenant context. |
| patient_id | bigint | FK | No | — | Related record identifier; see FK register for enforced references. |
| appointment_id | bigint | FK | No | — | Related record identifier; see FK register for enforced references. |
| practitioner_id | bigint | FK | No | — | Related record identifier; see FK register for enforced references. |
| content | text | — | No | — | Protected application field; inspect model cast/service for hash or encryption handling. |
| note_version | integer | — | No | — | note version; physical definition preserved from PostgreSQL. |
| signed_at | timestamp with time zone | — | Yes | — | signed at; physical definition preserved from PostgreSQL. |
| supersedes_id | bigint | FK | Yes | — | Related record identifier; see FK register for enforced references. |
| created_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |
| updated_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |

## consent_records

Purpose-specific consent and withdrawal evidence.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| id | bigint | PK | No | nextval('public.consent_records_id_seq'::regclass) | Record primary identifier. |
| tenant_id | bigint | FK | No | — | Clinic ownership boundary; assigned from trusted tenant context. |
| patient_id | bigint | FK | No | — | Related record identifier; see FK register for enforced references. |
| user_id | bigint | FK | Yes | — | Related record identifier; see FK register for enforced references. |
| purpose | character varying(255) | — | No | — | purpose; physical definition preserved from PostgreSQL. |
| notice_version | character varying(255) | — | No | — | notice version; physical definition preserved from PostgreSQL. |
| granted_at | timestamp with time zone | — | No | — | granted at; physical definition preserved from PostgreSQL. |
| withdrawn_at | timestamp with time zone | — | Yes | — | withdrawn at; physical definition preserved from PostgreSQL. |
| capture_method | character varying(255) | — | No | — | capture method; physical definition preserved from PostgreSQL. |
| created_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |
| updated_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |

## failed_jobs

Framework failed queue work.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| id | bigint | PK | No | nextval('public.failed_jobs_id_seq'::regclass) | Record primary identifier. |
| uuid | character varying(255) | — | No | — | uuid; physical definition preserved from PostgreSQL. |
| connection | character varying(255) | — | No | — | connection; physical definition preserved from PostgreSQL. |
| queue | character varying(255) | — | No | — | queue; physical definition preserved from PostgreSQL. |
| payload | text | — | No | — | payload; physical definition preserved from PostgreSQL. |
| exception | text | — | No | — | exception; physical definition preserved from PostgreSQL. |
| failed_at | timestamp(0) without time zone | — | No | CURRENT_TIMESTAMP | failed at; physical definition preserved from PostgreSQL. |

## idempotency_requests

Actor-scoped booking replay protection.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| id | bigint | PK | No | nextval('public.idempotency_requests_id_seq'::regclass) | Record primary identifier. |
| tenant_id | bigint | FK | No | — | Clinic ownership boundary; assigned from trusted tenant context. |
| actor_id | bigint | FK | No | — | Related record identifier; see FK register for enforced references. |
| operation | character varying(255) | — | No | — | operation; physical definition preserved from PostgreSQL. |
| key_hash | character varying(64) | — | No | — | key hash; physical definition preserved from PostgreSQL. |
| request_hash | character varying(64) | — | No | — | request hash; physical definition preserved from PostgreSQL. |
| result_resource_id | bigint | FK | No | — | Related record identifier; see FK register for enforced references. |
| expires_at | timestamp with time zone | — | No | — | expires at; physical definition preserved from PostgreSQL. |
| created_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |
| updated_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |

## intake_submissions

Versioned encrypted patient intake.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| id | bigint | PK | No | nextval('public.intake_submissions_id_seq'::regclass) | Record primary identifier. |
| tenant_id | bigint | FK | No | — | Clinic ownership boundary; assigned from trusted tenant context. |
| patient_id | bigint | FK | No | — | Related record identifier; see FK register for enforced references. |
| appointment_id | bigint | FK | Yes | — | Related record identifier; see FK register for enforced references. |
| form_version | character varying(255) | — | No | — | form version; physical definition preserved from PostgreSQL. |
| answers | text | — | No | — | Protected application field; inspect model cast/service for hash or encryption handling. |
| submitted_at | timestamp with time zone | — | No | — | submitted at; physical definition preserved from PostgreSQL. |
| author_id | bigint | FK | No | — | Related record identifier; see FK register for enforced references. |
| supersedes_id | bigint | FK | Yes | — | Related record identifier; see FK register for enforced references. |
| created_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |
| updated_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |

## job_batches

Framework queue batch metadata.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| id | character varying(255) | PK | No | — | Record primary identifier. |
| name | character varying(255) | — | No | — | name; physical definition preserved from PostgreSQL. |
| total_jobs | integer | — | No | — | total jobs; physical definition preserved from PostgreSQL. |
| pending_jobs | integer | — | No | — | pending jobs; physical definition preserved from PostgreSQL. |
| failed_jobs | integer | — | No | — | failed jobs; physical definition preserved from PostgreSQL. |
| failed_job_ids | text | — | No | — | failed job ids; physical definition preserved from PostgreSQL. |
| options | text | — | Yes | — | options; physical definition preserved from PostgreSQL. |
| cancelled_at | integer | — | Yes | — | cancelled at; physical definition preserved from PostgreSQL. |
| created_at | integer | — | No | — | Framework record timestamp. |
| finished_at | integer | — | Yes | — | finished at; physical definition preserved from PostgreSQL. |

## jobs

Framework database queue work.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| id | bigint | PK | No | nextval('public.jobs_id_seq'::regclass) | Record primary identifier. |
| queue | character varying(255) | — | No | — | queue; physical definition preserved from PostgreSQL. |
| payload | text | — | No | — | payload; physical definition preserved from PostgreSQL. |
| attempts | smallint | — | No | — | attempts; physical definition preserved from PostgreSQL. |
| reserved_at | integer | — | Yes | — | reserved at; physical definition preserved from PostgreSQL. |
| available_at | integer | — | No | — | available at; physical definition preserved from PostgreSQL. |
| created_at | integer | — | No | — | Framework record timestamp. |

## migrations

Applied Laravel migration ledger.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| id | integer | PK | No | nextval('public.migrations_id_seq'::regclass) | Record primary identifier. |
| migration | character varying(255) | — | No | — | migration; physical definition preserved from PostgreSQL. |
| batch | integer | — | No | — | batch; physical definition preserved from PostgreSQL. |

## notification_deliveries

Delivery attempts and status for outbox work.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| id | bigint | PK | No | nextval('public.notification_deliveries_id_seq'::regclass) | Record primary identifier. |
| tenant_id | bigint | FK | No | — | Clinic ownership boundary; assigned from trusted tenant context. |
| outbox_message_id | bigint | FK | No | — | Related record identifier; see FK register for enforced references. |
| channel | character varying(32) | — | No | 'email'::character varying | channel; physical definition preserved from PostgreSQL. |
| state | character varying(32) | — | No | 'pending'::character varying | state; physical definition preserved from PostgreSQL. |
| attempts | integer | — | No | 0 | attempts; physical definition preserved from PostgreSQL. |
| sent_at | timestamp with time zone | — | Yes | — | sent at; physical definition preserved from PostgreSQL. |
| last_error_code | character varying(255) | — | Yes | — | last error code; physical definition preserved from PostgreSQL. |
| created_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |
| updated_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |

## operatories

Clinic chair inventory.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| id | bigint | PK | No | nextval('public.operatories_id_seq'::regclass) | Record primary identifier. |
| tenant_id | bigint | FK | No | — | Clinic ownership boundary; assigned from trusted tenant context. |
| name | character varying(255) | — | No | — | name; physical definition preserved from PostgreSQL. |
| active | boolean | — | No | true | active; physical definition preserved from PostgreSQL. |
| created_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |
| updated_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |

## operatory_services

Chair/service compatibility junction.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| id | bigint | PK | No | nextval('public.operatory_services_id_seq'::regclass) | Record primary identifier. |
| tenant_id | bigint | FK | No | — | Clinic ownership boundary; assigned from trusted tenant context. |
| operatory_id | bigint | FK | No | — | Related record identifier; see FK register for enforced references. |
| service_id | bigint | FK | No | — | Related record identifier; see FK register for enforced references. |
| created_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |
| updated_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |

## outbox_messages

Transactional appointment notification work.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| id | bigint | PK | No | nextval('public.outbox_messages_id_seq'::regclass) | Record primary identifier. |
| tenant_id | bigint | FK | No | — | Clinic ownership boundary; assigned from trusted tenant context. |
| event_type | character varying(255) | — | No | — | event type; physical definition preserved from PostgreSQL. |
| resource_id | bigint | FK | No | — | Related record identifier; see FK register for enforced references. |
| version | integer | — | No | — | version; physical definition preserved from PostgreSQL. |
| deduplication_key | character varying(255) | — | No | — | deduplication key; physical definition preserved from PostgreSQL. |
| state | character varying(32) | — | No | 'pending'::character varying | state; physical definition preserved from PostgreSQL. |
| attempts | integer | — | No | 0 | attempts; physical definition preserved from PostgreSQL. |
| available_at | timestamp with time zone | — | No | — | available at; physical definition preserved from PostgreSQL. |
| processed_at | timestamp with time zone | — | Yes | — | processed at; physical definition preserved from PostgreSQL. |
| last_error_code | character varying(255) | — | Yes | — | last error code; physical definition preserved from PostgreSQL. |
| created_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |
| updated_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |

## password_reset_tokens

Tenant-bound hashed password reset credentials.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| tenant_id | bigint | PK, FK | No | — | Clinic ownership boundary; assigned from trusted tenant context. |
| email | character varying(255) | PK | No | — | email; physical definition preserved from PostgreSQL. |
| token | character varying(255) | — | No | — | Protected application field; inspect model cast/service for hash or encryption handling. |
| created_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |

## patient_profiles

Patient contact details; account link is optional for walk-ins.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| id | bigint | PK | No | nextval('public.patient_profiles_id_seq'::regclass) | Record primary identifier. |
| tenant_id | bigint | FK | No | — | Clinic ownership boundary; assigned from trusted tenant context. |
| user_id | bigint | FK | Yes | — | Related record identifier; see FK register for enforced references. |
| first_name | character varying(255) | — | No | — | first name; physical definition preserved from PostgreSQL. |
| last_name | character varying(255) | — | No | — | last name; physical definition preserved from PostgreSQL. |
| email | character varying(255) | — | Yes | — | email; physical definition preserved from PostgreSQL. |
| phone | character varying(255) | — | Yes | — | phone; physical definition preserved from PostgreSQL. |
| date_of_birth | date | — | Yes | — | date of birth; physical definition preserved from PostgreSQL. |
| status | character varying(32) | — | No | 'active'::character varying | status; physical definition preserved from PostgreSQL. |
| created_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |
| updated_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |

## platform_admins

Separate platform credentials and MFA material.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| id | bigint | PK | No | nextval('public.platform_admins_id_seq'::regclass) | Record primary identifier. |
| name | character varying(255) | — | No | — | name; physical definition preserved from PostgreSQL. |
| email | character varying(255) | — | No | — | email; physical definition preserved from PostgreSQL. |
| password | character varying(255) | — | No | — | One-way password hash; never plaintext. |
| status | character varying(32) | — | No | 'active'::character varying | status; physical definition preserved from PostgreSQL. |
| two_factor_secret | text | — | Yes | — | Protected application field; inspect model cast/service for hash or encryption handling. |
| two_factor_recovery_codes | text | — | Yes | — | two factor recovery codes; physical definition preserved from PostgreSQL. |
| two_factor_confirmed_at | timestamp with time zone | — | Yes | — | two factor confirmed at; physical definition preserved from PostgreSQL. |
| remember_token | character varying(100) | — | Yes | — | Protected application field; inspect model cast/service for hash or encryption handling. |
| created_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |
| updated_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |

## platform_audit_events

Append-only platform administration actions.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| id | bigint | PK | No | nextval('public.platform_audit_events_id_seq'::regclass) | Record primary identifier. |
| platform_admin_id | bigint | FK | No | — | Related record identifier; see FK register for enforced references. |
| tenant_id | bigint | FK | No | — | Clinic ownership boundary; assigned from trusted tenant context. |
| action | character varying(64) | — | No | — | action; physical definition preserved from PostgreSQL. |
| previous_status | character varying(32) | — | No | — | previous status; physical definition preserved from PostgreSQL. |
| new_status | character varying(32) | — | No | — | new status; physical definition preserved from PostgreSQL. |
| occurred_at | timestamp(0) with time zone | — | No | — | occurred at; physical definition preserved from PostgreSQL. |

## practitioner_breaks

Recurring practitioner unavailable windows.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| id | bigint | PK | No | nextval('public.practitioner_breaks_id_seq'::regclass) | Record primary identifier. |
| tenant_id | bigint | FK | No | — | Clinic ownership boundary; assigned from trusted tenant context. |
| practitioner_id | bigint | FK | No | — | Related record identifier; see FK register for enforced references. |
| weekday | smallint | — | No | — | weekday; physical definition preserved from PostgreSQL. |
| local_start | time without time zone | — | No | — | local start; physical definition preserved from PostgreSQL. |
| local_end | time without time zone | — | No | — | local end; physical definition preserved from PostgreSQL. |
| effective_from | date | — | Yes | — | effective from; physical definition preserved from PostgreSQL. |
| effective_to | date | — | Yes | — | effective to; physical definition preserved from PostgreSQL. |
| created_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |
| updated_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |

## practitioner_services

Practitioner/service eligibility junction.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| id | bigint | PK | No | nextval('public.practitioner_services_id_seq'::regclass) | Record primary identifier. |
| tenant_id | bigint | FK | No | — | Clinic ownership boundary; assigned from trusted tenant context. |
| practitioner_id | bigint | FK | No | — | Related record identifier; see FK register for enforced references. |
| service_id | bigint | FK | No | — | Related record identifier; see FK register for enforced references. |
| created_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |
| updated_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |

## practitioner_shifts

Recurring practitioner working windows.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| id | bigint | PK | No | nextval('public.practitioner_shifts_id_seq'::regclass) | Record primary identifier. |
| tenant_id | bigint | FK | No | — | Clinic ownership boundary; assigned from trusted tenant context. |
| practitioner_id | bigint | FK | No | — | Related record identifier; see FK register for enforced references. |
| weekday | smallint | — | No | — | weekday; physical definition preserved from PostgreSQL. |
| local_start | time without time zone | — | No | — | local start; physical definition preserved from PostgreSQL. |
| local_end | time without time zone | — | No | — | local end; physical definition preserved from PostgreSQL. |
| effective_from | date | — | Yes | — | effective from; physical definition preserved from PostgreSQL. |
| effective_to | date | — | Yes | — | effective to; physical definition preserved from PostgreSQL. |
| created_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |
| updated_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |

## practitioners

Clinical practitioners linked to clinic users.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| id | bigint | PK | No | nextval('public.practitioners_id_seq'::regclass) | Record primary identifier. |
| tenant_id | bigint | FK | No | — | Clinic ownership boundary; assigned from trusted tenant context. |
| user_id | bigint | FK | No | — | Related record identifier; see FK register for enforced references. |
| display_title | character varying(255) | — | Yes | — | display title; physical definition preserved from PostgreSQL. |
| specialization | character varying(255) | — | Yes | — | specialization; physical definition preserved from PostgreSQL. |
| active | boolean | — | No | true | active; physical definition preserved from PostgreSQL. |
| created_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |
| updated_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |

## schedule_blocks

Dated clinic closures, practitioner leave or chair maintenance.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| id | bigint | PK | No | nextval('public.schedule_blocks_id_seq'::regclass) | Record primary identifier. |
| tenant_id | bigint | FK | No | — | Clinic ownership boundary; assigned from trusted tenant context. |
| resource_type | character varying(32) | — | No | — | resource type; physical definition preserved from PostgreSQL. |
| practitioner_id | bigint | FK | Yes | — | Related record identifier; see FK register for enforced references. |
| operatory_id | bigint | FK | Yes | — | Related record identifier; see FK register for enforced references. |
| starts_at | timestamp with time zone | — | No | — | starts at; physical definition preserved from PostgreSQL. |
| ends_at | timestamp with time zone | — | No | — | ends at; physical definition preserved from PostgreSQL. |
| reason | character varying(255) | — | Yes | — | reason; physical definition preserved from PostgreSQL. |
| created_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |
| updated_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |

## services

Clinic service duration, buffer, currency and fee configuration.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| id | bigint | PK | No | nextval('public.services_id_seq'::regclass) | Record primary identifier. |
| tenant_id | bigint | FK | No | — | Clinic ownership boundary; assigned from trusted tenant context. |
| name | character varying(255) | — | No | — | name; physical definition preserved from PostgreSQL. |
| category | character varying(255) | — | No | — | category; physical definition preserved from PostgreSQL. |
| description | text | — | Yes | — | description; physical definition preserved from PostgreSQL. |
| duration_minutes | smallint | — | No | — | duration minutes; physical definition preserved from PostgreSQL. |
| buffer_before_minutes | smallint | — | No | 0 | buffer before minutes; physical definition preserved from PostgreSQL. |
| buffer_after_minutes | smallint | — | No | 15 | buffer after minutes; physical definition preserved from PostgreSQL. |
| price_minor | integer | — | No | — | price minor; physical definition preserved from PostgreSQL. |
| currency | character(3) | — | No | 'PHP'::bpchar | currency; physical definition preserved from PostgreSQL. |
| active | boolean | — | No | true | active; physical definition preserved from PostgreSQL. |
| created_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |
| updated_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |

## sessions

Framework database session storage; local session driver is file.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| id | character varying(255) | PK | No | — | Record primary identifier. |
| tenant_id | bigint | — | Yes | — | Clinic ownership boundary; assigned from trusted tenant context. |
| user_id | bigint | — | Yes | — | Related record identifier; see FK register for enforced references. |
| ip_address | character varying(45) | — | Yes | — | ip address; physical definition preserved from PostgreSQL. |
| user_agent | text | — | Yes | — | user agent; physical definition preserved from PostgreSQL. |
| payload | text | — | No | — | payload; physical definition preserved from PostgreSQL. |
| last_activity | integer | — | No | — | last activity; physical definition preserved from PostgreSQL. |

## shopify_connections

Clinic shop domain, encrypted Storefront credentials and connection generation.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| id | bigint | PK | No | nextval('public.shopify_connections_id_seq'::regclass) | Record primary identifier. |
| tenant_id | bigint | FK | No | — | Clinic ownership boundary; assigned from trusted tenant context. |
| shop_host | character varying(255) | — | No | — | shop host; physical definition preserved from PostgreSQL. |
| shop_id | character varying(255) | — | No | — | Related record identifier; see FK register for enforced references. |
| primary_domain | character varying(255) | — | Yes | — | primary domain; physical definition preserved from PostgreSQL. |
| private_token | text | — | Yes | — | Protected application field; inspect model cast/service for hash or encryption handling. |
| status | character varying(255) | — | No | 'active'::character varying | status; physical definition preserved from PostgreSQL. |
| generation | integer | — | No | 1 | generation; physical definition preserved from PostgreSQL. |
| verified_at | timestamp(0) with time zone | — | Yes | — | verified at; physical definition preserved from PostgreSQL. |
| created_at | timestamp(0) with time zone | — | Yes | — | Framework record timestamp. |
| updated_at | timestamp(0) with time zone | — | Yes | — | Framework record timestamp. |

## shopping_carts

Clinic/session-bound provider cart references.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| id | bigint | PK | No | nextval('public.shopping_carts_id_seq'::regclass) | Record primary identifier. |
| tenant_id | bigint | FK | No | — | Clinic ownership boundary; assigned from trusted tenant context. |
| connection_generation | integer | — | No | — | connection generation; physical definition preserved from PostgreSQL. |
| session_hash | character varying(64) | — | No | — | session hash; physical definition preserved from PostgreSQL. |
| shopify_cart_id | text | — | No | — | Related record identifier; see FK register for enforced references. |
| created_at | timestamp(0) with time zone | — | Yes | — | Framework record timestamp. |
| updated_at | timestamp(0) with time zone | — | Yes | — | Framework record timestamp. |

## staff_invitations

Expiring staff invitation hashes and proposed roles.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| id | bigint | PK | No | nextval('public.staff_invitations_id_seq'::regclass) | Record primary identifier. |
| tenant_id | bigint | FK | No | — | Clinic ownership boundary; assigned from trusted tenant context. |
| email | character varying(255) | — | No | — | email; physical definition preserved from PostgreSQL. |
| roles | jsonb | — | No | — | roles; physical definition preserved from PostgreSQL. |
| token_hash | character varying(64) | — | No | — | Protected application field; inspect model cast/service for hash or encryption handling. |
| expires_at | timestamp with time zone | — | No | — | expires at; physical definition preserved from PostgreSQL. |
| accepted_at | timestamp with time zone | — | Yes | — | accepted at; physical definition preserved from PostgreSQL. |
| invited_by | bigint | FK | Yes | — | invited by; physical definition preserved from PostgreSQL. |
| created_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |
| updated_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |

## subscription_items

Provider price/product items for a subscription.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| id | bigint | PK | No | nextval('public.subscription_items_id_seq'::regclass) | Record primary identifier. |
| subscription_id | bigint | FK | No | — | Related record identifier; see FK register for enforced references. |
| stripe_id | character varying(255) | — | No | — | Related record identifier; see FK register for enforced references. |
| stripe_product | character varying(255) | — | No | — | stripe product; physical definition preserved from PostgreSQL. |
| stripe_price | character varying(255) | — | No | — | stripe price; physical definition preserved from PostgreSQL. |
| meter_id | character varying(255) | — | Yes | — | Related record identifier; see FK register for enforced references. |
| meter_event_name | character varying(255) | — | Yes | — | meter event name; physical definition preserved from PostgreSQL. |
| quantity | integer | — | Yes | — | quantity; physical definition preserved from PostgreSQL. |
| created_at | timestamp(0) with time zone | — | Yes | — | Framework record timestamp. |
| updated_at | timestamp(0) with time zone | — | Yes | — | Framework record timestamp. |

## subscriptions

Tenant-billable Cashier subscription state.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| id | bigint | PK | No | nextval('public.subscriptions_id_seq'::regclass) | Record primary identifier. |
| tenant_id | bigint | FK | No | — | Clinic ownership boundary; assigned from trusted tenant context. |
| type | character varying(255) | — | No | — | type; physical definition preserved from PostgreSQL. |
| stripe_id | character varying(255) | — | No | — | Related record identifier; see FK register for enforced references. |
| stripe_status | character varying(255) | — | No | — | stripe status; physical definition preserved from PostgreSQL. |
| stripe_price | character varying(255) | — | Yes | — | stripe price; physical definition preserved from PostgreSQL. |
| quantity | integer | — | Yes | — | quantity; physical definition preserved from PostgreSQL. |
| trial_ends_at | timestamp(0) with time zone | — | Yes | — | trial ends at; physical definition preserved from PostgreSQL. |
| ends_at | timestamp(0) with time zone | — | Yes | — | ends at; physical definition preserved from PostgreSQL. |
| created_at | timestamp(0) with time zone | — | Yes | — | Framework record timestamp. |
| updated_at | timestamp(0) with time zone | — | Yes | — | Framework record timestamp. |

## tenants

Clinic registry, subdomain, timezone, booking policy and billing customer identifiers.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| id | bigint | PK | No | nextval('public.tenants_id_seq'::regclass) | Record primary identifier. |
| name | character varying(255) | — | No | — | name; physical definition preserved from PostgreSQL. |
| subdomain | character varying(63) | — | No | — | subdomain; physical definition preserved from PostgreSQL. |
| timezone | character varying(255) | — | No | 'Asia/Manila'::character varying | timezone; physical definition preserved from PostgreSQL. |
| currency | character(3) | — | No | 'PHP'::bpchar | currency; physical definition preserved from PostgreSQL. |
| lifecycle_status | character varying(32) | — | No | 'active'::character varying | lifecycle status; physical definition preserved from PostgreSQL. |
| slot_interval_minutes | smallint | — | No | 15 | slot interval minutes; physical definition preserved from PostgreSQL. |
| booking_horizon_days | smallint | — | No | 90 | booking horizon days; physical definition preserved from PostgreSQL. |
| minimum_lead_minutes | integer | — | No | 120 | minimum lead minutes; physical definition preserved from PostgreSQL. |
| cancellation_window_hours | smallint | — | No | 24 | cancellation window hours; physical definition preserved from PostgreSQL. |
| branding | jsonb | — | No | '{}'::jsonb | branding; physical definition preserved from PostgreSQL. |
| stripe_id | character varying(255) | — | Yes | — | Related record identifier; see FK register for enforced references. |
| pm_type | character varying(255) | — | Yes | — | pm type; physical definition preserved from PostgreSQL. |
| pm_last_four | character varying(4) | — | Yes | — | pm last four; physical definition preserved from PostgreSQL. |
| trial_ends_at | timestamp with time zone | — | Yes | — | trial ends at; physical definition preserved from PostgreSQL. |
| billing_status | character varying(32) | — | No | 'pending'::character varying | billing status; physical definition preserved from PostgreSQL. |
| created_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |
| updated_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |

## user_roles

Allowlisted tenant-local roles; one user may hold several roles.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| id | bigint | PK | No | nextval('public.user_roles_id_seq'::regclass) | Record primary identifier. |
| tenant_id | bigint | FK | No | — | Clinic ownership boundary; assigned from trusted tenant context. |
| user_id | bigint | FK | No | — | Related record identifier; see FK register for enforced references. |
| role | character varying(32) | — | No | — | role; physical definition preserved from PostgreSQL. |
| created_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |
| updated_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |

## users

Tenant-local credentials and identity; password is a Laravel hash.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| id | bigint | PK | No | nextval('public.users_id_seq'::regclass) | Record primary identifier. |
| tenant_id | bigint | FK | No | — | Clinic ownership boundary; assigned from trusted tenant context. |
| name | character varying(255) | — | No | — | name; physical definition preserved from PostgreSQL. |
| email | character varying(255) | — | No | — | email; physical definition preserved from PostgreSQL. |
| password | character varying(255) | — | No | — | One-way password hash; never plaintext. |
| status | character varying(32) | — | No | 'active'::character varying | status; physical definition preserved from PostgreSQL. |
| email_verified_at | timestamp with time zone | — | Yes | — | email verified at; physical definition preserved from PostgreSQL. |
| two_factor_secret | text | — | Yes | — | Protected application field; inspect model cast/service for hash or encryption handling. |
| two_factor_recovery_codes | text | — | Yes | — | two factor recovery codes; physical definition preserved from PostgreSQL. |
| two_factor_confirmed_at | timestamp with time zone | — | Yes | — | two factor confirmed at; physical definition preserved from PostgreSQL. |
| remember_token | character varying(100) | — | Yes | — | Protected application field; inspect model cast/service for hash or encryption handling. |
| created_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |
| updated_at | timestamp with time zone | — | Yes | — | Framework record timestamp. |

## webhook_events

Durable verified provider event inbox.

| Field | PostgreSQL type (size included) | Key | Nullable | Default | Description |
| --- | --- | --- | --- | --- | --- |
| id | bigint | PK | No | nextval('public.webhook_events_id_seq'::regclass) | Record primary identifier. |
| provider | character varying(255) | — | No | — | provider; physical definition preserved from PostgreSQL. |
| provider_event_id | character varying(255) | — | No | — | Related record identifier; see FK register for enforced references. |
| event_type | character varying(255) | — | No | — | event type; physical definition preserved from PostgreSQL. |
| tenant_id | bigint | FK | Yes | — | Clinic ownership boundary; assigned from trusted tenant context. |
| raw_payload | text | — | No | — | raw payload; physical definition preserved from PostgreSQL. |
| state | character varying(255) | — | No | 'pending'::character varying | state; physical definition preserved from PostgreSQL. |
| attempts | integer | — | No | 0 | attempts; physical definition preserved from PostgreSQL. |
| last_error_code | character varying(255) | — | Yes | — | last error code; physical definition preserved from PostgreSQL. |
| processed_at | timestamp(0) with time zone | — | Yes | — | processed at; physical definition preserved from PostgreSQL. |
| created_at | timestamp(0) with time zone | — | Yes | — | Framework record timestamp. |
| updated_at | timestamp(0) with time zone | — | Yes | — | Framework record timestamp. |

## Enforced foreign keys

| Constraint | Child columns | Parent key | Delete behavior |
| --- | --- | --- | --- |
| appointment_events_tenant_id_actor_id_fkey | appointment_events (tenant_id, actor_id) | users (tenant_id, id) | ON DELETE RESTRICT |
| appointment_events_tenant_id_appointment_id_fkey | appointment_events (tenant_id, appointment_id) | appointments (tenant_id, id) | ON DELETE RESTRICT |
| appointment_events_tenant_id_fkey | appointment_events (tenant_id) | tenants (id) | NO ACTION |
| appointments_tenant_id_fkey | appointments (tenant_id) | tenants (id) | NO ACTION |
| appointments_tenant_id_operatory_id_fkey | appointments (tenant_id, operatory_id) | operatories (tenant_id, id) | ON DELETE RESTRICT |
| appointments_tenant_id_patient_id_fkey | appointments (tenant_id, patient_id) | patient_profiles (tenant_id, id) | ON DELETE RESTRICT |
| appointments_tenant_id_practitioner_id_fkey | appointments (tenant_id, practitioner_id) | practitioners (tenant_id, id) | ON DELETE RESTRICT |
| appointments_tenant_id_service_id_fkey | appointments (tenant_id, service_id) | services (tenant_id, id) | ON DELETE RESTRICT |
| audit_events_tenant_id_actor_id_fkey | audit_events (tenant_id, actor_id) | users (tenant_id, id) | ON DELETE RESTRICT |
| audit_events_tenant_id_fkey | audit_events (tenant_id) | tenants (id) | NO ACTION |
| billing_checkout_sessions_tenant_id_foreign | billing_checkout_sessions (tenant_id) | tenants (id) | ON DELETE RESTRICT |
| billing_entitlements_tenant_id_foreign | billing_entitlements (tenant_id) | tenants (id) | ON DELETE RESTRICT |
| care_assignments_tenant_id_appointment_id_patient_id_fkey | care_assignments (tenant_id, appointment_id, patient_id) | appointments (tenant_id, id, patient_id) | ON DELETE RESTRICT |
| care_assignments_tenant_id_fkey | care_assignments (tenant_id) | tenants (id) | NO ACTION |
| care_assignments_tenant_id_granted_by_fkey | care_assignments (tenant_id, granted_by) | users (tenant_id, id) | ON DELETE RESTRICT |
| care_assignments_tenant_id_patient_id_fkey | care_assignments (tenant_id, patient_id) | patient_profiles (tenant_id, id) | ON DELETE RESTRICT |
| care_assignments_tenant_id_practitioner_id_fkey | care_assignments (tenant_id, practitioner_id) | practitioners (tenant_id, id) | ON DELETE RESTRICT |
| clinic_hours_tenant_id_fkey | clinic_hours (tenant_id) | tenants (id) | NO ACTION |
| clinical_notes_tenant_id_appointment_id_patient_id_fkey | clinical_notes (tenant_id, appointment_id, patient_id) | appointments (tenant_id, id, patient_id) | ON DELETE RESTRICT |
| clinical_notes_tenant_id_fkey | clinical_notes (tenant_id) | tenants (id) | NO ACTION |
| clinical_notes_tenant_id_patient_id_fkey | clinical_notes (tenant_id, patient_id) | patient_profiles (tenant_id, id) | ON DELETE RESTRICT |
| clinical_notes_tenant_id_practitioner_id_fkey | clinical_notes (tenant_id, practitioner_id) | practitioners (tenant_id, id) | ON DELETE RESTRICT |
| clinical_notes_tenant_id_supersedes_id_patient_id_fkey | clinical_notes (tenant_id, supersedes_id, patient_id) | clinical_notes (tenant_id, id, patient_id) | ON DELETE RESTRICT |
| consent_records_tenant_id_fkey | consent_records (tenant_id) | tenants (id) | NO ACTION |
| consent_records_tenant_id_patient_id_fkey | consent_records (tenant_id, patient_id) | patient_profiles (tenant_id, id) | ON DELETE RESTRICT |
| consent_records_tenant_id_user_id_fkey | consent_records (tenant_id, user_id) | users (tenant_id, id) | ON DELETE RESTRICT |
| idempotency_requests_tenant_id_actor_id_fkey | idempotency_requests (tenant_id, actor_id) | users (tenant_id, id) | ON DELETE RESTRICT |
| idempotency_requests_tenant_id_fkey | idempotency_requests (tenant_id) | tenants (id) | NO ACTION |
| idempotency_requests_tenant_id_result_resource_id_fkey | idempotency_requests (tenant_id, result_resource_id) | appointments (tenant_id, id) | ON DELETE RESTRICT |
| intake_submissions_tenant_id_appointment_id_patient_id_fkey | intake_submissions (tenant_id, appointment_id, patient_id) | appointments (tenant_id, id, patient_id) | ON DELETE RESTRICT |
| intake_submissions_tenant_id_author_id_fkey | intake_submissions (tenant_id, author_id) | users (tenant_id, id) | ON DELETE RESTRICT |
| intake_submissions_tenant_id_fkey | intake_submissions (tenant_id) | tenants (id) | NO ACTION |
| intake_submissions_tenant_id_patient_id_fkey | intake_submissions (tenant_id, patient_id) | patient_profiles (tenant_id, id) | ON DELETE RESTRICT |
| intake_submissions_tenant_id_supersedes_id_patient_id_fkey | intake_submissions (tenant_id, supersedes_id, patient_id) | intake_submissions (tenant_id, id, patient_id) | ON DELETE RESTRICT |
| notification_deliveries_tenant_id_fkey | notification_deliveries (tenant_id) | tenants (id) | NO ACTION |
| notification_deliveries_tenant_id_outbox_message_id_fkey | notification_deliveries (tenant_id, outbox_message_id) | outbox_messages (tenant_id, id) | ON DELETE RESTRICT |
| operatories_tenant_id_fkey | operatories (tenant_id) | tenants (id) | NO ACTION |
| operatory_services_tenant_id_fkey | operatory_services (tenant_id) | tenants (id) | NO ACTION |
| operatory_services_tenant_id_operatory_id_fkey | operatory_services (tenant_id, operatory_id) | operatories (tenant_id, id) | ON DELETE RESTRICT |
| operatory_services_tenant_id_service_id_fkey | operatory_services (tenant_id, service_id) | services (tenant_id, id) | ON DELETE RESTRICT |
| outbox_messages_tenant_id_fkey | outbox_messages (tenant_id) | tenants (id) | NO ACTION |
| outbox_messages_tenant_id_resource_id_fkey | outbox_messages (tenant_id, resource_id) | appointments (tenant_id, id) | ON DELETE RESTRICT |
| password_reset_tokens_tenant_id_fkey | password_reset_tokens (tenant_id) | tenants (id) | NO ACTION |
| patient_profiles_tenant_id_fkey | patient_profiles (tenant_id) | tenants (id) | NO ACTION |
| patient_profiles_tenant_id_user_id_fkey | patient_profiles (tenant_id, user_id) | users (tenant_id, id) | ON DELETE RESTRICT |
| platform_audit_events_platform_admin_id_foreign | platform_audit_events (platform_admin_id) | platform_admins (id) | ON DELETE RESTRICT |
| platform_audit_events_tenant_id_foreign | platform_audit_events (tenant_id) | tenants (id) | ON DELETE RESTRICT |
| practitioner_breaks_tenant_id_fkey | practitioner_breaks (tenant_id) | tenants (id) | NO ACTION |
| practitioner_breaks_tenant_id_practitioner_id_fkey | practitioner_breaks (tenant_id, practitioner_id) | practitioners (tenant_id, id) | ON DELETE RESTRICT |
| practitioner_services_tenant_id_fkey | practitioner_services (tenant_id) | tenants (id) | NO ACTION |
| practitioner_services_tenant_id_practitioner_id_fkey | practitioner_services (tenant_id, practitioner_id) | practitioners (tenant_id, id) | ON DELETE RESTRICT |
| practitioner_services_tenant_id_service_id_fkey | practitioner_services (tenant_id, service_id) | services (tenant_id, id) | ON DELETE RESTRICT |
| practitioner_shifts_tenant_id_fkey | practitioner_shifts (tenant_id) | tenants (id) | NO ACTION |
| practitioner_shifts_tenant_id_practitioner_id_fkey | practitioner_shifts (tenant_id, practitioner_id) | practitioners (tenant_id, id) | ON DELETE RESTRICT |
| practitioners_tenant_id_fkey | practitioners (tenant_id) | tenants (id) | NO ACTION |
| practitioners_tenant_id_user_id_fkey | practitioners (tenant_id, user_id) | users (tenant_id, id) | ON DELETE RESTRICT |
| schedule_blocks_tenant_id_fkey | schedule_blocks (tenant_id) | tenants (id) | NO ACTION |
| schedule_blocks_tenant_id_operatory_id_fkey | schedule_blocks (tenant_id, operatory_id) | operatories (tenant_id, id) | ON DELETE RESTRICT |
| schedule_blocks_tenant_id_practitioner_id_fkey | schedule_blocks (tenant_id, practitioner_id) | practitioners (tenant_id, id) | ON DELETE RESTRICT |
| services_tenant_id_fkey | services (tenant_id) | tenants (id) | NO ACTION |
| shopify_connections_tenant_id_foreign | shopify_connections (tenant_id) | tenants (id) | ON DELETE RESTRICT |
| shopping_carts_tenant_id_foreign | shopping_carts (tenant_id) | tenants (id) | ON DELETE RESTRICT |
| staff_invitations_tenant_id_fkey | staff_invitations (tenant_id) | tenants (id) | NO ACTION |
| staff_invitations_tenant_id_invited_by_fkey | staff_invitations (tenant_id, invited_by) | users (tenant_id, id) | ON DELETE RESTRICT |
| subscription_items_subscription_id_foreign | subscription_items (subscription_id) | subscriptions (id) | ON DELETE RESTRICT |
| subscriptions_tenant_id_foreign | subscriptions (tenant_id) | tenants (id) | ON DELETE RESTRICT |
| user_roles_tenant_id_fkey | user_roles (tenant_id) | tenants (id) | NO ACTION |
| user_roles_tenant_id_user_id_fkey | user_roles (tenant_id, user_id) | users (tenant_id, id) | ON DELETE RESTRICT |
| users_tenant_id_fkey | users (tenant_id) | tenants (id) | NO ACTION |
| webhook_events_tenant_id_foreign | webhook_events (tenant_id) | tenants (id) | ON DELETE RESTRICT |
