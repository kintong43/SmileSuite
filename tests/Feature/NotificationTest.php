<?php

namespace Tests\Feature;

use App\Jobs\DeliverAppointmentNotification;
use App\Models\Appointment;
use App\Models\ClinicHour;
use App\Models\NotificationDelivery;
use App\Models\Operatory;
use App\Models\OutboxMessage;
use App\Models\PatientProfile;
use App\Models\Practitioner;
use App\Models\PractitionerShift;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AppointmentMail;
use App\Notifications\CalendarInvitation;
use App\Notifications\OutboxDispatcher;
use App\Scheduling\BookingService;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantRunner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private int $appointmentId;

    private int $messageId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-26 00:00:00 UTC'));
        $this->tenant = Tenant::create(['name' => 'Notification fixture', 'subdomain' => 'notice-'.Str::lower(Str::random(10)), 'timezone' => 'Asia/Manila', 'currency' => 'PHP', 'lifecycle_status' => 'active']);
        app(TenantRunner::class)->run($this->tenant, function (): void {
            $staff = User::create(['name' => 'Reception', 'email' => 'staff@example.test', 'password' => 'password', 'status' => 'active', 'email_verified_at' => now()]);
            DB::table('user_roles')->insert(['tenant_id' => $this->tenant->id, 'user_id' => $staff->id, 'role' => 'receptionist']);
            DB::table('user_roles')->insert(['tenant_id' => $this->tenant->id, 'user_id' => $staff->id, 'role' => 'practitioner']);
            DB::table('billing_entitlements')->insert(['tenant_id' => $this->tenant->id, 'tier' => 'pro', 'bookings_enabled' => true, 'over_limit' => false, 'practitioner_limit' => 10, 'chair_limit' => 10, 'reconciled_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $patient = PatientProfile::create(['first_name' => 'Private', 'last_name' => 'Patient', 'email' => 'patient@example.test']);
            $doctor = Practitioner::create(['user_id' => $staff->id, 'active' => true]);
            $chair = Operatory::create(['name' => 'Chair', 'active' => true]);
            $service = Service::create(['name' => 'Secret diagnosis treatment', 'category' => 'Confidential allergy', 'description' => 'Clinical narrative must stay private', 'duration_minutes' => 30, 'buffer_before_minutes' => 0, 'buffer_after_minutes' => 0, 'price_minor' => 1000, 'currency' => 'PHP', 'active' => true]);
            DB::table('practitioner_services')->insert(['tenant_id' => $this->tenant->id, 'practitioner_id' => $doctor->id, 'service_id' => $service->id]);
            DB::table('operatory_services')->insert(['tenant_id' => $this->tenant->id, 'operatory_id' => $chair->id, 'service_id' => $service->id]);
            ClinicHour::create(['weekday' => 2, 'local_start' => '09:00', 'local_end' => '17:00']);
            PractitionerShift::create(['practitioner_id' => $doctor->id, 'weekday' => 2, 'local_start' => '09:00', 'local_end' => '17:00']);
            $appointment = app(BookingService::class)->book($staff, ['patient_id' => $patient->id, 'service_id' => $service->id, 'practitioner_id' => $doctor->id, 'operatory_id' => $chair->id, 'start_at' => '2026-10-27T10:00:00+08:00'], 'notification-fixture');
            $this->appointmentId = $appointment->id;
            $this->messageId = OutboxMessage::where('resource_id', $appointment->id)->sole()->id;
        });
    }

    public function test_committed_booking_outbox_dispatches_and_duplicate_job_does_not_resend(): void
    {
        Mail::fake();
        $this->assertSame(1, app(OutboxDispatcher::class)->dispatchPending());
        (new DeliverAppointmentNotification($this->tenant->id, $this->messageId))->handle();
        Mail::assertSent(AppointmentMail::class, 1);
        Mail::assertSent(AppointmentMail::class, fn ($mail) => $mail->hasTo('patient@example.test'));
        app(TenantRunner::class)->run($this->tenant, function (): void {
            $this->assertSame('sent', OutboxMessage::findOrFail($this->messageId)->state);
            $delivery = NotificationDelivery::where('outbox_message_id', $this->messageId)->sole();
            $this->assertSame('sent', $delivery->state);
            $this->assertSame(1, $delivery->attempts);
        });
        $this->assertFalse(app()->bound(TenantContext::class));
    }

    public function test_calendar_is_minimal_utc_crlf_with_stable_uid_and_increasing_cancellation_sequence(): void
    {
        app(TenantRunner::class)->run($this->tenant, function (): void {
            $appointment = Appointment::findOrFail($this->appointmentId);
            $calendar = app(CalendarInvitation::class)->render($appointment);
            $this->assertStringContainsString('DTSTART:20261027T020000Z', $calendar);
            $this->assertStringContainsString('DTEND:20261027T023000Z', $calendar);
            $this->assertStringContainsString('METHOD:REQUEST', $calendar);
            $this->assertStringContainsString('SEQUENCE:1', $calendar);
            $this->assertStringNotContainsString("\n", str_replace("\r\n", '', $calendar));
            foreach (['Secret', 'diagnosis', 'treatment', 'allergy', 'narrative', 'Private', 'Patient'] as $private) {
                $this->assertStringNotContainsString($private, $calendar);
            }
            preg_match('/UID:([^\r]+)/', $calendar, $uid);
            $appointment->update(['status' => 'cancelled', 'version' => 2]);
            $cancelled = app(CalendarInvitation::class)->render($appointment);
            $this->assertStringContainsString('UID:'.$uid[1], $cancelled);
            $this->assertStringContainsString('SEQUENCE:2', $cancelled);
            $this->assertStringContainsString('METHOD:CANCEL', $cancelled);
            $this->assertStringContainsString('STATUS:CANCELLED', $cancelled);
        });
    }

    public function test_obsolete_event_is_skipped_before_email(): void
    {
        Mail::fake();
        app(TenantRunner::class)->run($this->tenant, fn () => Appointment::findOrFail($this->appointmentId)->update(['version' => 2]));
        app(OutboxDispatcher::class)->dispatchPending();
        Mail::assertNothingSent();
        app(TenantRunner::class)->run($this->tenant, fn () => $this->assertSame('superseded', OutboxMessage::findOrFail($this->messageId)->state));
    }

    public function test_cancellation_delivers_a_minimal_calendar_attachment(): void
    {
        Mail::fake();
        app(TenantRunner::class)->run($this->tenant, function (): void {
            $appointment = Appointment::findOrFail($this->appointmentId);
            $actor = User::where('email', 'staff@example.test')->sole();
            app(BookingService::class)->transition($actor, $appointment, 'cancelled', 1, 'Private clinical narrative');
        });
        $this->assertSame(2, app(OutboxDispatcher::class)->dispatchPending());
        Mail::assertSent(AppointmentMail::class, function (AppointmentMail $mail): bool {
            $mail->build();
            $this->assertSame('Appointment cancelled', $mail->subject);
            $this->assertStringContainsString('METHOD:CANCEL', $mail->calendar);
            $this->assertStringContainsString('SEQUENCE:2', $mail->calendar);
            $this->assertStringNotContainsString('Private clinical narrative', $mail->calendar.$mail->render());
            $this->assertSame('appointment.ics', $mail->rawAttachments[0]['name']);
            $this->assertStringContainsString('method=CANCEL', $mail->rawAttachments[0]['options']['mime']);

            return $mail->cancelled;
        });
        Mail::assertSentCount(1);
    }

    public function test_rollback_of_appointment_change_leaves_no_outbox_to_deliver(): void
    {
        app(TenantRunner::class)->run($this->tenant, function (): void {
            try {
                DB::transaction(function (): void {
                    app(BookingService::class)->transition(User::where('email', 'staff@example.test')->sole(), Appointment::findOrFail($this->appointmentId), 'cancelled', 1, 'Changed plans');
                    throw new \RuntimeException('Rollback fixture');
                });
            } catch (\RuntimeException $exception) {
                $this->assertSame('Rollback fixture', $exception->getMessage());
            }
            $this->assertSame(1, OutboxMessage::count());
            $this->assertSame(1, Appointment::findOrFail($this->appointmentId)->version);
        });
    }

    public function test_queue_outage_preserves_outbox_and_recovers_after_queue_returns(): void
    {
        Mail::fake();
        config(['queue.default' => 'unavailable']);
        $this->assertSame(0, app(OutboxDispatcher::class)->dispatchPending());
        app(TenantRunner::class)->run($this->tenant, function (): void {
            $message = OutboxMessage::findOrFail($this->messageId);
            $this->assertSame('failed', $message->state);
            $this->assertSame('queue_unavailable', $message->last_error_code);
            $this->assertNull($message->processed_at);
            $this->assertSame(1, Appointment::count());
        });
        Mail::assertNothingSent();
        config(['queue.default' => 'sync']);
        $this->travel(2)->minutes();
        $this->assertSame(1, app(OutboxDispatcher::class)->dispatchPending());
        Mail::assertSentCount(1);
        $this->assertFalse(app()->bound(TenantContext::class));
    }

    public function test_provider_failure_is_redacted_and_retried_after_backoff(): void
    {
        Mail::shouldReceive('getDefaultDriver')->andReturn('array');
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('Secret patient@example.test diagnosis'));
        app(OutboxDispatcher::class)->dispatchPending();
        app(TenantRunner::class)->run($this->tenant, function (): void {
            $message = OutboxMessage::findOrFail($this->messageId);
            $this->assertSame('failed', $message->state);
            $this->assertSame(1, $message->attempts);
            $this->assertSame('delivery_failed', $message->last_error_code);
            $this->assertTrue($message->available_at->isFuture());
        });
        $this->assertSame(0, app(OutboxDispatcher::class)->dispatchPending());
        $this->travel(2)->minutes();
        Mail::fake();
        $this->assertSame(1, app(OutboxDispatcher::class)->dispatchPending());
        Mail::assertSent(AppointmentMail::class, 1);
        $this->assertFalse(app()->bound(TenantContext::class));
    }

    public function test_lost_queued_and_stale_sending_messages_are_recovered_but_retries_are_bounded(): void
    {
        Queue::fake();
        $this->assertSame(1, app(OutboxDispatcher::class)->dispatchPending());
        Queue::assertPushed(DeliverAppointmentNotification::class, fn ($job) => $job->tenantId === $this->tenant->id && $job->outboxMessageId === $this->messageId);
        $this->assertSame(0, app(OutboxDispatcher::class)->dispatchPending());
        $this->travel(11)->minutes();
        $this->assertSame(1, app(OutboxDispatcher::class)->dispatchPending());
        app(TenantRunner::class)->run($this->tenant, fn () => OutboxMessage::findOrFail($this->messageId)->update(['state' => 'sending', 'available_at' => now()->subMinute()]));
        $this->assertSame(1, app(OutboxDispatcher::class)->dispatchPending());
        app(TenantRunner::class)->run($this->tenant, fn () => OutboxMessage::findOrFail($this->messageId)->update(['state' => 'failed', 'attempts' => 5, 'available_at' => now()->subMinute()]));
        $this->assertSame(0, app(OutboxDispatcher::class)->dispatchPending());
    }

    public function test_repeated_provider_failure_stops_after_five_durable_attempts(): void
    {
        Mail::shouldReceive('to')->times(5)->andThrow(new \RuntimeException('Provider unavailable'));
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->assertSame(1, app(OutboxDispatcher::class)->dispatchPending());
            $this->travel(1)->hours();
        }
        $this->assertSame(0, app(OutboxDispatcher::class)->dispatchPending());
        app(TenantRunner::class)->run($this->tenant, function (): void {
            $message = OutboxMessage::findOrFail($this->messageId);
            $this->assertSame('failed', $message->state);
            $this->assertSame(5, $message->attempts);
            $this->assertSame(5, NotificationDelivery::where('outbox_message_id', $message->id)->sole()->attempts);
        });
        $this->assertFalse(app()->bound(TenantContext::class));
    }

    public function test_missing_recipient_is_visible_without_repeated_delivery_attempts(): void
    {
        Mail::fake();
        app(TenantRunner::class)->run($this->tenant, fn () => PatientProfile::query()->update(['email' => null]));
        $this->assertSame(1, app(OutboxDispatcher::class)->dispatchPending());
        $this->travel(1)->hours();
        $this->assertSame(0, app(OutboxDispatcher::class)->dispatchPending());
        Mail::assertNothingSent();
        app(TenantRunner::class)->run($this->tenant, function (): void {
            $message = OutboxMessage::findOrFail($this->messageId);
            $this->assertSame('skipped', $message->state);
            $this->assertSame('recipient_unavailable', $message->last_error_code);
            $this->assertSame('skipped', NotificationDelivery::where('outbox_message_id', $message->id)->sole()->state);
        });
    }

    public function test_wrong_tenant_job_cannot_load_or_send_another_tenants_outbox(): void
    {
        Mail::fake();
        $other = Tenant::create(['name' => 'Other', 'subdomain' => 'other-'.Str::lower(Str::random(10)), 'timezone' => 'UTC', 'currency' => 'PHP', 'lifecycle_status' => 'active']);
        (new DeliverAppointmentNotification($other->id, $this->messageId))->handle();
        Mail::assertNothingSent();
        $this->assertFalse(app()->bound(TenantContext::class));
        app(TenantRunner::class)->run($this->tenant, fn () => $this->assertSame('pending', OutboxMessage::findOrFail($this->messageId)->state));
    }
}
