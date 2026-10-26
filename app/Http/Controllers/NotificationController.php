<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AuditEvent;
use App\Models\NotificationDelivery;
use App\Models\OutboxMessage;
use App\Models\Practitioner;
use App\Notifications\OutboxDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NotificationController extends Controller
{
    private const STATES = [
        'pending' => 'Pending', 'queued' => 'Queued', 'sending' => 'Sending',
        'sent' => 'Sent', 'failed' => 'Failed', 'skipped' => 'Skipped', 'superseded' => 'Superseded',
    ];

    private const REASONS = [
        'provider_restored' => 'Delivery provider restored',
        'recipient_corrected' => 'Recipient details corrected',
        'staff_reviewed' => 'Delivery reviewed by staff',
    ];

    public function index(Request $request): View
    {
        abort_unless($request->user()->hasRole('owner', 'receptionist', 'practitioner'), 403);
        $canRetry = $request->user()->hasRole('owner', 'receptionist');
        $query = OutboxMessage::query();
        if (! $canRetry) {
            $query->whereIn('resource_id', Appointment::select('id')->whereIn('practitioner_id', Practitioner::select('id')->where('user_id', $request->user()->id)));
        }
        $messages = $query->orderByDesc('id')->paginate(25);
        $deliveries = NotificationDelivery::whereIn('outbox_message_id', $messages->getCollection()->modelKeys())->where('channel', 'email')->get()->keyBy('outbox_message_id');
        $versions = Appointment::whereIn('id', $messages->getCollection()->pluck('resource_id'))->pluck('version', 'id');
        // The view receives a deliberate operational projection, never patient or provider payloads.
        $messages->through(function (OutboxMessage $message) use ($deliveries, $versions, $canRetry): array {
            $delivery = $deliveries->get($message->id);

            return [
                'id' => $message->id,
                'appointment_id' => $message->resource_id,
                'version' => $message->version,
                'state' => self::STATES[$message->state] ?? 'Unknown',
                'attempts' => $message->attempts,
                'delivery_state' => $delivery ? (self::STATES[$delivery->state] ?? 'Unknown') : 'Awaiting delivery',
                'error' => $this->errorLabel($message->last_error_code ?: $delivery?->last_error_code),
                'available_at' => $message->available_at,
                'sent_at' => $delivery?->sent_at,
                'can_retry' => $canRetry && $delivery?->state !== 'sent' && in_array($message->state, ['failed', 'skipped'], true) && (int) $versions->get($message->resource_id) === $message->version,
            ];
        });

        return view('notifications', ['messages' => $messages, 'reasons' => self::REASONS, 'maxAttempts' => OutboxDispatcher::MAX_ATTEMPTS]);
    }

    public function retry(Request $request, OutboxMessage $message): RedirectResponse
    {
        abort_unless($request->user()->hasRole('owner', 'receptionist'), 403);
        $data = $request->validate(['reason' => ['required', 'string', Rule::in(array_keys(self::REASONS))]]);
        DB::transaction(function () use ($request, $message, $data): void {
            $current = OutboxMessage::whereKey($message->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($current->state, ['failed', 'skipped'], true), 422, 'Only failed or skipped notifications can be retried.');
            $appointment = Appointment::whereKey($current->resource_id)->lockForUpdate()->firstOrFail();
            abort_unless($appointment->version === $current->version, 422, 'This notification has been replaced by a newer appointment update.');
            $delivery = NotificationDelivery::where('outbox_message_id', $current->id)->where('channel', 'email')->lockForUpdate()->first();
            abort_if($delivery?->state === 'sent', 422, 'This notification has already been delivered.');
            AuditEvent::create([
                'actor_id' => $request->user()->id, 'action' => 'notification.retry_requested',
                'resource_type' => 'outbox_message', 'resource_id' => $current->id,
                'metadata' => ['reason' => $data['reason'], 'prior_state' => $current->state, 'prior_attempts' => $current->attempts],
                'occurred_at' => now(),
            ]);
            $current->update(['state' => 'pending', 'attempts' => 0, 'available_at' => now(), 'processed_at' => null, 'last_error_code' => null]);
            $delivery?->update(['state' => 'pending', 'attempts' => 0, 'sent_at' => null, 'last_error_code' => null]);
        });

        return redirect('/notifications')->with('success', 'Notification retry requested. Delivery will resume with the next dispatch.');
    }

    private function errorLabel(?string $code): ?string
    {
        return match ($code) {
            null => null,
            'delivery_failed' => 'Delivery provider failed',
            'queue_unavailable' => 'Delivery queue unavailable',
            'recipient_unavailable' => 'Recipient details unavailable',
            default => 'Delivery issue recorded',
        };
    }
}
