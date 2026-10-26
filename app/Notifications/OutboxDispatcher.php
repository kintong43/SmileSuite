<?php

namespace App\Notifications;

use App\Jobs\DeliverAppointmentNotification;
use App\Models\OutboxMessage;
use App\Models\Tenant;
use App\Tenancy\TenantRunner;
use Illuminate\Support\Facades\DB;
use Throwable;

final class OutboxDispatcher
{
    public const MAX_ATTEMPTS = 5;

    public const LEASE_MINUTES = 10;

    public function __construct(private TenantRunner $tenants) {}

    public function dispatchPending(): int
    {
        $dispatched = 0;
        foreach (Tenant::orderBy('id')->cursor() as $tenant) {
            $ids = $this->tenants->run($tenant, fn () => DB::transaction(function (): array {
                $messages = OutboxMessage::whereIn('state', ['pending', 'failed', 'queued', 'sending'])
                    ->where('attempts', '<', self::MAX_ATTEMPTS)->where('available_at', '<=', now())
                    ->orderBy('id')->limit(100)->lock('FOR UPDATE SKIP LOCKED')->get();
                foreach ($messages as $message) {
                    $message->update(['state' => 'queued', 'available_at' => now()->addMinutes(self::LEASE_MINUTES)]);
                }

                return $messages->modelKeys();
            }));
            // Release context before dispatch: sync queues enter through the same tenant-safe job path.
            foreach ($ids as $id) {
                try {
                    DeliverAppointmentNotification::dispatch((int) $tenant->id, (int) $id);
                    $dispatched++;
                } catch (Throwable) {
                    $this->tenants->run($tenant, fn () => DB::transaction(function () use ($id): void {
                        $message = OutboxMessage::whereKey($id)->lockForUpdate()->first();
                        if ($message && $message->state === 'queued') {
                            $attempt = $message->attempts + 1;
                            $message->update(['state' => 'failed', 'attempts' => $attempt, 'last_error_code' => 'queue_unavailable', 'available_at' => now()->addSeconds(self::backoff($attempt))]);
                        }
                    }));
                }
            }
        }

        return $dispatched;
    }

    public static function backoff(int $attempt): int
    {
        return min(3600, 60 * (2 ** max(0, min($attempt - 1, 6))));
    }
}
