<?php

namespace Tests\Feature;

use App\Clinical\ClinicalAccess;
use App\Models\Appointment;
use App\Models\CareAssignment;
use App\Models\PatientProfile;
use App\Models\Practitioner;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRole;
use App\Tenancy\TenantRunner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CareAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $issuer;

    private User $recipient;

    private Practitioner $recipientPractitioner;

    private Appointment $appointment;

    private PatientProfile $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(TenantRunner::class)->run(Tenant::where('subdomain', 'harbor')->firstOrFail(), function (): void {
            $this->issuer = User::where('email', 'practitioner@harbor.example.test')->firstOrFail();
            $this->recipient = User::create(['name' => 'Synthetic Covering Doctor', 'email' => 'covering@harbor.example.test', 'password' => 'Synthetic-password-2026!', 'email_verified_at' => now(), 'status' => 'active']);
            UserRole::create(['user_id' => $this->recipient->id, 'role' => 'practitioner']);
            $this->recipientPractitioner = Practitioner::create(['user_id' => $this->recipient->id]);
            $this->appointment = Appointment::orderBy('start_at')->firstOrFail();
            $this->patient = $this->appointment->patient;
        });
    }

    private function endpoint(?int $patientId = null): string
    {
        return 'http://harbor.smilesuite.localhost/patients/'.($patientId ?? $this->patient->id).'/care-assignments';
    }

    private function signIn(User $user): static
    {
        return $this->actingAs($user)->withSession(['mfa_confirmed' => true, 'mfa_user_id' => $user->id, 'auth_stamp' => hash('sha256', (string) $user->remember_token)]);
    }

    private function grantData(): array
    {
        return ['appointment_id' => $this->appointment->id, 'practitioner_id' => $this->recipientPractitioner->id, 'valid_to' => now()->addDays(7)->toIso8601String(), 'purpose' => 'covering_care'];
    }

    public function test_expiry_offset_is_preserved_as_a_utc_instant(): void
    {
        $expiry = now()->addDays(7)->toImmutable()->setTimezone('Asia/Manila');
        $this->signIn($this->issuer)->post($this->endpoint(), array_replace($this->grantData(), ['valid_to' => $expiry->toIso8601String()]))->assertRedirect();
        $row = DB::table('care_assignments')->first();
        $this->assertSame($expiry->utc()->format('Y-m-d H:i:s'), CarbonImmutable::parse($row->valid_to)->utc()->format('Y-m-d H:i:s'));
    }

    public function test_assigned_practitioner_can_delegate_one_encounter_then_revoke_access(): void
    {
        $this->signIn($this->issuer)->get($this->endpoint())->assertOk()->assertSee('Delegate encounter access');
        $this->post($this->endpoint(), $this->grantData())->assertRedirect($this->endpoint());
        $assignment = DB::table('care_assignments')->where('granted_by', $this->issuer->id)->first();
        $this->assertNotNull($assignment);
        app(TenantRunner::class)->run(Tenant::findOrFail($this->issuer->tenant_id), function (): void {
            $this->assertSame([$this->appointment->id], app(ClinicalAccess::class)->permittedAppointmentIds($this->recipient, $this->patient));
        });
        $this->post($this->endpoint().'/'.$assignment->id.'/revoke')->assertRedirect($this->endpoint());
        $this->assertDatabaseMissing('care_assignments', ['id' => $assignment->id, 'revoked_at' => null]);
        $this->assertDatabaseHas('audit_events', ['action' => 'care_assignment.grant', 'resource_id' => $assignment->id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'care_assignment.revoke', 'resource_id' => $assignment->id]);
    }

    public function test_owner_receptionist_and_patient_cannot_delegate(): void
    {
        foreach (['owner', 'receptionist', 'patient'] as $role) {
            $user = app(TenantRunner::class)->run(Tenant::findOrFail($this->issuer->tenant_id), fn () => User::where('email', $role.'@harbor.example.test')->firstOrFail());
            $this->signIn($user)->get($this->endpoint())->assertForbidden();
            $this->post($this->endpoint(), $this->grantData())->assertForbidden();
        }
    }

    public function test_scope_cannot_be_unbounded_or_substituted_between_patients(): void
    {
        $this->signIn($this->issuer);
        $this->post($this->endpoint(), array_replace($this->grantData(), ['appointment_id' => null]))->assertSessionHasErrors('appointment_id');
        $other = DB::table('patient_profiles')->where('tenant_id', $this->issuer->tenant_id)->where('id', '!=', $this->patient->id)->first();
        $this->post($this->endpoint($other->id), $this->grantData())->assertNotFound();
        $this->assertDatabaseCount('care_assignments', 0);
    }

    public function test_recipient_and_expiry_are_validated_and_duplicate_scope_is_rejected(): void
    {
        $foreign = DB::table('practitioners')->where('tenant_id', '!=', $this->issuer->tenant_id)->first();
        $this->signIn($this->issuer);
        $this->post($this->endpoint(), array_replace($this->grantData(), ['practitioner_id' => $foreign->id]))->assertNotFound();
        foreach (['2026-12-01T10:00:00', now()->subDay()->toIso8601String(), now()->addDays(91)->toIso8601String()] as $expiry) {
            $this->post($this->endpoint(), array_replace($this->grantData(), ['valid_to' => $expiry]))->assertSessionHasErrors('valid_to');
        }
        $this->post($this->endpoint(), $this->grantData())->assertRedirect();
        $this->post($this->endpoint(), $this->grantData())->assertSessionHasErrors('practitioner_id');
        $this->assertDatabaseCount('care_assignments', 1);
    }

    public function test_issuer_can_revoke_after_encounter_finishes_and_other_practitioner_cannot(): void
    {
        $this->signIn($this->issuer)->post($this->endpoint(), $this->grantData())->assertRedirect();
        $assignment = DB::table('care_assignments')->first();
        DB::table('appointments')->where('patient_id', $this->patient->id)->where('tenant_id', $this->issuer->tenant_id)->update(['status' => 'completed']);
        $this->get($this->endpoint())->assertOk()->assertDontSee('name="appointment_id"', false);
        $this->signIn($this->recipient)->post($this->endpoint().'/'.$assignment->id.'/revoke')->assertForbidden();
        $this->signIn($this->issuer)->post($this->endpoint().'/'.$assignment->id.'/revoke')->assertRedirect();
    }

    public function test_environment_gate_blocks_delegation(): void
    {
        config(['smilesuite.sandbox' => false, 'smilesuite.real_patient_data' => false]);
        $this->signIn($this->issuer)->get($this->endpoint())->assertForbidden();
        $this->post($this->endpoint(), $this->grantData())->assertForbidden();
    }

    public function test_history_is_limited_to_own_assignments_and_revoke_rejects_wrong_patient(): void
    {
        $this->signIn($this->issuer)->post($this->endpoint(), $this->grantData())->assertRedirect();
        $own = DB::table('care_assignments')->first();
        app(TenantRunner::class)->run(Tenant::findOrFail($this->issuer->tenant_id), fn () => CareAssignment::create([
            'patient_id' => $this->patient->id, 'appointment_id' => $this->appointment->id, 'practitioner_id' => $this->recipientPractitioner->id,
            'granted_by' => $this->recipient->id, 'valid_from' => now(), 'valid_to' => now()->addDay(), 'purpose' => 'consultation',
        ]));
        $this->get($this->endpoint())->assertOk()->assertViewHas('assignments', fn ($assignments) => $assignments->pluck('id')->all() === [$own->id]);
        $other = DB::table('patient_profiles')->where('tenant_id', $this->issuer->tenant_id)->where('id', '!=', $this->patient->id)->first();
        $this->post($this->endpoint($other->id).'/'.$own->id.'/revoke')->assertNotFound();
        $this->assertDatabaseHas('care_assignments', ['id' => $own->id, 'revoked_at' => null]);
    }

    public function test_current_care_does_not_allow_delegating_an_old_unassigned_encounter(): void
    {
        $old = app(TenantRunner::class)->run(Tenant::findOrFail($this->issuer->tenant_id), function (): Appointment {
            $copy = $this->appointment->replicate(['public_code']);
            $copy->public_code = 'SYNTHETIC-OLD-DELEGATION';
            foreach (['start_at', 'end_at', 'occupied_start_at', 'occupied_end_at'] as $column) {
                $copy->$column = $copy->$column->subMonths(2);
            }
            $copy->status = 'completed';
            $copy->save();

            return $copy;
        });
        $this->signIn($this->issuer)->post($this->endpoint(), array_replace($this->grantData(), ['appointment_id' => $old->id]))->assertForbidden();
        $this->assertDatabaseCount('care_assignments', 0);
    }

    public function test_scoped_recipient_cannot_redelegate_access(): void
    {
        $this->signIn($this->issuer)->post($this->endpoint(), $this->grantData())->assertRedirect();
        $issuerPractitioner = DB::table('practitioners')->where('tenant_id', $this->issuer->tenant_id)->where('user_id', $this->issuer->id)->first();
        $this->signIn($this->recipient)->post($this->endpoint(), array_replace($this->grantData(), ['practitioner_id' => $issuerPractitioner->id]))->assertForbidden();
        $this->assertDatabaseCount('care_assignments', 1);
    }

    public function test_existing_broad_active_assignment_can_issue_scoped_delegation(): void
    {
        app(TenantRunner::class)->run(Tenant::findOrFail($this->issuer->tenant_id), fn () => CareAssignment::create([
            'patient_id' => $this->patient->id, 'practitioner_id' => $this->recipientPractitioner->id,
            'granted_by' => $this->issuer->id, 'valid_from' => now(), 'valid_to' => now()->addDays(10), 'purpose' => 'consultation',
        ]));
        $issuerPractitioner = DB::table('practitioners')->where('tenant_id', $this->issuer->tenant_id)->where('user_id', $this->issuer->id)->first();
        $this->signIn($this->recipient)->post($this->endpoint(), array_replace($this->grantData(), ['practitioner_id' => $issuerPractitioner->id]))->assertRedirect();
        $this->assertDatabaseHas('care_assignments', ['granted_by' => $this->recipient->id, 'appointment_id' => $this->appointment->id, 'practitioner_id' => $issuerPractitioner->id]);
    }
}
