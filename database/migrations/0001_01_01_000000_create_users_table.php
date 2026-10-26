<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $tables = [];

    private function table(string $name, string $columns): void
    {
        DB::statement("CREATE TABLE {$name} (id BIGSERIAL PRIMARY KEY, tenant_id BIGINT NOT NULL REFERENCES tenants(id), {$columns}, created_at TIMESTAMPTZ, updated_at TIMESTAMPTZ, UNIQUE(tenant_id,id))");
        $this->tables[] = $name;
    }

    private function fk(string $table, string $column, string $target): void
    {
        DB::statement("ALTER TABLE {$table} ADD FOREIGN KEY (tenant_id,{$column}) REFERENCES {$target}(tenant_id,id) ON DELETE RESTRICT");
    }

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('PostgreSQL with btree_gist is required.');
        }
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
        DB::unprepared(<<<'SQL'
CREATE TABLE tenants (
 id BIGSERIAL PRIMARY KEY, name VARCHAR(255) NOT NULL, subdomain VARCHAR(63) NOT NULL UNIQUE,
 timezone VARCHAR(255) NOT NULL DEFAULT 'Asia/Manila', currency CHAR(3) NOT NULL DEFAULT 'PHP',
 lifecycle_status VARCHAR(32) NOT NULL DEFAULT 'active' CHECK(lifecycle_status IN ('pending','active','suspended','closed')),
 slot_interval_minutes SMALLINT NOT NULL DEFAULT 15 CHECK(slot_interval_minutes BETWEEN 5 AND 120),
 booking_horizon_days SMALLINT NOT NULL DEFAULT 90 CHECK(booking_horizon_days BETWEEN 1 AND 730),
 minimum_lead_minutes INTEGER NOT NULL DEFAULT 120 CHECK(minimum_lead_minutes >= 0),
 cancellation_window_hours SMALLINT NOT NULL DEFAULT 24 CHECK(cancellation_window_hours >= 0), branding JSONB NOT NULL DEFAULT '{}',
 stripe_id VARCHAR(255) UNIQUE, pm_type VARCHAR(255), pm_last_four VARCHAR(4), trial_ends_at TIMESTAMPTZ,
 billing_status VARCHAR(32) NOT NULL DEFAULT 'pending', created_at TIMESTAMPTZ, updated_at TIMESTAMPTZ,
 CHECK(subdomain = lower(trim(subdomain)) AND subdomain ~ '^[a-z0-9]([a-z0-9-]*[a-z0-9])?$' AND subdomain NOT IN ('www','admin','api','support','mail','status','app','billing')),
 CHECK(currency ~ '^[A-Z]{3}$')
);
CREATE TABLE platform_admins (
 id BIGSERIAL PRIMARY KEY, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL UNIQUE CHECK(email = lower(trim(email))),
 password VARCHAR(255) NOT NULL, status VARCHAR(32) NOT NULL DEFAULT 'active', two_factor_secret TEXT,
 two_factor_recovery_codes TEXT, two_factor_confirmed_at TIMESTAMPTZ, remember_token VARCHAR(100), created_at TIMESTAMPTZ, updated_at TIMESTAMPTZ
);
CREATE TABLE password_reset_tokens (tenant_id BIGINT NOT NULL REFERENCES tenants(id), email VARCHAR(255) NOT NULL, token VARCHAR(255) NOT NULL, created_at TIMESTAMPTZ, PRIMARY KEY(tenant_id,email));
CREATE TABLE sessions (id VARCHAR(255) PRIMARY KEY, tenant_id BIGINT, user_id BIGINT, ip_address VARCHAR(45), user_agent TEXT, payload TEXT NOT NULL, last_activity INTEGER NOT NULL);
CREATE INDEX sessions_last_activity ON sessions(last_activity);
SQL);
        $this->table('users', "name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL CHECK(email = lower(trim(email))), password VARCHAR(255) NOT NULL, status VARCHAR(32) NOT NULL DEFAULT 'active' CHECK(status IN ('active','invited','disabled')), email_verified_at TIMESTAMPTZ, two_factor_secret TEXT, two_factor_recovery_codes TEXT, two_factor_confirmed_at TIMESTAMPTZ, remember_token VARCHAR(100), UNIQUE(tenant_id,email)");
        $this->table('user_roles', "user_id BIGINT NOT NULL, role VARCHAR(32) NOT NULL CHECK(role IN ('owner','practitioner','receptionist','patient')), UNIQUE(tenant_id,user_id,role)");
        $this->fk('user_roles', 'user_id', 'users');
        $this->table('staff_invitations', 'email VARCHAR(255) NOT NULL CHECK(email = lower(trim(email))), roles JSONB NOT NULL, token_hash VARCHAR(64) NOT NULL UNIQUE, expires_at TIMESTAMPTZ NOT NULL, accepted_at TIMESTAMPTZ, invited_by BIGINT');
        $this->fk('staff_invitations', 'invited_by', 'users');
        $this->table('patient_profiles', "user_id BIGINT, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, email VARCHAR(255), phone VARCHAR(255), date_of_birth DATE, status VARCHAR(32) NOT NULL DEFAULT 'active', UNIQUE(tenant_id,user_id)");
        $this->fk('patient_profiles', 'user_id', 'users');
        $this->table('practitioners', 'user_id BIGINT NOT NULL, display_title VARCHAR(255), specialization VARCHAR(255), active BOOLEAN NOT NULL DEFAULT true, UNIQUE(tenant_id,user_id)');
        $this->fk('practitioners', 'user_id', 'users');
        $this->table('operatories', 'name VARCHAR(255) NOT NULL, active BOOLEAN NOT NULL DEFAULT true, UNIQUE(tenant_id,name)');
        $this->table('services', "name VARCHAR(255) NOT NULL, category VARCHAR(255) NOT NULL, description TEXT, duration_minutes SMALLINT NOT NULL CHECK(duration_minutes BETWEEN 1 AND 1440), buffer_before_minutes SMALLINT NOT NULL DEFAULT 0 CHECK(buffer_before_minutes BETWEEN 0 AND 1440), buffer_after_minutes SMALLINT NOT NULL DEFAULT 15 CHECK(buffer_after_minutes BETWEEN 0 AND 1440), price_minor INTEGER NOT NULL CHECK(price_minor >= 0), currency CHAR(3) NOT NULL DEFAULT 'PHP' CHECK(currency ~ '^[A-Z]{3}$'), active BOOLEAN NOT NULL DEFAULT true");
        foreach (['practitioner' => 'practitioners', 'operatory' => 'operatories'] as $singular => $target) {
            $name = $singular.'_services';
            $this->table($name, "{$singular}_id BIGINT NOT NULL, service_id BIGINT NOT NULL, UNIQUE(tenant_id,{$singular}_id,service_id)");
            $this->fk($name, $singular.'_id', $target);
            $this->fk($name, 'service_id', 'services');
        }
        foreach (['clinic_hours', 'practitioner_shifts', 'practitioner_breaks'] as $name) {
            $prefix = $name === 'clinic_hours' ? '' : 'practitioner_id BIGINT NOT NULL, ';
            $this->table($name, $prefix.'weekday SMALLINT NOT NULL CHECK(weekday BETWEEN 1 AND 7), local_start TIME NOT NULL, local_end TIME NOT NULL, effective_from DATE, effective_to DATE, CHECK(local_start < local_end), CHECK(effective_to IS NULL OR effective_from IS NULL OR effective_from <= effective_to)');
            if ($prefix) {
                $this->fk($name, 'practitioner_id', 'practitioners');
            }
            DB::statement("CREATE INDEX {$name}_weekday ON {$name}(tenant_id,weekday)");
        }
        $this->table('schedule_blocks', "resource_type VARCHAR(32) NOT NULL, practitioner_id BIGINT, operatory_id BIGINT, starts_at TIMESTAMPTZ NOT NULL, ends_at TIMESTAMPTZ NOT NULL, reason VARCHAR(255), CHECK(starts_at < ends_at), CHECK((resource_type='clinic' AND practitioner_id IS NULL AND operatory_id IS NULL) OR (resource_type='practitioner' AND practitioner_id IS NOT NULL AND operatory_id IS NULL) OR (resource_type='operatory' AND operatory_id IS NOT NULL AND practitioner_id IS NULL))");
        $this->fk('schedule_blocks', 'practitioner_id', 'practitioners');
        $this->fk('schedule_blocks', 'operatory_id', 'operatories');
        $this->table('appointments', "public_code VARCHAR(255) NOT NULL UNIQUE, patient_id BIGINT NOT NULL, practitioner_id BIGINT NOT NULL, service_id BIGINT NOT NULL, operatory_id BIGINT NOT NULL, start_at TIMESTAMPTZ NOT NULL, end_at TIMESTAMPTZ NOT NULL, occupied_start_at TIMESTAMPTZ NOT NULL, occupied_end_at TIMESTAMPTZ NOT NULL, status VARCHAR(32) NOT NULL DEFAULT 'scheduled' CHECK(status IN ('scheduled','confirmed','checked_in','in_chair','completed','no_show','cancelled')), price_minor INTEGER NOT NULL CHECK(price_minor >= 0), currency CHAR(3) NOT NULL CHECK(currency ~ '^[A-Z]{3}$'), duration_minutes SMALLINT NOT NULL CHECK(duration_minutes > 0), buffer_before_minutes SMALLINT NOT NULL CHECK(buffer_before_minutes >= 0), buffer_after_minutes SMALLINT NOT NULL CHECK(buffer_after_minutes >= 0), timezone VARCHAR(255) NOT NULL, version INTEGER NOT NULL DEFAULT 1 CHECK(version > 0), payment_method VARCHAR(32) NOT NULL DEFAULT 'pay_at_clinic' CHECK(payment_method='pay_at_clinic'), checked_in_at TIMESTAMPTZ, in_chair_at TIMESTAMPTZ, completed_at TIMESTAMPTZ, UNIQUE(tenant_id,id,patient_id), CHECK(occupied_start_at <= start_at AND start_at < end_at AND end_at <= occupied_end_at)");
        foreach (['patient_id' => 'patient_profiles', 'practitioner_id' => 'practitioners', 'service_id' => 'services', 'operatory_id' => 'operatories'] as $column => $target) {
            $this->fk('appointments', $column, $target);
        }
        foreach (['practitioner', 'operatory'] as $resource) {
            DB::statement("ALTER TABLE appointments ADD CONSTRAINT appointments_{$resource}_no_overlap EXCLUDE USING gist (tenant_id WITH =, {$resource}_id WITH =, tstzrange(occupied_start_at,occupied_end_at,'[)') WITH &&) WHERE(status <> 'cancelled')");
        }
        DB::statement('CREATE INDEX appointments_tenant_start ON appointments(tenant_id,start_at)');
        DB::statement('CREATE INDEX appointments_patient_start ON appointments(tenant_id,patient_id,start_at)');
        $this->table('appointment_events', 'appointment_id BIGINT NOT NULL, actor_id BIGINT, prior_state VARCHAR(32), new_state VARCHAR(32) NOT NULL, reason VARCHAR(255), occurred_at TIMESTAMPTZ NOT NULL, version INTEGER NOT NULL, UNIQUE(tenant_id,appointment_id,version)');
        $this->fk('appointment_events', 'appointment_id', 'appointments');
        $this->fk('appointment_events', 'actor_id', 'users');
        $this->table('idempotency_requests', 'actor_id BIGINT NOT NULL, operation VARCHAR(255) NOT NULL, key_hash VARCHAR(64) NOT NULL, request_hash VARCHAR(64) NOT NULL, result_resource_id BIGINT NOT NULL, expires_at TIMESTAMPTZ NOT NULL, UNIQUE(tenant_id,actor_id,operation,key_hash)');
        $this->fk('idempotency_requests', 'actor_id', 'users');
        $this->fk('idempotency_requests', 'result_resource_id', 'appointments');
        $this->table('outbox_messages', "event_type VARCHAR(255) NOT NULL, resource_id BIGINT NOT NULL, version INTEGER NOT NULL, deduplication_key VARCHAR(255) NOT NULL, state VARCHAR(32) NOT NULL DEFAULT 'pending', attempts INTEGER NOT NULL DEFAULT 0, available_at TIMESTAMPTZ NOT NULL, processed_at TIMESTAMPTZ, last_error_code VARCHAR(255), UNIQUE(tenant_id,deduplication_key)");
        $this->fk('outbox_messages', 'resource_id', 'appointments');
        DB::statement('CREATE INDEX outbox_pending ON outbox_messages(state,available_at)');
        $this->table('notification_deliveries', "outbox_message_id BIGINT NOT NULL, channel VARCHAR(32) NOT NULL DEFAULT 'email', state VARCHAR(32) NOT NULL DEFAULT 'pending', attempts INTEGER NOT NULL DEFAULT 0, sent_at TIMESTAMPTZ, last_error_code VARCHAR(255), UNIQUE(tenant_id,outbox_message_id,channel)");
        $this->fk('notification_deliveries', 'outbox_message_id', 'outbox_messages');
        $this->table('audit_events', "actor_id BIGINT, action VARCHAR(255) NOT NULL, resource_type VARCHAR(255) NOT NULL, resource_id BIGINT, metadata JSONB NOT NULL DEFAULT '{}', occurred_at TIMESTAMPTZ NOT NULL");
        $this->fk('audit_events', 'actor_id', 'users');
        $this->table('intake_submissions', 'patient_id BIGINT NOT NULL, appointment_id BIGINT, form_version VARCHAR(255) NOT NULL, answers TEXT NOT NULL, submitted_at TIMESTAMPTZ NOT NULL, author_id BIGINT NOT NULL, supersedes_id BIGINT, UNIQUE(tenant_id,id,patient_id)');
        $this->table('clinical_notes', 'patient_id BIGINT NOT NULL, appointment_id BIGINT NOT NULL, practitioner_id BIGINT NOT NULL, content TEXT NOT NULL, note_version INTEGER NOT NULL CHECK(note_version > 0), signed_at TIMESTAMPTZ, supersedes_id BIGINT, UNIQUE(tenant_id,id,patient_id), UNIQUE(tenant_id,appointment_id,note_version)');
        foreach (['intake_submissions', 'clinical_notes'] as $name) {
            $this->fk($name, 'patient_id', 'patient_profiles');
            DB::statement("ALTER TABLE {$name} ADD FOREIGN KEY(tenant_id,appointment_id,patient_id) REFERENCES appointments(tenant_id,id,patient_id) ON DELETE RESTRICT");
            DB::statement("ALTER TABLE {$name} ADD FOREIGN KEY(tenant_id,supersedes_id,patient_id) REFERENCES {$name}(tenant_id,id,patient_id) ON DELETE RESTRICT");
        }
        $this->fk('intake_submissions', 'author_id', 'users');
        $this->fk('clinical_notes', 'practitioner_id', 'practitioners');
        $this->table('care_assignments', 'patient_id BIGINT NOT NULL, practitioner_id BIGINT NOT NULL, appointment_id BIGINT, granted_by BIGINT NOT NULL, valid_from TIMESTAMPTZ NOT NULL, valid_to TIMESTAMPTZ, revoked_at TIMESTAMPTZ, purpose VARCHAR(255) NOT NULL, CHECK(valid_to IS NULL OR valid_from < valid_to)');
        $this->fk('care_assignments', 'patient_id', 'patient_profiles');
        $this->fk('care_assignments', 'practitioner_id', 'practitioners');
        $this->fk('care_assignments', 'granted_by', 'users');
        DB::statement('ALTER TABLE care_assignments ADD FOREIGN KEY(tenant_id,appointment_id,patient_id) REFERENCES appointments(tenant_id,id,patient_id) ON DELETE RESTRICT');
        $this->table('consent_records', 'patient_id BIGINT NOT NULL, user_id BIGINT, purpose VARCHAR(255) NOT NULL, notice_version VARCHAR(255) NOT NULL, granted_at TIMESTAMPTZ NOT NULL, withdrawn_at TIMESTAMPTZ, capture_method VARCHAR(255) NOT NULL');
        $this->fk('consent_records', 'patient_id', 'patient_profiles');
        $this->fk('consent_records', 'user_id', 'users');
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION smilesuite_immutable_tenant() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN
 IF NEW.tenant_id IS DISTINCT FROM OLD.tenant_id THEN RAISE EXCEPTION 'Tenant ownership is immutable' USING ERRCODE='23514'; END IF;
 RETURN NEW; END; $$;
