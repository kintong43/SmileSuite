<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_checkout_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedInteger('generation');
            $table->string('price_id');
            $table->text('success_url');
            $table->text('cancel_url');
            $table->string('provider_session_id')->nullable()->unique();
            $table->text('checkout_url')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();
        });
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->string('stripe_id')->unique();
            $table->string('stripe_status');
            $table->string('stripe_price')->nullable();
            $table->integer('quantity')->nullable();
            $table->timestampTz('trial_ends_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->timestampsTz();
            $table->index(['tenant_id', 'stripe_status']);
        });
        Schema::create('subscription_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->restrictOnDelete();
            $table->string('stripe_id')->unique();
            $table->string('stripe_product');
            $table->string('stripe_price');
            $table->string('meter_id')->nullable();
            $table->string('meter_event_name')->nullable();
            $table->integer('quantity')->nullable();
            $table->timestampsTz();
        });
        Schema::create('billing_entitlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->restrictOnDelete();
            $table->string('tier')->default('none');
            $table->boolean('bookings_enabled')->default(false);
            $table->unsignedInteger('chair_limit')->nullable();
            $table->unsignedInteger('practitioner_limit')->nullable();
            $table->timestampTz('past_due_since')->nullable();
            $table->timestampTz('grace_until')->nullable();
            $table->boolean('over_limit')->default(false);
            $table->timestampTz('reconciled_at')->nullable();
            $table->timestampsTz();
        });
        Schema::create('webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider');
            $table->string('provider_event_id');
            $table->string('event_type');
            $table->foreignId('tenant_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('raw_payload');
            $table->string('state')->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('last_error_code')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['provider', 'provider_event_id']);
        });
        Schema::create('shopify_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->restrictOnDelete();
            $table->string('shop_host')->unique();
            $table->string('shop_id');
            $table->string('primary_domain')->nullable();
            $table->text('private_token')->nullable();
            $table->string('status')->default('active');
            $table->unsignedInteger('generation')->default(1);
            $table->timestampTz('verified_at')->nullable();
            $table->timestampsTz();
        });
        Schema::create('shopping_carts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('connection_generation');
            $table->string('session_hash', 64);
            $table->text('shopify_cart_id');
            $table->timestampsTz();
            $table->unique(['tenant_id', 'session_hash']);
        });
        foreach (['subscriptions', 'billing_checkout_sessions', 'billing_entitlements', 'shopify_connections', 'shopping_carts'] as $table) {
            DB::statement("CREATE TRIGGER {$table}_immutable_tenant BEFORE UPDATE ON {$table} FOR EACH ROW EXECUTE FUNCTION smilesuite_immutable_tenant()");
        }
        DB::unprepared("CREATE OR REPLACE FUNCTION smilesuite_immutable_subscription() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN IF NEW.subscription_id IS DISTINCT FROM OLD.subscription_id THEN RAISE EXCEPTION 'Subscription ownership is immutable' USING ERRCODE='23514'; END IF; RETURN NEW; END; $$;");
        DB::statement('CREATE TRIGGER subscription_items_immutable_owner BEFORE UPDATE ON subscription_items FOR EACH ROW EXECUTE FUNCTION smilesuite_immutable_subscription()');
    }

    public function down(): void
    {
        foreach (['shopping_carts', 'shopify_connections', 'webhook_events', 'billing_entitlements', 'subscription_items', 'subscriptions', 'billing_checkout_sessions'] as $table) {
            Schema::dropIfExists($table);
        }
        DB::statement('DROP FUNCTION IF EXISTS smilesuite_immutable_subscription()');
    }
};
