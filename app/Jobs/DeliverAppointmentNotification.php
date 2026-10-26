<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Models\NotificationDelivery;
use App\Models\OutboxMessage;
use App\Notifications\AppointmentMail;
use App\Notifications\CalendarInvitation;
use App\Notifications\OutboxDispatcher;
use App\Tenancy\TenantRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class DeliverAppointmentNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    // Retry policy lives in the durable outbox; queue failures recover when its lease expires.
    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public readonly int $tenantId, public readonly int $outboxMessageId) {}

    public function handle(): void
    {
        app(TenantRunner::class)->run($this->tenantId, function (): void {
            DB::transaction(function (): void {
                $message = OutboxMessage::whereKey($this->outboxMessageId)->lockForUpdate()->first();
                if (! $message || $message->state !== 'queued' || $message->attempts >= OutboxDispatcher::MAX_ATTEMPTS) {
                    return;
                }
                $appointment = Appointment::whereKey($message->resource_id)->lockForUpdate()->first();
                if (! $appointment || $appointment->version !== $message->version) {
                    $message->update(['state' => 'superseded', 'processed_at' => now()]);

                    return;
                }
                $delivery = NotificationDelivery::firstOrCreate(['outbox_message_id' => $message->id, 'channel' => 'email'], ['state' => 'pending', 'attempts' => 0]);
                if ($delivery->state === 'sent') {
                    $message->update(['state' => 'sent', 'processed_at' => $delivery->sent_at]);

                    return;
                }
                $recipient = $appointment->patient?->email ?: $appointment->patient?->user?->email;
                if (! $recipient || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    $message->update(['state' => 'skipped', 'processed_at' => now(), 'last_error_code' => 'recipient_unavailable']);
                    $delivery->update(['state' => 'skipped', 'last_error_code' => 'recipient_unavailable']);

                    return;
                }
                $attempt = $message->attempts + 1;
                $message->update(['state' => 'sending', 'attempts' => $attempt, 'available_at' => now()->addMinutes(OutboxDispatcher::LEASE_MINUTES)]);
                $delivery->update(['state' => 'sending', 'attempts' => $delivery->attempts + 1]);
                // Hold both row locks through sending: concurrent replays cannot send this event,
                // and a concurrent appointment edit cannot make the calendar obsolete mid-send.
                try {
                    Mail::to($recipient)->send(new AppointmentMail(app(CalendarInvitation::class)->render($appointment), $appointment->status === 'cancelled'));
                    $delivery->update(['state' => 'sent', 'sent_at' => now(), 'last_error_code' => null]);
                    $message->update(['state' => 'sent', 'processed_at' => now(), 'last_error_code' => null]);
                } catch (Throwable) {
                    // Never persist provider exception text: it may contain recipient or message data.
                    $delivery->update(['state' => 'failed', 'last_error_code' => 'delivery_failed']);
                    $message->update(['state' => 'failed', 'last_error_code' => 'delivery_failed', 'available_at' => now()->addSeconds(OutboxDispatcher::backoff($attempt))]);
                }
            });
        });
    }
}
