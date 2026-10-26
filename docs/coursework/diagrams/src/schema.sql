--
-- PostgreSQL database dump
--

\restrict j4smyOco0McflOM5P2zcL189cynGo3xqduQNUxqpu9OlYusoAjuMQMHSc54jtPD

-- Dumped from database version 16.15
-- Dumped by pg_dump version 16.15

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: btree_gist; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS btree_gist WITH SCHEMA public;


--
-- Name: EXTENSION btree_gist; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION btree_gist IS 'support for indexing common datatypes in GiST';


--
-- Name: prevent_platform_audit_mutation(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.prevent_platform_audit_mutation() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    RAISE EXCEPTION 'Platform audit events are append-only';
END;
$$;


--
-- Name: smilesuite_append_only(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.smilesuite_append_only() RETURNS trigger
    LANGUAGE plpgsql
    AS $$ BEGIN
 RAISE EXCEPTION 'Append-only record; create an amendment' USING ERRCODE='23514'; END; $$;


--
-- Name: smilesuite_immutable_subscription(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.smilesuite_immutable_subscription() RETURNS trigger
    LANGUAGE plpgsql
    AS $$ BEGIN IF NEW.subscription_id IS DISTINCT FROM OLD.subscription_id THEN RAISE EXCEPTION 'Subscription ownership is immutable' USING ERRCODE='23514'; END IF; RETURN NEW; END; $$;


--
-- Name: smilesuite_immutable_tenant(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.smilesuite_immutable_tenant() RETURNS trigger
    LANGUAGE plpgsql
    AS $$ BEGIN
 IF NEW.tenant_id IS DISTINCT FROM OLD.tenant_id THEN RAISE EXCEPTION 'Tenant ownership is immutable' USING ERRCODE='23514'; END IF;
 RETURN NEW; END; $$;


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: appointment_events; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.appointment_events (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    appointment_id bigint NOT NULL,
    actor_id bigint,
    prior_state character varying(32),
    new_state character varying(32) NOT NULL,
    reason character varying(255),
    occurred_at timestamp with time zone NOT NULL,
    version integer NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: appointment_events_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.appointment_events_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: appointment_events_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.appointment_events_id_seq OWNED BY public.appointment_events.id;


--
-- Name: appointments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.appointments (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    public_code character varying(255) NOT NULL,
    patient_id bigint NOT NULL,
    practitioner_id bigint NOT NULL,
    service_id bigint NOT NULL,
    operatory_id bigint NOT NULL,
    start_at timestamp with time zone NOT NULL,
    end_at timestamp with time zone NOT NULL,
    occupied_start_at timestamp with time zone NOT NULL,
    occupied_end_at timestamp with time zone NOT NULL,
    status character varying(32) DEFAULT 'scheduled'::character varying NOT NULL,
    price_minor integer NOT NULL,
    currency character(3) NOT NULL,
    duration_minutes smallint NOT NULL,
    buffer_before_minutes smallint NOT NULL,
    buffer_after_minutes smallint NOT NULL,
    timezone character varying(255) NOT NULL,
    version integer DEFAULT 1 NOT NULL,
    payment_method character varying(32) DEFAULT 'pay_at_clinic'::character varying NOT NULL,
    checked_in_at timestamp with time zone,
    in_chair_at timestamp with time zone,
    completed_at timestamp with time zone,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    CONSTRAINT appointments_buffer_after_minutes_check CHECK ((buffer_after_minutes >= 0)),
    CONSTRAINT appointments_buffer_before_minutes_check CHECK ((buffer_before_minutes >= 0)),
    CONSTRAINT appointments_check CHECK (((occupied_start_at <= start_at) AND (start_at < end_at) AND (end_at <= occupied_end_at))),
    CONSTRAINT appointments_currency_check CHECK ((currency ~ '^[A-Z]{3}$'::text)),
    CONSTRAINT appointments_duration_minutes_check CHECK ((duration_minutes > 0)),
    CONSTRAINT appointments_payment_method_check CHECK (((payment_method)::text = 'pay_at_clinic'::text)),
    CONSTRAINT appointments_price_minor_check CHECK ((price_minor >= 0)),
    CONSTRAINT appointments_status_check CHECK (((status)::text = ANY ((ARRAY['scheduled'::character varying, 'confirmed'::character varying, 'checked_in'::character varying, 'in_chair'::character varying, 'completed'::character varying, 'no_show'::character varying, 'cancelled'::character varying])::text[]))),
    CONSTRAINT appointments_version_check CHECK ((version > 0))
);


--
-- Name: appointments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.appointments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: appointments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.appointments_id_seq OWNED BY public.appointments.id;


--
-- Name: audit_events; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.audit_events (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    actor_id bigint,
    action character varying(255) NOT NULL,
    resource_type character varying(255) NOT NULL,
    resource_id bigint,
    metadata jsonb DEFAULT '{}'::jsonb NOT NULL,
    occurred_at timestamp with time zone NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: audit_events_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.audit_events_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: audit_events_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.audit_events_id_seq OWNED BY public.audit_events.id;


--
-- Name: billing_checkout_sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.billing_checkout_sessions (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    generation integer NOT NULL,
    price_id character varying(255) NOT NULL,
    success_url text NOT NULL,
    cancel_url text NOT NULL,
    provider_session_id character varying(255),
    checkout_url text,
    expires_at timestamp(0) with time zone,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: billing_checkout_sessions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.billing_checkout_sessions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: billing_checkout_sessions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.billing_checkout_sessions_id_seq OWNED BY public.billing_checkout_sessions.id;


--
-- Name: billing_entitlements; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.billing_entitlements (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    tier character varying(255) DEFAULT 'none'::character varying NOT NULL,
    bookings_enabled boolean DEFAULT false NOT NULL,
    chair_limit integer,
    practitioner_limit integer,
    past_due_since timestamp(0) with time zone,
    grace_until timestamp(0) with time zone,
    over_limit boolean DEFAULT false NOT NULL,
    reconciled_at timestamp(0) with time zone,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: billing_entitlements_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.billing_entitlements_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: billing_entitlements_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.billing_entitlements_id_seq OWNED BY public.billing_entitlements.id;


--
-- Name: cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration bigint NOT NULL
);


--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration bigint NOT NULL
);


--
-- Name: care_assignments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.care_assignments (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    patient_id bigint NOT NULL,
    practitioner_id bigint NOT NULL,
    appointment_id bigint,
    granted_by bigint NOT NULL,
    valid_from timestamp with time zone NOT NULL,
    valid_to timestamp with time zone,
    revoked_at timestamp with time zone,
    purpose character varying(255) NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    CONSTRAINT care_assignments_check CHECK (((valid_to IS NULL) OR (valid_from < valid_to)))
);


--
-- Name: care_assignments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.care_assignments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: care_assignments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.care_assignments_id_seq OWNED BY public.care_assignments.id;


--
-- Name: clinic_hours; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.clinic_hours (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    weekday smallint NOT NULL,
    local_start time without time zone NOT NULL,
    local_end time without time zone NOT NULL,
    effective_from date,
    effective_to date,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    CONSTRAINT clinic_hours_check CHECK ((local_start < local_end)),
    CONSTRAINT clinic_hours_check1 CHECK (((effective_to IS NULL) OR (effective_from IS NULL) OR (effective_from <= effective_to))),
    CONSTRAINT clinic_hours_weekday_check CHECK (((weekday >= 1) AND (weekday <= 7)))
);


--
-- Name: clinic_hours_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.clinic_hours_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: clinic_hours_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.clinic_hours_id_seq OWNED BY public.clinic_hours.id;


--
-- Name: clinical_notes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.clinical_notes (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    patient_id bigint NOT NULL,
    appointment_id bigint NOT NULL,
    practitioner_id bigint NOT NULL,
    content text NOT NULL,
    note_version integer NOT NULL,
    signed_at timestamp with time zone,
    supersedes_id bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    CONSTRAINT clinical_notes_note_version_check CHECK ((note_version > 0))
);


--
-- Name: clinical_notes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.clinical_notes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: clinical_notes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.clinical_notes_id_seq OWNED BY public.clinical_notes.id;


--
-- Name: consent_records; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.consent_records (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    patient_id bigint NOT NULL,
    user_id bigint,
    purpose character varying(255) NOT NULL,
    notice_version character varying(255) NOT NULL,
    granted_at timestamp with time zone NOT NULL,
    withdrawn_at timestamp with time zone,
    capture_method character varying(255) NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: consent_records_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.consent_records_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: consent_records_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.consent_records_id_seq OWNED BY public.consent_records.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection character varying(255) NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: idempotency_requests; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.idempotency_requests (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    actor_id bigint NOT NULL,
    operation character varying(255) NOT NULL,
    key_hash character varying(64) NOT NULL,
    request_hash character varying(64) NOT NULL,
    result_resource_id bigint NOT NULL,
    expires_at timestamp with time zone NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: idempotency_requests_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.idempotency_requests_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: idempotency_requests_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.idempotency_requests_id_seq OWNED BY public.idempotency_requests.id;


--
-- Name: intake_submissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.intake_submissions (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    patient_id bigint NOT NULL,
    appointment_id bigint,
    form_version character varying(255) NOT NULL,
    answers text NOT NULL,
    submitted_at timestamp with time zone NOT NULL,
    author_id bigint NOT NULL,
    supersedes_id bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: intake_submissions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.intake_submissions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: intake_submissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.intake_submissions_id_seq OWNED BY public.intake_submissions.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


--
-- Name: jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: notification_deliveries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notification_deliveries (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    outbox_message_id bigint NOT NULL,
    channel character varying(32) DEFAULT 'email'::character varying NOT NULL,
    state character varying(32) DEFAULT 'pending'::character varying NOT NULL,
    attempts integer DEFAULT 0 NOT NULL,
    sent_at timestamp with time zone,
    last_error_code character varying(255),
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: notification_deliveries_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.notification_deliveries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: notification_deliveries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.notification_deliveries_id_seq OWNED BY public.notification_deliveries.id;


--
-- Name: operatories; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.operatories (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    active boolean DEFAULT true NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: operatories_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.operatories_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: operatories_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.operatories_id_seq OWNED BY public.operatories.id;


--
-- Name: operatory_services; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.operatory_services (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    operatory_id bigint NOT NULL,
    service_id bigint NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: operatory_services_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.operatory_services_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: operatory_services_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.operatory_services_id_seq OWNED BY public.operatory_services.id;


--
-- Name: outbox_messages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.outbox_messages (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    event_type character varying(255) NOT NULL,
    resource_id bigint NOT NULL,
    version integer NOT NULL,
    deduplication_key character varying(255) NOT NULL,
    state character varying(32) DEFAULT 'pending'::character varying NOT NULL,
    attempts integer DEFAULT 0 NOT NULL,
    available_at timestamp with time zone NOT NULL,
    processed_at timestamp with time zone,
    last_error_code character varying(255),
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: outbox_messages_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.outbox_messages_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: outbox_messages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.outbox_messages_id_seq OWNED BY public.outbox_messages.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_reset_tokens (
    tenant_id bigint NOT NULL,
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp with time zone
);


--
-- Name: patient_profiles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.patient_profiles (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    user_id bigint,
    first_name character varying(255) NOT NULL,
    last_name character varying(255) NOT NULL,
    email character varying(255),
    phone character varying(255),
    date_of_birth date,
    status character varying(32) DEFAULT 'active'::character varying NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: patient_profiles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.patient_profiles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: patient_profiles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.patient_profiles_id_seq OWNED BY public.patient_profiles.id;


--
-- Name: platform_admins; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.platform_admins (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    password character varying(255) NOT NULL,
    status character varying(32) DEFAULT 'active'::character varying NOT NULL,
    two_factor_secret text,
    two_factor_recovery_codes text,
    two_factor_confirmed_at timestamp with time zone,
    remember_token character varying(100),
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    CONSTRAINT platform_admins_email_check CHECK (((email)::text = lower(TRIM(BOTH FROM email))))
);


--
-- Name: platform_admins_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.platform_admins_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: platform_admins_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.platform_admins_id_seq OWNED BY public.platform_admins.id;


--
-- Name: platform_audit_events; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.platform_audit_events (
    id bigint NOT NULL,
    platform_admin_id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    action character varying(64) NOT NULL,
    previous_status character varying(32) NOT NULL,
    new_status character varying(32) NOT NULL,
    occurred_at timestamp(0) with time zone NOT NULL
);


--
-- Name: platform_audit_events_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.platform_audit_events_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: platform_audit_events_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.platform_audit_events_id_seq OWNED BY public.platform_audit_events.id;


--
-- Name: practitioner_breaks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.practitioner_breaks (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    practitioner_id bigint NOT NULL,
    weekday smallint NOT NULL,
    local_start time without time zone NOT NULL,
    local_end time without time zone NOT NULL,
    effective_from date,
    effective_to date,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    CONSTRAINT practitioner_breaks_check CHECK ((local_start < local_end)),
    CONSTRAINT practitioner_breaks_check1 CHECK (((effective_to IS NULL) OR (effective_from IS NULL) OR (effective_from <= effective_to))),
    CONSTRAINT practitioner_breaks_weekday_check CHECK (((weekday >= 1) AND (weekday <= 7)))
);


--
-- Name: practitioner_breaks_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.practitioner_breaks_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: practitioner_breaks_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.practitioner_breaks_id_seq OWNED BY public.practitioner_breaks.id;


--
-- Name: practitioner_services; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.practitioner_services (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    practitioner_id bigint NOT NULL,
    service_id bigint NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: practitioner_services_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.practitioner_services_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: practitioner_services_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.practitioner_services_id_seq OWNED BY public.practitioner_services.id;


--
-- Name: practitioner_shifts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.practitioner_shifts (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    practitioner_id bigint NOT NULL,
    weekday smallint NOT NULL,
    local_start time without time zone NOT NULL,
    local_end time without time zone NOT NULL,
    effective_from date,
    effective_to date,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    CONSTRAINT practitioner_shifts_check CHECK ((local_start < local_end)),
    CONSTRAINT practitioner_shifts_check1 CHECK (((effective_to IS NULL) OR (effective_from IS NULL) OR (effective_from <= effective_to))),
    CONSTRAINT practitioner_shifts_weekday_check CHECK (((weekday >= 1) AND (weekday <= 7)))
);


--
-- Name: practitioner_shifts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.practitioner_shifts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: practitioner_shifts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.practitioner_shifts_id_seq OWNED BY public.practitioner_shifts.id;


--
-- Name: practitioners; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.practitioners (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    user_id bigint NOT NULL,
    display_title character varying(255),
    specialization character varying(255),
    active boolean DEFAULT true NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: practitioners_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.practitioners_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: practitioners_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.practitioners_id_seq OWNED BY public.practitioners.id;


--
-- Name: schedule_blocks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.schedule_blocks (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    resource_type character varying(32) NOT NULL,
    practitioner_id bigint,
    operatory_id bigint,
    starts_at timestamp with time zone NOT NULL,
    ends_at timestamp with time zone NOT NULL,
    reason character varying(255),
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    CONSTRAINT schedule_blocks_check CHECK ((starts_at < ends_at)),
    CONSTRAINT schedule_blocks_check1 CHECK (((((resource_type)::text = 'clinic'::text) AND (practitioner_id IS NULL) AND (operatory_id IS NULL)) OR (((resource_type)::text = 'practitioner'::text) AND (practitioner_id IS NOT NULL) AND (operatory_id IS NULL)) OR (((resource_type)::text = 'operatory'::text) AND (operatory_id IS NOT NULL) AND (practitioner_id IS NULL))))
);


--
-- Name: schedule_blocks_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.schedule_blocks_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: schedule_blocks_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.schedule_blocks_id_seq OWNED BY public.schedule_blocks.id;


--
-- Name: services; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.services (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    category character varying(255) NOT NULL,
    description text,
    duration_minutes smallint NOT NULL,
    buffer_before_minutes smallint DEFAULT 0 NOT NULL,
    buffer_after_minutes smallint DEFAULT 15 NOT NULL,
    price_minor integer NOT NULL,
    currency character(3) DEFAULT 'PHP'::bpchar NOT NULL,
    active boolean DEFAULT true NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    CONSTRAINT services_buffer_after_minutes_check CHECK (((buffer_after_minutes >= 0) AND (buffer_after_minutes <= 1440))),
    CONSTRAINT services_buffer_before_minutes_check CHECK (((buffer_before_minutes >= 0) AND (buffer_before_minutes <= 1440))),
    CONSTRAINT services_currency_check CHECK ((currency ~ '^[A-Z]{3}$'::text)),
    CONSTRAINT services_duration_minutes_check CHECK (((duration_minutes >= 1) AND (duration_minutes <= 1440))),
    CONSTRAINT services_price_minor_check CHECK ((price_minor >= 0))
);


--
-- Name: services_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.services_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: services_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.services_id_seq OWNED BY public.services.id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    tenant_id bigint,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


--
-- Name: shopify_connections; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.shopify_connections (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    shop_host character varying(255) NOT NULL,
    shop_id character varying(255) NOT NULL,
    primary_domain character varying(255),
    private_token text,
    status character varying(255) DEFAULT 'active'::character varying NOT NULL,
    generation integer DEFAULT 1 NOT NULL,
    verified_at timestamp(0) with time zone,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: shopify_connections_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.shopify_connections_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: shopify_connections_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.shopify_connections_id_seq OWNED BY public.shopify_connections.id;


--
-- Name: shopping_carts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.shopping_carts (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    connection_generation integer NOT NULL,
    session_hash character varying(64) NOT NULL,
    shopify_cart_id text NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: shopping_carts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.shopping_carts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: shopping_carts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.shopping_carts_id_seq OWNED BY public.shopping_carts.id;


--
-- Name: staff_invitations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.staff_invitations (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    email character varying(255) NOT NULL,
    roles jsonb NOT NULL,
    token_hash character varying(64) NOT NULL,
    expires_at timestamp with time zone NOT NULL,
    accepted_at timestamp with time zone,
    invited_by bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    CONSTRAINT staff_invitations_email_check CHECK (((email)::text = lower(TRIM(BOTH FROM email))))
);


--
-- Name: staff_invitations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.staff_invitations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: staff_invitations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.staff_invitations_id_seq OWNED BY public.staff_invitations.id;


--
-- Name: subscription_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.subscription_items (
    id bigint NOT NULL,
    subscription_id bigint NOT NULL,
    stripe_id character varying(255) NOT NULL,
    stripe_product character varying(255) NOT NULL,
    stripe_price character varying(255) NOT NULL,
    meter_id character varying(255),
    meter_event_name character varying(255),
    quantity integer,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: subscription_items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.subscription_items_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: subscription_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.subscription_items_id_seq OWNED BY public.subscription_items.id;


--
-- Name: subscriptions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.subscriptions (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    type character varying(255) NOT NULL,
    stripe_id character varying(255) NOT NULL,
    stripe_status character varying(255) NOT NULL,
    stripe_price character varying(255),
    quantity integer,
    trial_ends_at timestamp(0) with time zone,
    ends_at timestamp(0) with time zone,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: subscriptions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.subscriptions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: subscriptions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.subscriptions_id_seq OWNED BY public.subscriptions.id;


--
-- Name: tenants; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tenants (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    subdomain character varying(63) NOT NULL,
    timezone character varying(255) DEFAULT 'Asia/Manila'::character varying NOT NULL,
    currency character(3) DEFAULT 'PHP'::bpchar NOT NULL,
    lifecycle_status character varying(32) DEFAULT 'active'::character varying NOT NULL,
    slot_interval_minutes smallint DEFAULT 15 NOT NULL,
    booking_horizon_days smallint DEFAULT 90 NOT NULL,
    minimum_lead_minutes integer DEFAULT 120 NOT NULL,
    cancellation_window_hours smallint DEFAULT 24 NOT NULL,
    branding jsonb DEFAULT '{}'::jsonb NOT NULL,
    stripe_id character varying(255),
    pm_type character varying(255),
    pm_last_four character varying(4),
    trial_ends_at timestamp with time zone,
    billing_status character varying(32) DEFAULT 'pending'::character varying NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    CONSTRAINT tenants_booking_horizon_days_check CHECK (((booking_horizon_days >= 1) AND (booking_horizon_days <= 730))),
    CONSTRAINT tenants_cancellation_window_hours_check CHECK ((cancellation_window_hours >= 0)),
    CONSTRAINT tenants_currency_check CHECK ((currency ~ '^[A-Z]{3}$'::text)),
    CONSTRAINT tenants_lifecycle_status_check CHECK (((lifecycle_status)::text = ANY ((ARRAY['pending'::character varying, 'active'::character varying, 'suspended'::character varying, 'closed'::character varying])::text[]))),
    CONSTRAINT tenants_minimum_lead_minutes_check CHECK ((minimum_lead_minutes >= 0)),
    CONSTRAINT tenants_slot_interval_minutes_check CHECK (((slot_interval_minutes >= 5) AND (slot_interval_minutes <= 120))),
    CONSTRAINT tenants_subdomain_check CHECK ((((subdomain)::text = lower(TRIM(BOTH FROM subdomain))) AND ((subdomain)::text ~ '^[a-z0-9]([a-z0-9-]*[a-z0-9])?$'::text) AND ((subdomain)::text <> ALL ((ARRAY['www'::character varying, 'admin'::character varying, 'api'::character varying, 'support'::character varying, 'mail'::character varying, 'status'::character varying, 'app'::character varying, 'billing'::character varying])::text[]))))
);


--
-- Name: tenants_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.tenants_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tenants_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.tenants_id_seq OWNED BY public.tenants.id;


--
-- Name: user_roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_roles (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    user_id bigint NOT NULL,
    role character varying(32) NOT NULL,
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    CONSTRAINT user_roles_role_check CHECK (((role)::text = ANY ((ARRAY['owner'::character varying, 'practitioner'::character varying, 'receptionist'::character varying, 'patient'::character varying])::text[])))
);


--
-- Name: user_roles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.user_roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: user_roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.user_roles_id_seq OWNED BY public.user_roles.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    tenant_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    password character varying(255) NOT NULL,
    status character varying(32) DEFAULT 'active'::character varying NOT NULL,
    email_verified_at timestamp with time zone,
    two_factor_secret text,
    two_factor_recovery_codes text,
    two_factor_confirmed_at timestamp with time zone,
    remember_token character varying(100),
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    CONSTRAINT users_email_check CHECK (((email)::text = lower(TRIM(BOTH FROM email)))),
    CONSTRAINT users_status_check CHECK (((status)::text = ANY ((ARRAY['active'::character varying, 'invited'::character varying, 'disabled'::character varying])::text[])))
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: webhook_events; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.webhook_events (
    id bigint NOT NULL,
    provider character varying(255) NOT NULL,
    provider_event_id character varying(255) NOT NULL,
    event_type character varying(255) NOT NULL,
    tenant_id bigint,
    raw_payload text NOT NULL,
    state character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    attempts integer DEFAULT 0 NOT NULL,
    last_error_code character varying(255),
    processed_at timestamp(0) with time zone,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: webhook_events_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.webhook_events_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: webhook_events_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.webhook_events_id_seq OWNED BY public.webhook_events.id;


--
-- Name: appointment_events id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.appointment_events ALTER COLUMN id SET DEFAULT nextval('public.appointment_events_id_seq'::regclass);


--
-- Name: appointments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.appointments ALTER COLUMN id SET DEFAULT nextval('public.appointments_id_seq'::regclass);


--
-- Name: audit_events id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_events ALTER COLUMN id SET DEFAULT nextval('public.audit_events_id_seq'::regclass);


--
-- Name: billing_checkout_sessions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.billing_checkout_sessions ALTER COLUMN id SET DEFAULT nextval('public.billing_checkout_sessions_id_seq'::regclass);


--
-- Name: billing_entitlements id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.billing_entitlements ALTER COLUMN id SET DEFAULT nextval('public.billing_entitlements_id_seq'::regclass);


--
-- Name: care_assignments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.care_assignments ALTER COLUMN id SET DEFAULT nextval('public.care_assignments_id_seq'::regclass);


--
-- Name: clinic_hours id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clinic_hours ALTER COLUMN id SET DEFAULT nextval('public.clinic_hours_id_seq'::regclass);


--
-- Name: clinical_notes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clinical_notes ALTER COLUMN id SET DEFAULT nextval('public.clinical_notes_id_seq'::regclass);


--
-- Name: consent_records id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.consent_records ALTER COLUMN id SET DEFAULT nextval('public.consent_records_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: idempotency_requests id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.idempotency_requests ALTER COLUMN id SET DEFAULT nextval('public.idempotency_requests_id_seq'::regclass);


--
-- Name: intake_submissions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.intake_submissions ALTER COLUMN id SET DEFAULT nextval('public.intake_submissions_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: notification_deliveries id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_deliveries ALTER COLUMN id SET DEFAULT nextval('public.notification_deliveries_id_seq'::regclass);


--
-- Name: operatories id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.operatories ALTER COLUMN id SET DEFAULT nextval('public.operatories_id_seq'::regclass);


--
-- Name: operatory_services id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.operatory_services ALTER COLUMN id SET DEFAULT nextval('public.operatory_services_id_seq'::regclass);


--
-- Name: outbox_messages id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.outbox_messages ALTER COLUMN id SET DEFAULT nextval('public.outbox_messages_id_seq'::regclass);


--
-- Name: patient_profiles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.patient_profiles ALTER COLUMN id SET DEFAULT nextval('public.patient_profiles_id_seq'::regclass);


--
-- Name: platform_admins id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.platform_admins ALTER COLUMN id SET DEFAULT nextval('public.platform_admins_id_seq'::regclass);


--
-- Name: platform_audit_events id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.platform_audit_events ALTER COLUMN id SET DEFAULT nextval('public.platform_audit_events_id_seq'::regclass);


--
-- Name: practitioner_breaks id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.practitioner_breaks ALTER COLUMN id SET DEFAULT nextval('public.practitioner_breaks_id_seq'::regclass);


--
-- Name: practitioner_services id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.practitioner_services ALTER COLUMN id SET DEFAULT nextval('public.practitioner_services_id_seq'::regclass);


--
-- Name: practitioner_shifts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.practitioner_shifts ALTER COLUMN id SET DEFAULT nextval('public.practitioner_shifts_id_seq'::regclass);


--
-- Name: practitioners id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.practitioners ALTER COLUMN id SET DEFAULT nextval('public.practitioners_id_seq'::regclass);


--
-- Name: schedule_blocks id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.schedule_blocks ALTER COLUMN id SET DEFAULT nextval('public.schedule_blocks_id_seq'::regclass);


--
-- Name: services id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.services ALTER COLUMN id SET DEFAULT nextval('public.services_id_seq'::regclass);


--
-- Name: shopify_connections id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.shopify_connections ALTER COLUMN id SET DEFAULT nextval('public.shopify_connections_id_seq'::regclass);


--
-- Name: shopping_carts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.shopping_carts ALTER COLUMN id SET DEFAULT nextval('public.shopping_carts_id_seq'::regclass);


--
-- Name: staff_invitations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.staff_invitations ALTER COLUMN id SET DEFAULT nextval('public.staff_invitations_id_seq'::regclass);


--
-- Name: subscription_items id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscription_items ALTER COLUMN id SET DEFAULT nextval('public.subscription_items_id_seq'::regclass);


--
-- Name: subscriptions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscriptions ALTER COLUMN id SET DEFAULT nextval('public.subscriptions_id_seq'::regclass);


--
-- Name: tenants id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tenants ALTER COLUMN id SET DEFAULT nextval('public.tenants_id_seq'::regclass);


--
-- Name: user_roles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_roles ALTER COLUMN id SET DEFAULT nextval('public.user_roles_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: webhook_events id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.webhook_events ALTER COLUMN id SET DEFAULT nextval('public.webhook_events_id_seq'::regclass);


--
-- Name: appointment_events appointment_events_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.appointment_events
    ADD CONSTRAINT appointment_events_pkey PRIMARY KEY (id);


--
-- Name: appointment_events appointment_events_tenant_id_appointment_id_version_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.appointment_events
    ADD CONSTRAINT appointment_events_tenant_id_appointment_id_version_key UNIQUE (tenant_id, appointment_id, version);


--
-- Name: appointment_events appointment_events_tenant_id_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.appointment_events
    ADD CONSTRAINT appointment_events_tenant_id_id_key UNIQUE (tenant_id, id);


--
-- Name: appointments appointments_operatory_no_overlap; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.appointments
    ADD CONSTRAINT appointments_operatory_no_overlap EXCLUDE USING gist (tenant_id WITH =, operatory_id WITH =, tstzrange(occupied_start_at, occupied_end_at, '[)'::text) WITH &&) WHERE (((status)::text <> 'cancelled'::text));


--
-- Name: appointments appointments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.appointments
    ADD CONSTRAINT appointments_pkey PRIMARY KEY (id);


--
-- Name: appointments appointments_practitioner_no_overlap; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.appointments
    ADD CONSTRAINT appointments_practitioner_no_overlap EXCLUDE USING gist (tenant_id WITH =, practitioner_id WITH =, tstzrange(occupied_start_at, occupied_end_at, '[)'::text) WITH &&) WHERE (((status)::text <> 'cancelled'::text));


--
-- Name: appointments appointments_public_code_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.appointments
    ADD CONSTRAINT appointments_public_code_key UNIQUE (public_code);


--
-- Name: appointments appointments_tenant_id_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.appointments
    ADD CONSTRAINT appointments_tenant_id_id_key UNIQUE (tenant_id, id);


--
-- Name: appointments appointments_tenant_id_id_patient_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.appointments
    ADD CONSTRAINT appointments_tenant_id_id_patient_id_key UNIQUE (tenant_id, id, patient_id);


--
-- Name: audit_events audit_events_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_events
    ADD CONSTRAINT audit_events_pkey PRIMARY KEY (id);


--
-- Name: audit_events audit_events_tenant_id_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_events
    ADD CONSTRAINT audit_events_tenant_id_id_key UNIQUE (tenant_id, id);


--
-- Name: billing_checkout_sessions billing_checkout_sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.billing_checkout_sessions
    ADD CONSTRAINT billing_checkout_sessions_pkey PRIMARY KEY (id);


--
-- Name: billing_checkout_sessions billing_checkout_sessions_provider_session_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.billing_checkout_sessions
    ADD CONSTRAINT billing_checkout_sessions_provider_session_id_unique UNIQUE (provider_session_id);


--
-- Name: billing_checkout_sessions billing_checkout_sessions_tenant_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.billing_checkout_sessions
    ADD CONSTRAINT billing_checkout_sessions_tenant_id_unique UNIQUE (tenant_id);


--
-- Name: billing_entitlements billing_entitlements_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.billing_entitlements
    ADD CONSTRAINT billing_entitlements_pkey PRIMARY KEY (id);


--
-- Name: billing_entitlements billing_entitlements_tenant_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.billing_entitlements
    ADD CONSTRAINT billing_entitlements_tenant_id_unique UNIQUE (tenant_id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: care_assignments care_assignments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.care_assignments
    ADD CONSTRAINT care_assignments_pkey PRIMARY KEY (id);


--
-- Name: care_assignments care_assignments_tenant_id_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.care_assignments
    ADD CONSTRAINT care_assignments_tenant_id_id_key UNIQUE (tenant_id, id);


--
-- Name: clinic_hours clinic_hours_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clinic_hours
    ADD CONSTRAINT clinic_hours_pkey PRIMARY KEY (id);


--
-- Name: clinic_hours clinic_hours_tenant_id_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clinic_hours
    ADD CONSTRAINT clinic_hours_tenant_id_id_key UNIQUE (tenant_id, id);


--
-- Name: clinical_notes clinical_notes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clinical_notes
    ADD CONSTRAINT clinical_notes_pkey PRIMARY KEY (id);


--
-- Name: clinical_notes clinical_notes_tenant_id_appointment_id_note_version_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clinical_notes
    ADD CONSTRAINT clinical_notes_tenant_id_appointment_id_note_version_key UNIQUE (tenant_id, appointment_id, note_version);


--
-- Name: clinical_notes clinical_notes_tenant_id_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clinical_notes
    ADD CONSTRAINT clinical_notes_tenant_id_id_key UNIQUE (tenant_id, id);


--
-- Name: clinical_notes clinical_notes_tenant_id_id_patient_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clinical_notes
    ADD CONSTRAINT clinical_notes_tenant_id_id_patient_id_key UNIQUE (tenant_id, id, patient_id);


--
-- Name: consent_records consent_records_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.consent_records
    ADD CONSTRAINT consent_records_pkey PRIMARY KEY (id);


--
-- Name: consent_records consent_records_tenant_id_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.consent_records
    ADD CONSTRAINT consent_records_tenant_id_id_key UNIQUE (tenant_id, id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: idempotency_requests idempotency_requests_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.idempotency_requests
    ADD CONSTRAINT idempotency_requests_pkey PRIMARY KEY (id);


--
-- Name: idempotency_requests idempotency_requests_tenant_id_actor_id_operation_key_hash_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.idempotency_requests
    ADD CONSTRAINT idempotency_requests_tenant_id_actor_id_operation_key_hash_key UNIQUE (tenant_id, actor_id, operation, key_hash);


--
-- Name: idempotency_requests idempotency_requests_tenant_id_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.idempotency_requests
    ADD CONSTRAINT idempotency_requests_tenant_id_id_key UNIQUE (tenant_id, id);


--
-- Name: intake_submissions intake_submissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.intake_submissions
    ADD CONSTRAINT intake_submissions_pkey PRIMARY KEY (id);


--
-- Name: intake_submissions intake_submissions_tenant_id_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.intake_submissions
    ADD CONSTRAINT intake_submissions_tenant_id_id_key UNIQUE (tenant_id, id);


--
-- Name: intake_submissions intake_submissions_tenant_id_id_patient_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.intake_submissions
    ADD CONSTRAINT intake_submissions_tenant_id_id_patient_id_key UNIQUE (tenant_id, id, patient_id);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: notification_deliveries notification_deliveries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_deliveries
    ADD CONSTRAINT notification_deliveries_pkey PRIMARY KEY (id);


--
-- Name: notification_deliveries notification_deliveries_tenant_id_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_deliveries
    ADD CONSTRAINT notification_deliveries_tenant_id_id_key UNIQUE (tenant_id, id);


--
-- Name: notification_deliveries notification_deliveries_tenant_id_outbox_message_id_channel_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_deliveries
    ADD CONSTRAINT notification_deliveries_tenant_id_outbox_message_id_channel_key UNIQUE (tenant_id, outbox_message_id, channel);


--
-- Name: operatories operatories_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.operatories
    ADD CONSTRAINT operatories_pkey PRIMARY KEY (id);


--
-- Name: operatories operatories_tenant_id_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.operatories
    ADD CONSTRAINT operatories_tenant_id_id_key UNIQUE (tenant_id, id);


--
-- Name: operatories operatories_tenant_id_name_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.operatories
    ADD CONSTRAINT operatories_tenant_id_name_key UNIQUE (tenant_id, name);


--
-- Name: operatory_services operatory_services_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.operatory_services
    ADD CONSTRAINT operatory_services_pkey PRIMARY KEY (id);


--
-- Name: operatory_services operatory_services_tenant_id_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.operatory_services
    ADD CONSTRAINT operatory_services_tenant_id_id_key UNIQUE (tenant_id, id);


--
-- Name: operatory_services operatory_services_tenant_id_operatory_id_service_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.operatory_services
    ADD CONSTRAINT operatory_services_tenant_id_operatory_id_service_id_key UNIQUE (tenant_id, operatory_id, service_id);


--
-- Name: outbox_messages outbox_messages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.outbox_messages
    ADD CONSTRAINT outbox_messages_pkey PRIMARY KEY (id);


--
-- Name: outbox_messages outbox_messages_tenant_id_deduplication_key_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.outbox_messages
    ADD CONSTRAINT outbox_messages_tenant_id_deduplication_key_key UNIQUE (tenant_id, deduplication_key);


--
-- Name: outbox_messages outbox_messages_tenant_id_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.outbox_messages
    ADD CONSTRAINT outbox_messages_tenant_id_id_key UNIQUE (tenant_id, id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (tenant_id, email);


--
-- Name: patient_profiles patient_profiles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.patient_profiles
    ADD CONSTRAINT patient_profiles_pkey PRIMARY KEY (id);


--
-- Name: patient_profiles patient_profiles_tenant_id_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.patient_profiles
    ADD CONSTRAINT patient_profiles_tenant_id_id_key UNIQUE (tenant_id, id);


--
-- Name: patient_profiles patient_profiles_tenant_id_user_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.patient_profiles
    ADD CONSTRAINT patient_profiles_tenant_id_user_id_key UNIQUE (tenant_id, user_id);


--
-- Name: platform_admins platform_admins_email_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.platform_admins
    ADD CONSTRAINT platform_admins_email_key UNIQUE (email);


--
-- Name: platform_admins platform_admins_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.platform_admins
    ADD CONSTRAINT platform_admins_pkey PRIMARY KEY (id);


--
-- Name: platform_audit_events platform_audit_events_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.platform_audit_events
    ADD CONSTRAINT platform_audit_events_pkey PRIMARY KEY (id);


--
-- Name: practitioner_breaks practitioner_breaks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.practitioner_breaks
    ADD CONSTRAINT practitioner_breaks_pkey PRIMARY KEY (id);


--
-- Name: practitioner_breaks practitioner_breaks_tenant_id_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.practitioner_breaks
    ADD CONSTRAINT practitioner_breaks_tenant_id_id_key UNIQUE (tenant_id, id);


--
-- Name: practitioner_services practitioner_services_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.practitioner_services
    ADD CONSTRAINT practitioner_services_pkey PRIMARY KEY (id);


--
-- Name: practitioner_services practitioner_services_tenant_id_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.practitioner_services
    ADD CONSTRAINT practitioner_services_tenant_id_id_key UNIQUE (tenant_id, id);


--
-- Name: practitioner_services practitioner_services_tenant_id_practitioner_id_service_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.practitioner_services
    ADD CONSTRAINT practitioner_services_tenant_id_practitioner_id_service_id_key UNIQUE (tenant_id, practitioner_id, service_id);


--
-- Name: practitioner_shifts practitioner_shifts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.practitioner_shifts
    ADD CONSTRAINT practitioner_shifts_pkey PRIMARY KEY (id);


--
-- Name: practitioner_shifts practitioner_shifts_tenant_id_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.practitioner_shifts
    ADD CONSTRAINT practitioner_shifts_tenant_id_id_key UNIQUE (tenant_id, id);


--
-- Name: practitioners practitioners_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.practitioners
    ADD CONSTRAINT practitioners_pkey PRIMARY KEY (id);


--
-- Name: practitioners practitioners_tenant_id_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.practitioners
    ADD CONSTRAINT practitioners_tenant_id_id_key UNIQUE (tenant_id, id);


--
-- Name: practitioners practitioners_tenant_id_user_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.practitioners
    ADD CONSTRAINT practitioners_tenant_id_user_id_key UNIQUE (tenant_id, user_id);


--
-- Name: schedule_blocks schedule_blocks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.schedule_blocks
    ADD CONSTRAINT schedule_blocks_pkey PRIMARY KEY (id);


--
-- Name: schedule_blocks schedule_blocks_tenant_id_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.schedule_blocks
    ADD CONSTRAINT schedule_blocks_tenant_id_id_key UNIQUE (tenant_id, id);


--
-- Name: services services_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.services
    ADD CONSTRAINT services_pkey PRIMARY KEY (id);


--
-- Name: services services_tenant_id_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.services
    ADD CONSTRAINT services_tenant_id_id_key UNIQUE (tenant_id, id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: shopify_connections shopify_connections_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.shopify_connections
    ADD CONSTRAINT shopify_connections_pkey PRIMARY KEY (id);


--
-- Name: shopify_connections shopify_connections_shop_host_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.shopify_connections
    ADD CONSTRAINT shopify_connections_shop_host_unique UNIQUE (shop_host);


--
-- Name: shopify_connections shopify_connections_tenant_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.shopify_connections
    ADD CONSTRAINT shopify_connections_tenant_id_unique UNIQUE (tenant_id);


--
-- Name: shopping_carts shopping_carts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.shopping_carts
    ADD CONSTRAINT shopping_carts_pkey PRIMARY KEY (id);


--
-- Name: shopping_carts shopping_carts_tenant_id_session_hash_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.shopping_carts
    ADD CONSTRAINT shopping_carts_tenant_id_session_hash_unique UNIQUE (tenant_id, session_hash);


--
-- Name: staff_invitations staff_invitations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.staff_invitations
    ADD CONSTRAINT staff_invitations_pkey PRIMARY KEY (id);


--
-- Name: staff_invitations staff_invitations_tenant_id_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.staff_invitations
    ADD CONSTRAINT staff_invitations_tenant_id_id_key UNIQUE (tenant_id, id);


--
-- Name: staff_invitations staff_invitations_token_hash_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.staff_invitations
    ADD CONSTRAINT staff_invitations_token_hash_key UNIQUE (token_hash);


--
-- Name: subscription_items subscription_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscription_items
    ADD CONSTRAINT subscription_items_pkey PRIMARY KEY (id);


--
-- Name: subscription_items subscription_items_stripe_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscription_items
    ADD CONSTRAINT subscription_items_stripe_id_unique UNIQUE (stripe_id);


--
-- Name: subscriptions subscriptions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscriptions
    ADD CONSTRAINT subscriptions_pkey PRIMARY KEY (id);


--
-- Name: subscriptions subscriptions_stripe_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscriptions
    ADD CONSTRAINT subscriptions_stripe_id_unique UNIQUE (stripe_id);


--
-- Name: tenants tenants_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tenants
    ADD CONSTRAINT tenants_pkey PRIMARY KEY (id);


--
-- Name: tenants tenants_stripe_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tenants
    ADD CONSTRAINT tenants_stripe_id_key UNIQUE (stripe_id);


--
-- Name: tenants tenants_subdomain_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tenants
    ADD CONSTRAINT tenants_subdomain_key UNIQUE (subdomain);


--
-- Name: user_roles user_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_roles
    ADD CONSTRAINT user_roles_pkey PRIMARY KEY (id);


--
-- Name: user_roles user_roles_tenant_id_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_roles
    ADD CONSTRAINT user_roles_tenant_id_id_key UNIQUE (tenant_id, id);


--
-- Name: user_roles user_roles_tenant_id_user_id_role_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_roles
    ADD CONSTRAINT user_roles_tenant_id_user_id_role_key UNIQUE (tenant_id, user_id, role);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: users users_tenant_id_email_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_tenant_id_email_key UNIQUE (tenant_id, email);


--
-- Name: users users_tenant_id_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_tenant_id_id_key UNIQUE (tenant_id, id);


--
-- Name: webhook_events webhook_events_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.webhook_events
    ADD CONSTRAINT webhook_events_pkey PRIMARY KEY (id);


--
-- Name: webhook_events webhook_events_provider_provider_event_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.webhook_events
    ADD CONSTRAINT webhook_events_provider_provider_event_id_unique UNIQUE (provider, provider_event_id);


--
-- Name: appointments_patient_start; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX appointments_patient_start ON public.appointments USING btree (tenant_id, patient_id, start_at);


--
-- Name: appointments_tenant_start; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX appointments_tenant_start ON public.appointments USING btree (tenant_id, start_at);


--
-- Name: cache_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration);


--
-- Name: cache_locks_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration);


--
-- Name: clinic_hours_weekday; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX clinic_hours_weekday ON public.clinic_hours USING btree (tenant_id, weekday);


--
-- Name: failed_jobs_connection_queue_failed_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX failed_jobs_connection_queue_failed_at_index ON public.failed_jobs USING btree (connection, queue, failed_at);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: outbox_pending; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX outbox_pending ON public.outbox_messages USING btree (state, available_at);


--
-- Name: platform_audit_events_tenant_id_occurred_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX platform_audit_events_tenant_id_occurred_at_index ON public.platform_audit_events USING btree (tenant_id, occurred_at);


--
-- Name: practitioner_breaks_weekday; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX practitioner_breaks_weekday ON public.practitioner_breaks USING btree (tenant_id, weekday);


--
-- Name: practitioner_shifts_weekday; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX practitioner_shifts_weekday ON public.practitioner_shifts USING btree (tenant_id, weekday);


--
-- Name: sessions_last_activity; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_last_activity ON public.sessions USING btree (last_activity);


--
-- Name: subscriptions_tenant_id_stripe_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX subscriptions_tenant_id_stripe_status_index ON public.subscriptions USING btree (tenant_id, stripe_status);


--
-- Name: webhook_events_state_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX webhook_events_state_index ON public.webhook_events USING btree (state);


--
-- Name: appointment_events appointment_events_append_only; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER appointment_events_append_only BEFORE DELETE OR UPDATE ON public.appointment_events FOR EACH ROW EXECUTE FUNCTION public.smilesuite_append_only();


--
-- Name: appointment_events appointment_events_immutable_tenant; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER appointment_events_immutable_tenant BEFORE UPDATE ON public.appointment_events FOR EACH ROW EXECUTE FUNCTION public.smilesuite_immutable_tenant();


--
-- Name: appointments appointments_immutable_tenant; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER appointments_immutable_tenant BEFORE UPDATE ON public.appointments FOR EACH ROW EXECUTE FUNCTION public.smilesuite_immutable_tenant();


--
-- Name: audit_events audit_events_append_only; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_events_append_only BEFORE DELETE OR UPDATE ON public.audit_events FOR EACH ROW EXECUTE FUNCTION public.smilesuite_append_only();


--
-- Name: audit_events audit_events_immutable_tenant; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER audit_events_immutable_tenant BEFORE UPDATE ON public.audit_events FOR EACH ROW EXECUTE FUNCTION public.smilesuite_immutable_tenant();


--
-- Name: billing_checkout_sessions billing_checkout_sessions_immutable_tenant; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER billing_checkout_sessions_immutable_tenant BEFORE UPDATE ON public.billing_checkout_sessions FOR EACH ROW EXECUTE FUNCTION public.smilesuite_immutable_tenant();


--
-- Name: billing_entitlements billing_entitlements_immutable_tenant; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER billing_entitlements_immutable_tenant BEFORE UPDATE ON public.billing_entitlements FOR EACH ROW EXECUTE FUNCTION public.smilesuite_immutable_tenant();


--
-- Name: care_assignments care_assignments_immutable_tenant; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER care_assignments_immutable_tenant BEFORE UPDATE ON public.care_assignments FOR EACH ROW EXECUTE FUNCTION public.smilesuite_immutable_tenant();


--
-- Name: clinic_hours clinic_hours_immutable_tenant; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER clinic_hours_immutable_tenant BEFORE UPDATE ON public.clinic_hours FOR EACH ROW EXECUTE FUNCTION public.smilesuite_immutable_tenant();


--
-- Name: clinical_notes clinical_notes_append_only; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER clinical_notes_append_only BEFORE DELETE OR UPDATE ON public.clinical_notes FOR EACH ROW EXECUTE FUNCTION public.smilesuite_append_only();


--
-- Name: clinical_notes clinical_notes_immutable_tenant; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER clinical_notes_immutable_tenant BEFORE UPDATE ON public.clinical_notes FOR EACH ROW EXECUTE FUNCTION public.smilesuite_immutable_tenant();


--
-- Name: consent_records consent_records_immutable_tenant; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER consent_records_immutable_tenant BEFORE UPDATE ON public.consent_records FOR EACH ROW EXECUTE FUNCTION public.smilesuite_immutable_tenant();


--
-- Name: idempotency_requests idempotency_requests_immutable_tenant; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER idempotency_requests_immutable_tenant BEFORE UPDATE ON public.idempotency_requests FOR EACH ROW EXECUTE FUNCTION public.smilesuite_immutable_tenant();


--
-- Name: intake_submissions intake_submissions_append_only; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER intake_submissions_append_only BEFORE DELETE OR UPDATE ON public.intake_submissions FOR EACH ROW EXECUTE FUNCTION public.smilesuite_append_only();


--
-- Name: intake_submissions intake_submissions_immutable_tenant; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER intake_submissions_immutable_tenant BEFORE UPDATE ON public.intake_submissions FOR EACH ROW EXECUTE FUNCTION public.smilesuite_immutable_tenant();


--
-- Name: notification_deliveries notification_deliveries_immutable_tenant; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER notification_deliveries_immutable_tenant BEFORE UPDATE ON public.notification_deliveries FOR EACH ROW EXECUTE FUNCTION public.smilesuite_immutable_tenant();


--
-- Name: operatories operatories_immutable_tenant; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER operatories_immutable_tenant BEFORE UPDATE ON public.operatories FOR EACH ROW EXECUTE FUNCTION public.smilesuite_immutable_tenant();


--
-- Name: operatory_services operatory_services_immutable_tenant; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER operatory_services_immutable_tenant BEFORE UPDATE ON public.operatory_services FOR EACH ROW EXECUTE FUNCTION public.smilesuite_immutable_tenant();


--
-- Name: outbox_messages outbox_messages_immutable_tenant; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER outbox_messages_immutable_tenant BEFORE UPDATE ON public.outbox_messages FOR EACH ROW EXECUTE FUNCTION public.smilesuite_immutable_tenant();


--
-- Name: patient_profiles patient_profiles_immutable_tenant; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER patient_profiles_immutable_tenant BEFORE UPDATE ON public.patient_profiles FOR EACH ROW EXECUTE FUNCTION public.smilesuite_immutable_tenant();


--
-- Name: platform_audit_events platform_audit_append_only; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER platform_audit_append_only BEFORE DELETE OR UPDATE ON public.platform_audit_events FOR EACH ROW EXECUTE FUNCTION public.prevent_platform_audit_mutation();


--
-- Name: practitioner_breaks practitioner_breaks_immutable_tenant; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER practitioner_breaks_immutable_tenant BEFORE UPDATE ON public.practitioner_breaks FOR EACH ROW EXECUTE FUNCTION public.smilesuite_immutable_tenant();


--
-- Name: practitioner_services practitioner_services_immutable_tenant; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER practitioner_services_immutable_tenant BEFORE UPDATE ON public.practitioner_services FOR EACH ROW EXECUTE FUNCTION public.smilesuite_immutable_tenant();


--
-- Name: practitioner_shifts practitioner_shifts_immutable_tenant; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER practitioner_shifts_immutable_tenant BEFORE UPDATE ON public.practitioner_shifts FOR EACH ROW EXECUTE FUNCTION public.smilesuite_immutable_tenant();


--
-- Name: practitioners practitioners_immutable_tenant; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER practitioners_immutable_tenant BEFORE UPDATE ON public.practitioners FOR EACH ROW EXECUTE FUNCTION public.smilesuite_immutable_tenant();


--
-- Name: schedule_blocks schedule_blocks_immutable_tenant; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER schedule_blocks_immutable_tenant BEFORE UPDATE ON public.schedule_blocks FOR EACH ROW EXECUTE FUNCTION public.smilesuite_immutable_tenant();


--
-- Name: services services_immutable_tenant; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER services_immutable_tenant BEFORE UPDATE ON public.services FOR EACH ROW EXECUTE FUNCTION public.smilesuite_immutable_tenant();


--
-- Name: shopify_connections shopify_connections_immutable_tenant; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER shopify_connections_immutable_tenant BEFORE UPDATE ON public.shopify_connections FOR EACH ROW EXECUTE FUNCTION public.smilesuite_immutable_tenant();


--
-- Name: shopping_carts shopping_carts_immutable_tenant; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER shopping_carts_immutable_tenant BEFORE UPDATE ON public.shopping_carts FOR EACH ROW EXECUTE FUNCTION public.smilesuite_immutable_tenant();


--
-- Name: staff_invitations staff_invitations_immutable_tenant; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER staff_invitations_immutable_tenant BEFORE UPDATE ON public.staff_invitations FOR EACH ROW EXECUTE FUNCTION public.smilesuite_immutable_tenant();


--
-- Name: subscription_items subscription_items_immutable_owner; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER subscription_items_immutable_owner BEFORE UPDATE ON public.subscription_items FOR EACH ROW EXECUTE FUNCTION public.smilesuite_immutable_subscription();


--
-- Name: subscriptions subscriptions_immutable_tenant; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER subscriptions_immutable_tenant BEFORE UPDATE ON public.subscriptions FOR EACH ROW EXECUTE FUNCTION public.smilesuite_immutable_tenant();


--
-- Name: user_roles user_roles_immutable_tenant; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER user_roles_immutable_tenant BEFORE UPDATE ON public.user_roles FOR EACH ROW EXECUTE FUNCTION public.smilesuite_immutable_tenant();


--
-- Name: users users_immutable_tenant; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER users_immutable_tenant BEFORE UPDATE ON public.users FOR EACH ROW EXECUTE FUNCTION public.smilesuite_immutable_tenant();


--
-- Name: appointment_events appointment_events_tenant_id_actor_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.appointment_events
    ADD CONSTRAINT appointment_events_tenant_id_actor_id_fkey FOREIGN KEY (tenant_id, actor_id) REFERENCES public.users(tenant_id, id) ON DELETE RESTRICT;


--
-- Name: appointment_events appointment_events_tenant_id_appointment_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.appointment_events
    ADD CONSTRAINT appointment_events_tenant_id_appointment_id_fkey FOREIGN KEY (tenant_id, appointment_id) REFERENCES public.appointments(tenant_id, id) ON DELETE RESTRICT;


--
-- Name: appointment_events appointment_events_tenant_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.appointment_events
    ADD CONSTRAINT appointment_events_tenant_id_fkey FOREIGN KEY (tenant_id) REFERENCES public.tenants(id);


--
-- Name: appointments appointments_tenant_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.appointments
    ADD CONSTRAINT appointments_tenant_id_fkey FOREIGN KEY (tenant_id) REFERENCES public.tenants(id);


--
-- Name: appointments appointments_tenant_id_operatory_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.appointments
    ADD CONSTRAINT appointments_tenant_id_operatory_id_fkey FOREIGN KEY (tenant_id, operatory_id) REFERENCES public.operatories(tenant_id, id) ON DELETE RESTRICT;


--
-- Name: appointments appointments_tenant_id_patient_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.appointments
    ADD CONSTRAINT appointments_tenant_id_patient_id_fkey FOREIGN KEY (tenant_id, patient_id) REFERENCES public.patient_profiles(tenant_id, id) ON DELETE RESTRICT;


--
-- Name: appointments appointments_tenant_id_practitioner_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.appointments
    ADD CONSTRAINT appointments_tenant_id_practitioner_id_fkey FOREIGN KEY (tenant_id, practitioner_id) REFERENCES public.practitioners(tenant_id, id) ON DELETE RESTRICT;


--
-- Name: appointments appointments_tenant_id_service_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.appointments
    ADD CONSTRAINT appointments_tenant_id_service_id_fkey FOREIGN KEY (tenant_id, service_id) REFERENCES public.services(tenant_id, id) ON DELETE RESTRICT;


--
-- Name: audit_events audit_events_tenant_id_actor_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_events
    ADD CONSTRAINT audit_events_tenant_id_actor_id_fkey FOREIGN KEY (tenant_id, actor_id) REFERENCES public.users(tenant_id, id) ON DELETE RESTRICT;


--
-- Name: audit_events audit_events_tenant_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_events
    ADD CONSTRAINT audit_events_tenant_id_fkey FOREIGN KEY (tenant_id) REFERENCES public.tenants(id);


--
-- Name: billing_checkout_sessions billing_checkout_sessions_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.billing_checkout_sessions
    ADD CONSTRAINT billing_checkout_sessions_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE RESTRICT;


--
-- Name: billing_entitlements billing_entitlements_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.billing_entitlements
    ADD CONSTRAINT billing_entitlements_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE RESTRICT;


--
-- Name: care_assignments care_assignments_tenant_id_appointment_id_patient_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.care_assignments
    ADD CONSTRAINT care_assignments_tenant_id_appointment_id_patient_id_fkey FOREIGN KEY (tenant_id, appointment_id, patient_id) REFERENCES public.appointments(tenant_id, id, patient_id) ON DELETE RESTRICT;


--
-- Name: care_assignments care_assignments_tenant_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.care_assignments
    ADD CONSTRAINT care_assignments_tenant_id_fkey FOREIGN KEY (tenant_id) REFERENCES public.tenants(id);


--
-- Name: care_assignments care_assignments_tenant_id_granted_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.care_assignments
    ADD CONSTRAINT care_assignments_tenant_id_granted_by_fkey FOREIGN KEY (tenant_id, granted_by) REFERENCES public.users(tenant_id, id) ON DELETE RESTRICT;


--
-- Name: care_assignments care_assignments_tenant_id_patient_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.care_assignments
    ADD CONSTRAINT care_assignments_tenant_id_patient_id_fkey FOREIGN KEY (tenant_id, patient_id) REFERENCES public.patient_profiles(tenant_id, id) ON DELETE RESTRICT;


--
-- Name: care_assignments care_assignments_tenant_id_practitioner_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.care_assignments
    ADD CONSTRAINT care_assignments_tenant_id_practitioner_id_fkey FOREIGN KEY (tenant_id, practitioner_id) REFERENCES public.practitioners(tenant_id, id) ON DELETE RESTRICT;


--
-- Name: clinic_hours clinic_hours_tenant_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clinic_hours
    ADD CONSTRAINT clinic_hours_tenant_id_fkey FOREIGN KEY (tenant_id) REFERENCES public.tenants(id);


--
-- Name: clinical_notes clinical_notes_tenant_id_appointment_id_patient_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clinical_notes
    ADD CONSTRAINT clinical_notes_tenant_id_appointment_id_patient_id_fkey FOREIGN KEY (tenant_id, appointment_id, patient_id) REFERENCES public.appointments(tenant_id, id, patient_id) ON DELETE RESTRICT;


--
-- Name: clinical_notes clinical_notes_tenant_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clinical_notes
    ADD CONSTRAINT clinical_notes_tenant_id_fkey FOREIGN KEY (tenant_id) REFERENCES public.tenants(id);


--
-- Name: clinical_notes clinical_notes_tenant_id_patient_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clinical_notes
    ADD CONSTRAINT clinical_notes_tenant_id_patient_id_fkey FOREIGN KEY (tenant_id, patient_id) REFERENCES public.patient_profiles(tenant_id, id) ON DELETE RESTRICT;


--
-- Name: clinical_notes clinical_notes_tenant_id_practitioner_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clinical_notes
    ADD CONSTRAINT clinical_notes_tenant_id_practitioner_id_fkey FOREIGN KEY (tenant_id, practitioner_id) REFERENCES public.practitioners(tenant_id, id) ON DELETE RESTRICT;


--
-- Name: clinical_notes clinical_notes_tenant_id_supersedes_id_patient_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clinical_notes
    ADD CONSTRAINT clinical_notes_tenant_id_supersedes_id_patient_id_fkey FOREIGN KEY (tenant_id, supersedes_id, patient_id) REFERENCES public.clinical_notes(tenant_id, id, patient_id) ON DELETE RESTRICT;


--
-- Name: consent_records consent_records_tenant_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.consent_records
    ADD CONSTRAINT consent_records_tenant_id_fkey FOREIGN KEY (tenant_id) REFERENCES public.tenants(id);


--
-- Name: consent_records consent_records_tenant_id_patient_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.consent_records
    ADD CONSTRAINT consent_records_tenant_id_patient_id_fkey FOREIGN KEY (tenant_id, patient_id) REFERENCES public.patient_profiles(tenant_id, id) ON DELETE RESTRICT;


--
-- Name: consent_records consent_records_tenant_id_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.consent_records
    ADD CONSTRAINT consent_records_tenant_id_user_id_fkey FOREIGN KEY (tenant_id, user_id) REFERENCES public.users(tenant_id, id) ON DELETE RESTRICT;


--
-- Name: idempotency_requests idempotency_requests_tenant_id_actor_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.idempotency_requests
    ADD CONSTRAINT idempotency_requests_tenant_id_actor_id_fkey FOREIGN KEY (tenant_id, actor_id) REFERENCES public.users(tenant_id, id) ON DELETE RESTRICT;


--
-- Name: idempotency_requests idempotency_requests_tenant_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.idempotency_requests
    ADD CONSTRAINT idempotency_requests_tenant_id_fkey FOREIGN KEY (tenant_id) REFERENCES public.tenants(id);


--
-- Name: idempotency_requests idempotency_requests_tenant_id_result_resource_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.idempotency_requests
    ADD CONSTRAINT idempotency_requests_tenant_id_result_resource_id_fkey FOREIGN KEY (tenant_id, result_resource_id) REFERENCES public.appointments(tenant_id, id) ON DELETE RESTRICT;


--
-- Name: intake_submissions intake_submissions_tenant_id_appointment_id_patient_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.intake_submissions
    ADD CONSTRAINT intake_submissions_tenant_id_appointment_id_patient_id_fkey FOREIGN KEY (tenant_id, appointment_id, patient_id) REFERENCES public.appointments(tenant_id, id, patient_id) ON DELETE RESTRICT;


--
-- Name: intake_submissions intake_submissions_tenant_id_author_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.intake_submissions
    ADD CONSTRAINT intake_submissions_tenant_id_author_id_fkey FOREIGN KEY (tenant_id, author_id) REFERENCES public.users(tenant_id, id) ON DELETE RESTRICT;


--
-- Name: intake_submissions intake_submissions_tenant_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.intake_submissions
    ADD CONSTRAINT intake_submissions_tenant_id_fkey FOREIGN KEY (tenant_id) REFERENCES public.tenants(id);


--
-- Name: intake_submissions intake_submissions_tenant_id_patient_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.intake_submissions
    ADD CONSTRAINT intake_submissions_tenant_id_patient_id_fkey FOREIGN KEY (tenant_id, patient_id) REFERENCES public.patient_profiles(tenant_id, id) ON DELETE RESTRICT;


--
-- Name: intake_submissions intake_submissions_tenant_id_supersedes_id_patient_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.intake_submissions
    ADD CONSTRAINT intake_submissions_tenant_id_supersedes_id_patient_id_fkey FOREIGN KEY (tenant_id, supersedes_id, patient_id) REFERENCES public.intake_submissions(tenant_id, id, patient_id) ON DELETE RESTRICT;


--
-- Name: notification_deliveries notification_deliveries_tenant_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_deliveries
    ADD CONSTRAINT notification_deliveries_tenant_id_fkey FOREIGN KEY (tenant_id) REFERENCES public.tenants(id);


--
-- Name: notification_deliveries notification_deliveries_tenant_id_outbox_message_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_deliveries
    ADD CONSTRAINT notification_deliveries_tenant_id_outbox_message_id_fkey FOREIGN KEY (tenant_id, outbox_message_id) REFERENCES public.outbox_messages(tenant_id, id) ON DELETE RESTRICT;


--
-- Name: operatories operatories_tenant_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.operatories
    ADD CONSTRAINT operatories_tenant_id_fkey FOREIGN KEY (tenant_id) REFERENCES public.tenants(id);


--
-- Name: operatory_services operatory_services_tenant_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.operatory_services
    ADD CONSTRAINT operatory_services_tenant_id_fkey FOREIGN KEY (tenant_id) REFERENCES public.tenants(id);


--
-- Name: operatory_services operatory_services_tenant_id_operatory_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.operatory_services
    ADD CONSTRAINT operatory_services_tenant_id_operatory_id_fkey FOREIGN KEY (tenant_id, operatory_id) REFERENCES public.operatories(tenant_id, id) ON DELETE RESTRICT;


--
-- Name: operatory_services operatory_services_tenant_id_service_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.operatory_services
    ADD CONSTRAINT operatory_services_tenant_id_service_id_fkey FOREIGN KEY (tenant_id, service_id) REFERENCES public.services(tenant_id, id) ON DELETE RESTRICT;


--
-- Name: outbox_messages outbox_messages_tenant_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.outbox_messages
    ADD CONSTRAINT outbox_messages_tenant_id_fkey FOREIGN KEY (tenant_id) REFERENCES public.tenants(id);


--
-- Name: outbox_messages outbox_messages_tenant_id_resource_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.outbox_messages
    ADD CONSTRAINT outbox_messages_tenant_id_resource_id_fkey FOREIGN KEY (tenant_id, resource_id) REFERENCES public.appointments(tenant_id, id) ON DELETE RESTRICT;


--
-- Name: password_reset_tokens password_reset_tokens_tenant_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_tenant_id_fkey FOREIGN KEY (tenant_id) REFERENCES public.tenants(id);


--
-- Name: patient_profiles patient_profiles_tenant_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.patient_profiles
    ADD CONSTRAINT patient_profiles_tenant_id_fkey FOREIGN KEY (tenant_id) REFERENCES public.tenants(id);


--
-- Name: patient_profiles patient_profiles_tenant_id_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.patient_profiles
    ADD CONSTRAINT patient_profiles_tenant_id_user_id_fkey FOREIGN KEY (tenant_id, user_id) REFERENCES public.users(tenant_id, id) ON DELETE RESTRICT;


--
-- Name: platform_audit_events platform_audit_events_platform_admin_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.platform_audit_events
    ADD CONSTRAINT platform_audit_events_platform_admin_id_foreign FOREIGN KEY (platform_admin_id) REFERENCES public.platform_admins(id) ON DELETE RESTRICT;


--
-- Name: platform_audit_events platform_audit_events_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.platform_audit_events
    ADD CONSTRAINT platform_audit_events_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE RESTRICT;


--
-- Name: practitioner_breaks practitioner_breaks_tenant_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.practitioner_breaks
    ADD CONSTRAINT practitioner_breaks_tenant_id_fkey FOREIGN KEY (tenant_id) REFERENCES public.tenants(id);


--
-- Name: practitioner_breaks practitioner_breaks_tenant_id_practitioner_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.practitioner_breaks
    ADD CONSTRAINT practitioner_breaks_tenant_id_practitioner_id_fkey FOREIGN KEY (tenant_id, practitioner_id) REFERENCES public.practitioners(tenant_id, id) ON DELETE RESTRICT;


--
-- Name: practitioner_services practitioner_services_tenant_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.practitioner_services
    ADD CONSTRAINT practitioner_services_tenant_id_fkey FOREIGN KEY (tenant_id) REFERENCES public.tenants(id);


--
-- Name: practitioner_services practitioner_services_tenant_id_practitioner_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.practitioner_services
    ADD CONSTRAINT practitioner_services_tenant_id_practitioner_id_fkey FOREIGN KEY (tenant_id, practitioner_id) REFERENCES public.practitioners(tenant_id, id) ON DELETE RESTRICT;


--
-- Name: practitioner_services practitioner_services_tenant_id_service_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.practitioner_services
    ADD CONSTRAINT practitioner_services_tenant_id_service_id_fkey FOREIGN KEY (tenant_id, service_id) REFERENCES public.services(tenant_id, id) ON DELETE RESTRICT;


--
-- Name: practitioner_shifts practitioner_shifts_tenant_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.practitioner_shifts
    ADD CONSTRAINT practitioner_shifts_tenant_id_fkey FOREIGN KEY (tenant_id) REFERENCES public.tenants(id);


--
-- Name: practitioner_shifts practitioner_shifts_tenant_id_practitioner_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.practitioner_shifts
    ADD CONSTRAINT practitioner_shifts_tenant_id_practitioner_id_fkey FOREIGN KEY (tenant_id, practitioner_id) REFERENCES public.practitioners(tenant_id, id) ON DELETE RESTRICT;


--
-- Name: practitioners practitioners_tenant_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.practitioners
    ADD CONSTRAINT practitioners_tenant_id_fkey FOREIGN KEY (tenant_id) REFERENCES public.tenants(id);


--
-- Name: practitioners practitioners_tenant_id_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.practitioners
    ADD CONSTRAINT practitioners_tenant_id_user_id_fkey FOREIGN KEY (tenant_id, user_id) REFERENCES public.users(tenant_id, id) ON DELETE RESTRICT;


--
-- Name: schedule_blocks schedule_blocks_tenant_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.schedule_blocks
    ADD CONSTRAINT schedule_blocks_tenant_id_fkey FOREIGN KEY (tenant_id) REFERENCES public.tenants(id);


--
-- Name: schedule_blocks schedule_blocks_tenant_id_operatory_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.schedule_blocks
    ADD CONSTRAINT schedule_blocks_tenant_id_operatory_id_fkey FOREIGN KEY (tenant_id, operatory_id) REFERENCES public.operatories(tenant_id, id) ON DELETE RESTRICT;


--
-- Name: schedule_blocks schedule_blocks_tenant_id_practitioner_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.schedule_blocks
    ADD CONSTRAINT schedule_blocks_tenant_id_practitioner_id_fkey FOREIGN KEY (tenant_id, practitioner_id) REFERENCES public.practitioners(tenant_id, id) ON DELETE RESTRICT;


--
-- Name: services services_tenant_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.services
    ADD CONSTRAINT services_tenant_id_fkey FOREIGN KEY (tenant_id) REFERENCES public.tenants(id);


--
-- Name: shopify_connections shopify_connections_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.shopify_connections
    ADD CONSTRAINT shopify_connections_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE RESTRICT;


--
-- Name: shopping_carts shopping_carts_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.shopping_carts
    ADD CONSTRAINT shopping_carts_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE RESTRICT;


--
-- Name: staff_invitations staff_invitations_tenant_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.staff_invitations
    ADD CONSTRAINT staff_invitations_tenant_id_fkey FOREIGN KEY (tenant_id) REFERENCES public.tenants(id);


--
-- Name: staff_invitations staff_invitations_tenant_id_invited_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.staff_invitations
    ADD CONSTRAINT staff_invitations_tenant_id_invited_by_fkey FOREIGN KEY (tenant_id, invited_by) REFERENCES public.users(tenant_id, id) ON DELETE RESTRICT;


--
-- Name: subscription_items subscription_items_subscription_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscription_items
    ADD CONSTRAINT subscription_items_subscription_id_foreign FOREIGN KEY (subscription_id) REFERENCES public.subscriptions(id) ON DELETE RESTRICT;


--
-- Name: subscriptions subscriptions_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscriptions
    ADD CONSTRAINT subscriptions_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE RESTRICT;


--
-- Name: user_roles user_roles_tenant_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_roles
    ADD CONSTRAINT user_roles_tenant_id_fkey FOREIGN KEY (tenant_id) REFERENCES public.tenants(id);


--
-- Name: user_roles user_roles_tenant_id_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_roles
    ADD CONSTRAINT user_roles_tenant_id_user_id_fkey FOREIGN KEY (tenant_id, user_id) REFERENCES public.users(tenant_id, id) ON DELETE RESTRICT;


--
-- Name: users users_tenant_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_tenant_id_fkey FOREIGN KEY (tenant_id) REFERENCES public.tenants(id);


--
-- Name: webhook_events webhook_events_tenant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.webhook_events
    ADD CONSTRAINT webhook_events_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES public.tenants(id) ON DELETE RESTRICT;


--
-- PostgreSQL database dump complete
--

\unrestrict j4smyOco0McflOM5P2zcL189cynGo3xqduQNUxqpu9OlYusoAjuMQMHSc54jtPD
