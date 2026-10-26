<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AuditEvent;
use App\Models\IntakeSubmission;
use App\Models\Operatory;
use App\Models\PatientProfile;
use App\Models\Practitioner;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\MissingTenantContext;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantRunner;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_models_fail_closed_without_context(): void
    {
        $this->expectException(MissingTenantContext::class);
        User::query()->count();
    }

    public function test_runner_isolates_accounts_and_clears_context_after_failure(): void
    {
        $a = Tenant::create(['name' => 'Synthetic A', 'subdomain' => 'test-a']);
        $b = Tenant::create(['name' => 'Synthetic B', 'subdomain' => 'test-b']);
        $runner = app(TenantRunner::class);
        $runner->run($a, fn () => User::create(['name' => 'A', 'email' => 'SAME@example.test', 'password' => 'synthetic-password']));
        $runner->run($b, fn () => User::create(['name' => 'B', 'email' => 'same@example.test', 'password' => 'synthetic-password']));
        $this->assertSame('A', $runner->run($a, fn () => User::sole()->name));
        $this->assertSame('B', $runner->run($b, fn () => User::sole()->name));
        try {
            $runner->run($a, fn () => throw new \RuntimeException('expected'));
        } catch (\RuntimeException) {
        }
        $this->assertFalse(app()->bound(TenantContext::class));
    }

    public function test_explicit_foreign_tenant_ownership_is_rejected(): void
    {
        $a = Tenant::create(['name' => 'Synthetic A', 'subdomain' => 'test-a']);
        $b = Tenant::create(['name' => 'Synthetic B', 'subdomain' => 'test-b']);
        $this->expectException(\LogicException::class);
        app(TenantRunner::class)->run($a, fn () => User::create(['tenant_id' => $b->id, 'name' => 'Wrong', 'email' => 'wrong@example.test', 'password' => 'synthetic-password']));
    }

    public function test_composite_foreign_key_rejects_cross_tenant_raw_write(): void
    {
        $a = Tenant::create(['name' => 'Synthetic A', 'subdomain' => 'test-a']);
        $b = Tenant::create(['name' => 'Synthetic B', 'subdomain' => 'test-b']);
        $user = app(TenantRunner::class)->run($a, fn () => User::create(['name' => 'A', 'email' => 'a@example.test', 'password' => 'synthetic-password']));
        $this->expectException(QueryException::class);
        DB::table('patient_profiles')->insert(['tenant_id' => $b->id, 'user_id' => $user->id, 'first_name' => 'Synthetic', 'last_name' => 'Mismatch']);
    }

    public function test_bulk_update_cannot_change_tenant_ownership(): void
    {
        $a = Tenant::create(['name' => 'Synthetic A', 'subdomain' => 'test-a']);
        $b = Tenant::create(['name' => 'Synthetic B', 'subdomain' => 'test-b']);
        $this->expectException(QueryException::class);
        app(TenantRunner::class)->run($a, function () use ($b): void {
            $profile = PatientProfile::create(['first_name' => 'Synthetic', 'last_name' => 'Person']);
            PatientProfile::whereKey($profile->id)->update(['tenant_id' => $b->id]);
        });
    }

    public function test_user_roles_are_tenant_local_and_may_be_combined(): void
    {
        $tenant = Tenant::create(['name' => 'Synthetic Roles', 'subdomain' => 'test-roles']);
        app(TenantRunner::class)->run($tenant, function (): void {
            $user = User::create(['name' => 'Practitioner owner', 'email' => 'roles@example.test', 'password' => 'synthetic-password']);
            $user->roles()->create(['role' => 'owner']);
            $user->roles()->create(['role' => 'practitioner']);
            $this->assertTrue($user->hasRole('owner'));
            $this->assertTrue($user->hasRole('patient', 'practitioner'));
            $this->assertFalse($user->hasRole('receptionist'));
        });
    }

    public function test_normalized_identity_uniqueness_is_enforced_by_database(): void
    {
        $tenant = Tenant::create(['name' => 'Synthetic Email', 'subdomain' => 'test-email']);
        app(TenantRunner::class)->run($tenant, fn () => User::create(['name' => 'First', 'email' => ' USER@example.test ', 'password' => 'synthetic-password']));
        $this->expectException(QueryException::class);
        app(TenantRunner::class)->run($tenant, fn () => User::create(['name' => 'Second', 'email' => 'user@example.test', 'password' => 'synthetic-password']));
    }

    public function test_audit_records_cannot_be_rewritten_with_raw_sql(): void
    {
        $tenant = Tenant::create(['name' => 'Synthetic Audit', 'subdomain' => 'test-audit']);
        $this->expectException(QueryException::class);
        app(TenantRunner::class)->run($tenant, function (): void {
            $event = AuditEvent::create(['action' => 'fixture.created', 'resource_type' => 'fixture', 'occurred_at' => now(), 'metadata' => []]);
            DB::table('audit_events')->where('id', $event->id)->update(['action' => 'rewritten']);
        });
    }

    public function test_same_clinic_patient_substitution_on_intake_is_rejected(): void
    {
        $tenant = Tenant::create(['name' => 'Synthetic Clinical', 'subdomain' => 'test-clinical']);
        $this->expectException(QueryException::class);
        app(TenantRunner::class)->run($tenant, function (): void {
            $user = User::create(['name' => 'Demo Doctor', 'email' => 'doctor@example.test', 'password' => 'synthetic-password']);
            $patientA = PatientProfile::create(['first_name' => 'First', 'last_name' => 'Example']);
            $patientB = PatientProfile::create(['first_name' => 'Second', 'last_name' => 'Example']);
            $practitioner = Practitioner::create(['user_id' => $user->id]);
            $chair = Operatory::create(['name' => 'Test chair']);
            $service = Service::create(['name' => 'Test consultation', 'category' => 'Test', 'duration_minutes' => 30, 'price_minor' => 100]);
            $start = now()->startOfMinute();
            $appointment = Appointment::create([
                'public_code' => 'TENANCY-TEST', 'patient_id' => $patientA->id, 'practitioner_id' => $practitioner->id,
                'service_id' => $service->id, 'operatory_id' => $chair->id, 'start_at' => $start, 'end_at' => $start->copy()->addMinutes(30),
                'occupied_start_at' => $start, 'occupied_end_at' => $start->copy()->addMinutes(45), 'price_minor' => 100, 'currency' => 'PHP',
                'duration_minutes' => 30, 'buffer_before_minutes' => 0, 'buffer_after_minutes' => 15, 'timezone' => 'Asia/Manila',
            ]);
            IntakeSubmission::create(['patient_id' => $patientB->id, 'appointment_id' => $appointment->id, 'form_version' => 'synthetic-1', 'answers' => ['synthetic' => true], 'submitted_at' => now(), 'author_id' => $user->id]);
        });
    }
}
