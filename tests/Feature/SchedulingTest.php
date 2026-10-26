<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\AuditEvent;
use App\Models\ClinicHour;
use App\Models\IdempotencyRequest;
use App\Models\Operatory;
use App\Models\OutboxMessage;
use App\Models\PatientProfile;
use App\Models\Practitioner;
use App\Models\PractitionerBreak;
use App\Models\PractitionerShift;
use App\Models\ScheduleBlock;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Scheduling\AvailabilityService;
use App\Scheduling\BookingService;
use App\Scheduling\ScheduleGuard;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SchedulingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $patient;

    private User $staff;

    private PatientProfile $profile;

    private Practitioner $practitioner;

    private Operatory $chair;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-10-26 00:00:00 UTC');
        Carbon::setTestNow('2026-10-26 00:00:00 UTC');
        $this->tenant = Tenant::create(['name' => 'Schedule fixture', 'subdomain' => 'schedule', 'timezone' => 'Asia/Manila', 'currency' => 'PHP', 'lifecycle_status' => 'active']);
        app()->instance(TenantContext::class, new TenantContext($this->tenant));
        DB::table('billing_entitlements')->insert(['tenant_id' => $this->tenant->id, 'tier' => 'pro', 'bookings_enabled' => true, 'over_limit' => false, 'practitioner_limit' => 10, 'chair_limit' => 10, 'reconciled_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $this->patient = $this->user('Patient', 'patient');
        $this->staff = $this->user('Reception', 'receptionist');
        $doctor = $this->user('Doctor', 'practitioner');
        $this->profile = PatientProfile::create(['user_id' => $this->patient->id, 'first_name' => 'Synthetic', 'last_name' => 'Patient']);
        $this->practitioner = Practitioner::create(['user_id' => $doctor->id, 'active' => true]);
        $this->chair = Operatory::create(['name' => 'Chair', 'active' => true]);
        $this->service = Service::create(['name' => 'Consultation', 'category' => 'General', 'duration_minutes' => 30, 'buffer_before_minutes' => 0, 'buffer_after_minutes' => 15, 'price_minor' => 150000, 'currency' => 'PHP', 'active' => true]);
        DB::table('practitioner_services')->insert(['tenant_id' => $this->tenant->id, 'practitioner_id' => $this->practitioner->id, 'service_id' => $this->service->id]);
        DB::table('operatory_services')->insert(['tenant_id' => $this->tenant->id, 'operatory_id' => $this->chair->id, 'service_id' => $this->service->id]);
        foreach (range(1, 7) as $day) {
            ClinicHour::create(['weekday' => $day, 'local_start' => '09:00', 'local_end' => '17:00']);
            PractitionerShift::create(['practitioner_id' => $this->practitioner->id, 'weekday' => $day, 'local_start' => '09:00', 'local_end' => '17:00']);
        }
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $name, string $role): User
    {
        $user = User::create(['name' => $name, 'email' => strtolower($name).'@example.test', 'password' => 'password', 'status' => 'active', 'email_verified_at' => now()]);
        DB::table('user_roles')->insert(['tenant_id' => $this->tenant->id, 'user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    private function data(string $start = '2026-10-27T10:00:00+08:00'): array
    {
        return ['patient_id' => $this->profile->id, 'service_id' => $this->service->id, 'practitioner_id' => $this->practitioner->id, 'operatory_id' => $this->chair->id, 'start_at' => $start];
    }

    private function rejects(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected scheduling validation rejection');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }

    public function test_booking_replays_identical_request_and_atomically_snapshots_and_publishes(): void
    {
        $booking = app(BookingService::class);
        $first = $booking->book($this->patient, $this->data(), 'replay-key');
        $again = $booking->book($this->patient, $this->data(), 'replay-key');
        $this->assertSame($first->id, $again->id);
        $this->assertSame('2026-10-27 02:45:00', $first->occupied_end_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(150000, $first->price_minor);
        $this->assertSame(1, Appointment::count());
        $this->assertSame(1, AppointmentEvent::count());
        $this->assertSame(1, OutboxMessage::count());
        $this->assertSame(1, IdempotencyRequest::count());
        $this->assertSame(1, AuditEvent::count());
        $this->rejects(fn () => $booking->book($this->patient, $this->data('2026-10-27T11:00:00+08:00'), 'replay-key'));
    }

    public function test_buffers_breaks_blocks_and_live_overlap_remove_slots(): void
    {
        PractitionerBreak::create(['practitioner_id' => $this->practitioner->id, 'weekday' => 2, 'local_start' => '12:00', 'local_end' => '13:00']);
        ScheduleBlock::create(['resource_type' => 'clinic', 'starts_at' => '2026-10-27 06:00:00+00', 'ends_at' => '2026-10-27 07:00:00+00']);
        app(BookingService::class)->book($this->patient, $this->data(), 'occupied');
        $slots = app(AvailabilityService::class)->slots($this->service, '2026-10-27');
        $starts = array_column($slots, 'start_at');
        $this->assertContains('2026-10-27T01:00:00Z', $starts);
        foreach (['02:00', '02:30', '03:30', '04:00', '06:00', '08:30'] as $time) {
            $this->assertNotContains('2026-10-27T'.$time.':00Z', $starts);
        }
        $this->assertContains('2026-10-27T02:45:00Z', $starts);
    }

    public function test_ownership_verification_grid_and_offsets_are_enforced_without_partial_writes(): void
    {
        $other = PatientProfile::create(['first_name' => 'Other', 'last_name' => 'Patient']);
        $booking = app(BookingService::class);
        $this->rejects(fn () => $booking->book($this->patient, array_replace($this->data(), ['patient_id' => $other->id]), 'other'));
        $this->rejects(fn () => $booking->book($this->patient, $this->data('2026-10-27T10:05:00+08:00'), 'grid'));
        $this->rejects(fn () => $booking->book($this->patient, $this->data('2026-10-27T10:00:00'), 'offset'));
        $this->patient->update(['email_verified_at' => null]);
        $this->rejects(fn () => $booking->book($this->patient, $this->data(), 'unverified'));
        $this->assertSame(0, Appointment::count());
        $this->assertSame(0, OutboxMessage::count());
    }

    public function test_state_versions_cancellation_and_schedule_guard_are_atomic(): void
    {
        $booking = app(BookingService::class);
        $appointment = $booking->book($this->patient, $this->data(), 'state');
        $this->rejects(fn () => app(ScheduleGuard::class)->mutate(fn () => $this->chair->update(['active' => false])));
        $this->assertTrue($this->chair->fresh()->active);
        $moved = $booking->reschedule($this->patient, $appointment, ['start_at' => '2026-10-28T10:00:00+08:00'], 1, 'Changed plans');
        $this->assertSame(2, $moved->version);
        $this->rejects(fn () => $booking->transition($this->patient, $moved, 'cancelled', 1, 'Cancel'));
        $cancelled = $booking->transition($this->patient, $moved, 'cancelled', 2, 'Cancel');
        $this->assertSame(3, $cancelled->version);
        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame(3, OutboxMessage::count());
        $this->assertCount(1, array_filter(app(AvailabilityService::class)->slots($this->service, '2026-10-28'), fn ($slot) => $slot['start_at'] === '2026-10-28T02:00:00Z'));
    }

    public function test_dst_gap_is_rejected_and_both_explicit_fold_offsets_are_selectable(): void
    {
        $this->tenant->update(['timezone' => 'America/New_York', 'booking_horizon_days' => 365]);
        ClinicHour::query()->update(['local_start' => '00:00', 'local_end' => '05:00']);
        PractitionerShift::query()->update(['local_start' => '00:00', 'local_end' => '05:00']);
        $this->rejects(fn () => app(BookingService::class)->book($this->staff, $this->data('2027-03-14T02:30:00-05:00'), 'gap'));
        $starts = array_column(app(AvailabilityService::class)->slots($this->service, '2026-11-01'), 'start_at');
        $this->assertContains('2026-11-01T05:30:00Z', $starts);
        $this->assertContains('2026-11-01T06:30:00Z', $starts);
    }

    public function test_lead_cancellation_and_billing_restrictions_preserve_existing_operational_access(): void
    {
        $booking = app(BookingService::class);
        // Now is 08:00 Manila; a 09:00 reservation requires a recorded staff override.
        $this->rejects(fn () => $booking->book($this->patient, $this->data('2026-10-26T09:00:00+08:00'), 'patient-lead'));
        $this->rejects(fn () => $booking->book($this->staff, $this->data('2026-10-26T09:00:00+08:00'), 'staff-lead'));
        $appointment = $booking->book($this->staff, array_replace($this->data('2026-10-26T09:00:00+08:00'), ['override_reason' => 'Walk-in request']), 'walk-in');
        $this->rejects(fn () => $booking->transition($this->patient, $appointment, 'cancelled', 1, 'Cancel'));
        $this->rejects(fn () => $booking->transition($this->staff, $appointment, 'no_show', 1));
        DB::table('billing_entitlements')->where('tenant_id', $this->tenant->id)->update(['bookings_enabled' => false]);
        $this->rejects(fn () => $booking->book($this->staff, $this->data(), 'billing'));
        $this->assertSame([], app(AvailabilityService::class)->slots($this->service, '2026-10-27'));
        $cancelled = $booking->transition($this->staff, $appointment, 'cancelled', 1, 'Walk-in cancelled');
        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame(2, AppointmentEvent::count());
    }

    public function test_resource_ineligibility_lifecycle_and_horizon_fail_closed(): void
    {
        $booking = app(BookingService::class);
        $this->rejects(fn () => $booking->book($this->patient, $this->data('2027-03-01T10:00:00+08:00'), 'horizon'));
        DB::table('operatory_services')->where('tenant_id', $this->tenant->id)->delete();
        $this->rejects(fn () => $booking->book($this->patient, $this->data(), 'ineligible'));
        DB::table('operatory_services')->insert(['tenant_id' => $this->tenant->id, 'operatory_id' => $this->chair->id, 'service_id' => $this->service->id]);
        $this->tenant->update(['lifecycle_status' => 'suspended']);
        $this->rejects(fn () => $booking->book($this->patient, $this->data(), 'suspended'));
        $this->assertSame(0, Appointment::count());
        $this->assertSame(0, AppointmentEvent::count());
        $this->assertSame(0, IdempotencyRequest::count());
    }

    public function test_grace_expiry_blocks_new_bookings_but_preserves_existing_appointment_operations(): void
    {
        DB::table('billing_entitlements')->where('tenant_id', $this->tenant->id)->update(['past_due_since' => now()->subDays(6), 'grace_until' => now()->addDay()]);
        $booking = app(BookingService::class);
        $existing = $booking->book($this->patient, $this->data(), 'future-grace');
        $this->assertNotEmpty(app(AvailabilityService::class)->slots($this->service, '2026-10-28'));
        // Persisted bookings_enabled remains true while the provider is unavailable.
        CarbonImmutable::setTestNow('2026-10-27 00:00:00 UTC');
        Carbon::setTestNow('2026-10-27 00:00:00 UTC');
        $this->rejects(fn () => $booking->book($this->staff, $this->data('2026-10-28T10:00:00+08:00'), 'expired-grace'));
        $this->assertSame([], app(AvailabilityService::class)->slots($this->service, '2026-10-28'));
        $checkedIn = $booking->transition($this->staff, $existing, 'checked_in', 1);
        $this->assertSame('checked_in', $checkedIn->status);
        $this->assertSame(1, Appointment::count());
        $this->assertSame(2, AppointmentEvent::count());
    }

    public function test_service_snapshots_survive_reschedule_and_terminal_states_keep_reservations(): void
    {
        $booking = app(BookingService::class);
        $appointment = $booking->book($this->patient, $this->data(), 'snapshot');
        app(ScheduleGuard::class)->mutate(fn () => $this->service->update(['duration_minutes' => 60, 'price_minor' => 999999, 'buffer_after_minutes' => 30]));
        $appointment = $booking->reschedule($this->staff, $appointment, ['start_at' => '2026-10-28T10:00:00+08:00'], 1, 'Patient requested');
        $this->assertSame(30, $appointment->duration_minutes);
        $this->assertSame(150000, $appointment->price_minor);
        $this->assertSame(15, $appointment->buffer_after_minutes);
        $this->rejects(fn () => $booking->transition($this->patient, $appointment, 'checked_in', 2));
        $appointment = $booking->transition($this->staff, $appointment, 'checked_in', 2);
        $appointment = $booking->transition($this->staff, $appointment, 'in_chair', 3);
        $appointment = $booking->transition($this->staff, $appointment, 'completed', 4);
        $this->assertNotNull($appointment->checked_in_at);
        $this->assertNotNull($appointment->in_chair_at);
        $this->assertNotNull($appointment->completed_at);
        $this->rejects(fn () => $booking->transition($this->staff, $appointment, 'cancelled', 5, 'Correction'));
        $this->rejects(fn () => $booking->book($this->staff, $this->data('2026-10-28T10:00:00+08:00'), 'terminal-overlap'));
        $this->assertSame(1, Appointment::count());
    }

    public function test_overnight_intervals_and_configuration_closures_cannot_cut_a_reservation(): void
    {
        ClinicHour::query()->update(['local_start' => '22:00', 'local_end' => '24:00']);
        PractitionerShift::query()->update(['local_start' => '22:00', 'local_end' => '24:00']);
        foreach (range(1, 7) as $weekday) {
            ClinicHour::create(['weekday' => $weekday, 'local_start' => '00:00', 'local_end' => '02:00']);
            PractitionerShift::create(['practitioner_id' => $this->practitioner->id, 'weekday' => $weekday, 'local_start' => '00:00', 'local_end' => '02:00']);
        }
        $booking = app(BookingService::class);
        $appointment = $booking->book($this->patient, $this->data('2026-10-28T01:00:00+08:00'), 'overnight');
        $this->rejects(fn () => app(ScheduleGuard::class)->mutate(fn () => ScheduleBlock::create(['resource_type' => 'operatory', 'operatory_id' => $this->chair->id, 'starts_at' => '2026-10-27 17:15:00+00', 'ends_at' => '2026-10-27 17:30:00+00'])));
        $this->assertSame(0, ScheduleBlock::count());
        $this->assertSame('2026-10-27 17:00:00', $appointment->start_at->utc()->format('Y-m-d H:i:s'));
    }
}
