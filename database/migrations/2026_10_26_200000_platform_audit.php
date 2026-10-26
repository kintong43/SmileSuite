<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('platform_admin_id')->constrained('platform_admins')->restrictOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('action', 64);
            $table->string('previous_status', 32);
            $table->string('new_status', 32);
            $table->timestampTz('occurred_at');
            $table->index(['tenant_id', 'occurred_at']);
        });
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION prevent_platform_audit_mutation() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'Platform audit events are append-only';
END;
$$;
CREATE TRIGGER platform_audit_append_only BEFORE UPDATE OR DELETE ON platform_audit_events
FOR EACH ROW EXECUTE FUNCTION prevent_platform_audit_mutation();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_audit_events');
        DB::statement('DROP FUNCTION IF EXISTS prevent_platform_audit_mutation()');
    }
};
