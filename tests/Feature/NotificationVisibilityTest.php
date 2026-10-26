<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AuditEvent;
use App\Models\NotificationDelivery;
use App\Models\OutboxMessage;
use App\Models\Practitioner;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private int $messageId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->tenant = Tenant::where('subdomain', 'harbor')->sole();
        $this->messageId = app(TenantRunner::class)->run($this->tenant, function (): int {
            $appointment = Appointment::orderBy('id')->firstOrFail();
            $message = OutboxMessage::create(['event_type' => 'appointment.booked', 'resource_id' => $appointment->id, 'version' => $appointment->version, 'deduplication_key' => 'visibility-'.$appointment->id, 'state' => 'failed', 'attempts' => 5, 'available_at' => now(), 'last_error_code' => 'private@example.test clinical narrative']);
            NotificationDelivery::create(['outbox_message_id' => $message->id, 'channel' => 'email', 'state' => 'failed', 'attempts' => 5, 'last_error_code' => 'delivery_failed']);

            return $message->id;
        });
    }

    private function signIn(string $role, ?Tenant $tenant = null): User
    {
        $tenant ??= $this->tenant;
        $user = app(TenantRunner::class)->run($tenant, fn () => User::where('email', $role.'@'.$tenant->subdomain.'.example.test')->sole());
        $this->actingAs($user)->withSession(['mfa_confirmed' => true, 'mfa_user_id' => $user->id]);

        return $user;
    }

    public function test_owner_and_receptionist_see_operational_status_without_message_or_medical_data(): void
    {
        foreach (['owner', 'receptionist'] as $role) {
            $this->signIn($role);
            $this->get('http://harbor.smilesuite.localhost/notifications')->assertOk()
                ->assertSee('Notification delivery')->assertSee('Failed')->assertSee('5 / 5')
                ->assertSee('Retry reason')->assertDontSee('private@example.test')->assertDontSee('clinical narrative')
                ->assertDontSee('Dental consultation')->assertDontSee('Alex Example');
        }
    }

    public function test_practitioner_sees_only_assigned_appointment_notifications_and_cannot_retry(): void
    {
        app(TenantRunner::class)->run($this->tenant, function (): void {
            $other = User::create(['name' => 'Other doctor', 'email' => 'other-doctor@example.test', 'password' => 'password', 'status' => 'active']);
            $practitioner = Practitioner::create(['user_id' => $other->id, 'active' => true]);
            $source = Appointment::orderBy('id')->firstOrFail();
            $appointment = $source->replicate();
            $appointment->public_code = 'OTHER-APPOINTMENT';
            $appointment->practitioner_id = $practitioner->id;
            $appointment->status = 'cancelled';
            $appointment->save();
            OutboxMessage::create(['event_type' => 'appointment.cancelled', 'resource_id' => $appointment->id, 'version' => 1, 'deduplication_key' => 'other-visibility', 'state' => 'sent', 'attempts' => 1, 'available_at' => now()]);
        });
        $this->signIn('practitioner');
        $response = $this->get('http://harbor.smilesuite.localhost/notifications')->assertOk()->assertDontSee('Retry reason');
        $response->assertViewHas('messages', fn ($messages) => $messages->total() === 1 && $messages->first()['id'] === $this->messageId);
        $this->post('http://harbor.smilesuite.localhost/notifications/'.$this->messageId.'/retry', ['reason' => 'staff_reviewed'])->assertForbidden();
    }

    public function test_patient_cannot_view_or_retry_notification_operations(): void
    {
        $this->signIn('patient');
        $this->get('http://harbor.smilesuite.localhost/notifications')->assertForbidden();
        $this->post('http://harbor.smilesuite.localhost/notifications/'.$this->messageId.'/retry', ['reason' => 'staff_reviewed'])->assertForbidden();
    }

    public function test_authorized_retry_requires_operational_reason_and_is_audited(): void
    {
        $actor = $this->signIn('receptionist');
        $url = 'http://harbor.smilesuite.localhost/notifications/'.$this->messageId.'/retry';
        $this->post($url)->assertSessionHasErrors('reason');
        $this->post($url, ['reason' => 'medical narrative'])->assertSessionHasErrors('reason');
        $this->post($url, ['reason' => 'provider_restored'])->assertRedirect('/notifications');
        app(TenantRunner::class)->run($this->tenant, function () use ($actor): void {
            $message = OutboxMessage::findOrFail($this->messageId);
            $this->assertSame('pending', $message->state);
            $this->assertSame(0, $message->attempts);
            $this->assertNull($message->last_error_code);
            $delivery = NotificationDelivery::where('outbox_message_id', $message->id)->sole();
            $this->assertSame('pending', $delivery->state);
            $this->assertSame(0, $delivery->attempts);
            $audit = AuditEvent::where('action', 'notification.retry_requested')->sole();
            $this->assertSame($actor->id, $audit->actor_id);
            $this->assertSame('provider_restored', $audit->metadata['reason']);
            $this->assertSame(5, $audit->metadata['prior_attempts']);
        });
    }

    public function test_sent_queued_superseded_and_stale_events_cannot_be_retried(): void
    {
        $this->signIn('owner');
        foreach (['sent', 'queued', 'superseded', 'pending'] as $state) {
            app(TenantRunner::class)->run($this->tenant, fn () => OutboxMessage::findOrFail($this->messageId)->update(['state' => $state]));
            $this->post('http://harbor.smilesuite.localhost/notifications/'.$this->messageId.'/retry', ['reason' => 'staff_reviewed'])->assertUnprocessable();
        }
        app(TenantRunner::class)->run($this->tenant, function (): void {
            $message = OutboxMessage::findOrFail($this->messageId);
            $message->update(['state' => 'failed']);
            Appointment::findOrFail($message->resource_id)->update(['version' => 2]);
        });
        $this->post('http://harbor.smilesuite.localhost/notifications/'.$this->messageId.'/retry', ['reason' => 'staff_reviewed'])->assertUnprocessable();
    }

    public function test_other_clinic_cannot_view_or_retry_outbox(): void
    {
        $this->signIn('owner', Tenant::where('subdomain', 'cedar')->sole());
        $this->get('http://cedar.smilesuite.localhost/notifications')->assertOk()->assertViewHas('messages', fn ($messages) => $messages->total() === 0);
        $this->post('http://cedar.smilesuite.localhost/notifications/'.$this->messageId.'/retry', ['reason' => 'staff_reviewed'])->assertNotFound();
    }

    public function test_skipped_recipient_can_be_retried_but_recorded_sent_delivery_cannot(): void
    {
        $this->signIn('owner');
        app(TenantRunner::class)->run($this->tenant, function (): void {
            OutboxMessage::findOrFail($this->messageId)->update(['state' => 'skipped', 'last_error_code' => 'recipient_unavailable']);
            NotificationDelivery::where('outbox_message_id', $this->messageId)->update(['state' => 'skipped']);
        });
        $this->post('http://harbor.smilesuite.localhost/notifications/'.$this->messageId.'/retry', ['reason' => 'recipient_corrected'])->assertRedirect('/notifications');
        app(TenantRunner::class)->run($this->tenant, function (): void {
            OutboxMessage::findOrFail($this->messageId)->update(['state' => 'failed']);
            NotificationDelivery::where('outbox_message_id', $this->messageId)->update(['state' => 'sent', 'sent_at' => now()]);
        });
        $this->post('http://harbor.smilesuite.localhost/notifications/'.$this->messageId.'/retry', ['reason' => 'staff_reviewed'])->assertUnprocessable();
        app(TenantRunner::class)->run($this->tenant, fn () => $this->assertSame(1, AuditEvent::where('action', 'notification.retry_requested')->count()));
    }
}