CREATE OR REPLACE FUNCTION smilesuite_append_only() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN
 RAISE EXCEPTION 'Append-only record; create an amendment' USING ERRCODE='23514'; END; $$;
SQL);
        foreach ($this->tables as $name) {
            DB::statement("CREATE TRIGGER {$name}_immutable_tenant BEFORE UPDATE ON {$name} FOR EACH ROW EXECUTE FUNCTION smilesuite_immutable_tenant()");
        }
        foreach (['audit_events', 'appointment_events', 'intake_submissions', 'clinical_notes'] as $name) {
            DB::statement("CREATE TRIGGER {$name}_append_only BEFORE UPDATE OR DELETE ON {$name} FOR EACH ROW EXECUTE FUNCTION smilesuite_append_only()");
        }
    }

    public function down(): void
    {
        foreach (['consent_records', 'care_assignments', 'clinical_notes', 'intake_submissions', 'audit_events', 'notification_deliveries', 'outbox_messages', 'idempotency_requests', 'appointment_events', 'appointments', 'schedule_blocks', 'practitioner_breaks', 'practitioner_shifts', 'clinic_hours', 'operatory_services', 'practitioner_services', 'services', 'operatories', 'practitioners', 'patient_profiles', 'staff_invitations', 'user_roles', 'users', 'sessions', 'password_reset_tokens', 'platform_admins', 'tenants'] as $name) {
            DB::statement("DROP TABLE IF EXISTS {$name}");
        }
        DB::statement('DROP FUNCTION IF EXISTS smilesuite_immutable_tenant()');
        DB::statement('DROP FUNCTION IF EXISTS smilesuite_append_only()');
    }
};
