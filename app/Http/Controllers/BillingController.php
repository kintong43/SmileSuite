<?php

namespace App\Http\Controllers;

use App\Billing\BillingCheckoutService;
use App\Billing\ProcessStripeEvent;
use App\Billing\StripeWebhookInbox;
use App\Models\BillingEntitlement;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;

class BillingController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasRole('owner'), 403);

        return view('billing', ['entitlement' => BillingEntitlement::first()]);
    }

    public function checkout(Request $request, BillingCheckoutService $billing)
    {
        abort_unless($request->user()->hasRole('owner'), 403);
        $data = $request->validate(['tier' => 'required|in:starter,pro', 'interval' => 'required|in:monthly,annual']);
        try {
            $url = $billing->checkout(app(TenantContext::class)->tenant, $data['tier'], $data['interval'], url('/billing?checkout=returned'), url('/billing'));
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['billing' => 'Live subscriptions are not enabled or the provider is unavailable.']);
        }

        return redirect()->away($url);
    }

    public function portal(Request $request, BillingCheckoutService $billing)
    {
        abort_unless($request->user()->hasRole('owner'), 403);
        try {
            return redirect()->away($billing->portal(app(TenantContext::class)->tenant, url('/billing')));
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['billing' => 'The subscription portal is not available.']);
        }
    }

    public function webhook(Request $request, StripeWebhookInbox $inbox)
    {
        abort_unless(in_array($request->getHost(), config('tenancy.platform_hosts'), true) && $request->getHost() !== config('tenancy.admin_host'), 404);
        try {
            $event = $inbox->receive($request->getContent(), (string) $request->header('Stripe-Signature'));
        } catch (SignatureVerificationException|\UnexpectedValueException|\InvalidArgumentException $exception) {
            return response()->json(['error' => 'Invalid event'], 400);
        } catch (\RuntimeException $exception) {
            return response()->json(['error' => 'Webhook unavailable'], 503);
        }
        // A durable pending inbox is recovered by the scheduler if queue dispatch is unavailable.
        if ($event->state === 'pending') {
            try {
                ProcessStripeEvent::dispatch($event->id);
            } catch (\Throwable $exception) { /* Inbox remains pending for scheduled recovery. */
            }
        }

        return response()->json(['received' => true]);
    }
}
