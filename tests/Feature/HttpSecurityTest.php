<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Operatory;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantRunner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class HttpSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_revoked_session_is_rejected_independently_of_session_backend(): void
    {
        $user = $this->account('patient');
        $this->actingAs($user)->withSession(['auth_stamp' => hash('sha256', (string) $user->remember_token)]);
        DB::table('users')->where('id', $user->id)->where('tenant_id', $user->tenant_id)->update(['remember_token' => 'new-revocation-token']);
        $user->refresh();
        $this->get('http://harbor.smilesuite.localhost/portal')->assertRedirect('/login');
    }

    public function test_mfa_throttle_cannot_be_reset_by_supplied_email(): void
    {
        $user = $this->account('owner');
        $this->actingAs($user);
        for ($i = 0; $i < 5; $i++) {
            $this->post('http://harbor.smilesuite.localhost/security', ['code' => '000000', 'email' => 'ignored-'.$i.'@example.test'])->assertSessionHasErrors('code');
        }
        $this->post('http://harbor.smilesuite.localhost/security', ['code' => '000000', 'email' => 'fresh@example.test'])->assertStatus(429);
    }

    public function test_dated_block_keeps_the_submitted_offset_as_a_utc_instant(): void
    {
        $user = $this->account('owner');
        $this->actingAs($user)->withSession(['mfa_confirmed' => true, 'mfa_user_id' => $user->id])->post('http://harbor.smilesuite.localhost/settings/blocks', ['resource_type' => 'clinic', 'starts_at' => '2026-12-25T09:00:00+08:00', 'ends_at' => '2026-12-25T17:00:00+08:00', 'reason' => 'Synthetic closure'])->assertSessionHasNoErrors();
        $block = DB::table('schedule_blocks')->where('tenant_id', $user->tenant_id)->where('reason', 'Synthetic closure')->first();
        $this->assertSame('2026-12-25 01:00:00', CarbonImmutable::parse($block->starts_at)->utc()->format('Y-m-d H:i:s'));
    }

    public function test_second_encounter_note_requires_an_explicit_amendment(): void
    {
        $user = $this->account('practitioner');
        $appointment = DB::table('appointments')->where('tenant_id', $user->tenant_id)->whereIn('status', ['scheduled', 'confirmed'])->orderBy('id')->first();
        $url = 'http://harbor.smilesuite.localhost/patients/'.$appointment->patient_id.'/notes';
        $this->actingAs($user)->withSession(['mfa_confirmed' => true, 'mfa_user_id' => $user->id]);
        $this->post($url, ['appointment_id' => $appointment->id, 'content' => 'Synthetic signed note'])->assertSessionHasNoErrors();
        $this->post($url, ['appointment_id' => $appointment->id, 'content' => 'Another note without an amendment reference'])->assertSessionHasErrors('supersedes_id');
        $this->assertDatabaseCount('clinical_notes', 1);
    }

    public function test_patient_export_is_own_intake_only_and_owner_cannot_download_it(): void
    {
        $user = $this->account('patient');
        $patient = DB::table('patient_profiles')->where('user_id', $user->id)->first();
        $url = 'http://harbor.smilesuite.localhost/patients/'.$patient->id.'/intake-export';
        $this->actingAs($user)->get($url)->assertOk()->assertHeader('content-disposition', 'attachment; filename="my-intake.json"')->assertJsonMissingPath('clinical_notes');
        $owner = app(TenantRunner::class)->run(Tenant::findOrFail($user->tenant_id), fn () => User::where('email', 'owner@harbor.example.test')->firstOrFail());
        $this->actingAs($owner)->withSession(['mfa_confirmed' => true, 'mfa_user_id' => $owner->id])->get($url)->assertForbidden();
    }

    private function account(string $role): User
    {
        $this->seed();

        return app(TenantRunner::class)->run(Tenant::where('subdomain', 'harbor')->firstOrFail(), fn () => User::where('email', $role.'@harbor.example.test')->firstOrFail());
    }

    public function test_clinic_owner_cannot_open_intake_without_care_role(): void
    {
        $owner = $this->account('owner');
        $patient = DB::table('patient_profiles')->where('tenant_id', $owner->tenant_id)->first();
        $this->actingAs($owner)->withSession(['mfa_confirmed' => true, 'mfa_user_id' => $owner->id])->get('http://harbor.smilesuite.localhost/patients/'.$patient->id.'/clinical')->assertForbidden();
    }

    public function test_patient_cannot_view_other_patient_or_other_clinic(): void
    {
        $user = $this->account('patient');
        $other = DB::table('patient_profiles')->where('tenant_id', $user->tenant_id)->whereNull('user_id')->first();
        $this->actingAs($user)->get('http://harbor.smilesuite.localhost/patients/'.$other->id.'/clinical')->assertForbidden();
        $this->get('http://cedar.smilesuite.localhost/portal')->assertForbidden();
    }

    public function test_staff_mfa_and_owner_settings_are_required(): void
    {
        $user = $this->account('receptionist');
        $this->actingAs($user)->get('http://harbor.smilesuite.localhost/dashboard')->assertRedirect('/security');
        $this->withSession(['mfa_confirmed' => true, 'mfa_user_id' => $user->id])->get('http://harbor.smilesuite.localhost/settings')->assertForbidden();
    }

    public function test_password_reset_is_tenant_bound_even_for_identical_email(): void
    {
        Mail::fake();
        $user = $this->account('patient');
        $this->app['auth']->forgetGuards();
        app(TenantRunner::class)->run(Tenant::where('subdomain', 'cedar')->firstOrFail(), fn () => User::create(['name' => 'Separate account', 'email' => $user->email, 'password' => 'Another-password-2026']));
        DB::table('password_reset_tokens')->insert(['tenant_id' => $user->tenant_id, 'email' => $user->email, 'token' => Hash::make('tenant-bound-secret'), 'created_at' => now()]);
        $this->post('http://cedar.smilesuite.localhost/reset-password/tenant-bound-secret', ['email' => $user->email, 'password' => 'A-new-password-2026', 'password_confirmation' => 'A-new-password-2026'])->assertSessionHasErrors('email');
        $this->assertDatabaseHas('password_reset_tokens', ['tenant_id' => $user->tenant_id, 'email' => $user->email]);
    }

    public function test_last_owner_cannot_remove_own_owner_role(): void
    {
        $owner = $this->account('owner');
        $this->actingAs($owner)->withSession(['mfa_confirmed' => true, 'mfa_user_id' => $owner->id])->post('http://harbor.smilesuite.localhost/settings/staff/'.$owner->id, ['roles' => ['receptionist'], 'status' => 'active'])->assertSessionHasErrors('roles');
        $this->assertDatabaseHas('user_roles', ['tenant_id' => $owner->tenant_id, 'user_id' => $owner->id, 'role' => 'owner']);
    }

    public function test_patient_cannot_elevate_own_roles(): void
    {
        $patient = $this->account('patient');
        $this->actingAs($patient)->post('http://harbor.smilesuite.localhost/settings/staff/'.$patient->id, ['roles' => ['owner'], 'status' => 'active'])->assertForbidden();
    }

    public function test_staff_update_preserves_patient_role_and_revokes_only_target_sessions(): void
    {
        $owner = $this->account('owner');
        $staff = DB::table('users')->where('tenant_id', $owner->tenant_id)->where('email', 'receptionist@harbor.example.test')->first();
        DB::table('user_roles')->insert(['tenant_id' => $owner->tenant_id, 'user_id' => $staff->id, 'role' => 'patient']);
        foreach (['staff-session' => $staff->id, 'owner-session' => $owner->id] as $id => $userId) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $userId, 'payload' => '', 'last_activity' => time()]);
        }
        $this->actingAs($owner)->withSession(['mfa_confirmed' => true, 'mfa_user_id' => $owner->id])->post('http://harbor.smilesuite.localhost/settings/staff/'.$staff->id, ['roles' => ['owner', 'receptionist'], 'status' => 'active'])->assertRedirect();
        $this->assertDatabaseHas('user_roles', ['tenant_id' => $owner->tenant_id, 'user_id' => $staff->id, 'role' => 'patient']);
        $this->assertDatabaseHas('user_roles', ['tenant_id' => $owner->tenant_id, 'user_id' => $staff->id, 'role' => 'owner']);
        $this->assertDatabaseMissing('sessions', ['id' => 'staff-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'owner-session']);
        $this->assertDatabaseHas('audit_events', ['tenant_id' => $owner->tenant_id, 'action' => 'staff.update', 'resource_id' => $staff->id]);
    }

    public function test_owner_cannot_disable_last_owner_or_assign_platform_role(): void
    {
        $owner = $this->account('owner');
        $this->actingAs($owner)->withSession(['mfa_confirmed' => true, 'mfa_user_id' => $owner->id])->post('http://harbor.smilesuite.localhost/settings/staff/'.$owner->id, ['roles' => ['owner'], 'status' => 'disabled'])->assertSessionHasErrors('roles');
        $this->post('http://harbor.smilesuite.localhost/settings/staff/'.$owner->id, ['roles' => ['platform_admin'], 'status' => 'active'])->assertSessionHasErrors('roles.0');
        $this->assertDatabaseHas('users', ['id' => $owner->id, 'status' => 'active']);
    }

    public function test_practitioner_role_removal_and_resource_deactivation_preserve_bookings(): void
    {
        $owner = $this->account('owner');
        $doctor = DB::table('practitioners')->where('tenant_id', $owner->tenant_id)->first();
        $chair = DB::table('operatories')->where('tenant_id', $owner->tenant_id)->first();
        $this->actingAs($owner)->withSession(['mfa_confirmed' => true, 'mfa_user_id' => $owner->id])->post('http://harbor.smilesuite.localhost/settings/staff/'.$doctor->user_id, ['roles' => ['receptionist'], 'status' => 'active'])->assertSessionHasErrors('roles');
        $this->post('http://harbor.smilesuite.localhost/settings/resources/chair/'.$chair->id.'/deactivate')->assertSessionHasErrors('schedule');
        $this->assertDatabaseHas('user_roles', ['user_id' => $doctor->user_id, 'role' => 'practitioner']);
        $this->assertDatabaseHas('operatories', ['id' => $chair->id, 'active' => true]);
    }

    public function test_eligibility_changes_are_tenant_bound_and_conflicts_roll_back(): void
    {
        $owner = $this->account('owner');
        $chair = DB::table('operatories')->where('tenant_id', $owner->tenant_id)->first();
        $foreignService = DB::table('services')->where('tenant_id', '!=', $owner->tenant_id)->first();
        $this->actingAs($owner)->withSession(['mfa_confirmed' => true, 'mfa_user_id' => $owner->id])->post('http://harbor.smilesuite.localhost/settings/eligibility', ['type' => 'chair', 'resource_id' => $chair->id, 'service_ids' => [$foreignService->id]])->assertSessionHasErrors('service_ids');
        $this->post('http://harbor.smilesuite.localhost/settings/eligibility', ['type' => 'chair', 'resource_id' => $chair->id, 'service_ids' => []])->assertSessionHasErrors('schedule');
        $this->assertSame(3, DB::table('operatory_services')->where('tenant_id', $owner->tenant_id)->where('operatory_id', $chair->id)->count());
    }

    public function test_unused_resource_can_be_deactivated_without_deletion(): void
    {
        $owner = $this->account('owner');
        $chair = app(TenantRunner::class)->run(Tenant::findOrFail($owner->tenant_id), fn () => Operatory::create(['name' => 'Unused demo chair']));
        $this->actingAs($owner)->withSession(['mfa_confirmed' => true, 'mfa_user_id' => $owner->id])->post('http://harbor.smilesuite.localhost/settings/resources/chair/'.$chair->id.'/deactivate')->assertRedirect();
        $this->assertDatabaseHas('operatories', ['id' => $chair->id, 'active' => false]);
        $this->assertDatabaseHas('audit_events', ['tenant_id' => $owner->tenant_id, 'action' => 'resource.deactivate', 'resource_id' => $chair->id]);
    }

    public function test_owner_settings_render_team_and_service_compatibility_forms(): void
    {
        $owner = $this->account('owner');
        $this->actingAs($owner)->withSession(['mfa_confirmed' => true, 'mfa_user_id' => $owner->id])
            ->get('http://harbor.smilesuite.localhost/settings')->assertOk()->assertSee('Save team access')->assertSee('Save compatible services')->assertSee('Deactivate chair');
    }

    public function test_changing_own_roles_requires_a_fresh_sign_in(): void
    {
        $owner = $this->account('owner');
        $this->actingAs($owner)->withSession(['mfa_confirmed' => true, 'mfa_user_id' => $owner->id])
            ->post('http://harbor.smilesuite.localhost/settings/staff/'.$owner->id, ['roles' => ['owner', 'receptionist'], 'status' => 'active'])->assertRedirect('/login');
        $this->assertGuest();
        $this->assertDatabaseHas('user_roles', ['user_id' => $owner->id, 'role' => 'receptionist']);
    }

    public function test_switching_authenticated_staff_accounts_clears_mfa_state(): void
    {
        $owner = $this->account('owner');
        $this->actingAs($owner)->withSession(['mfa_confirmed' => true, 'mfa_user_id' => $owner->id, 'mfa_enrollment_secret' => 'previous-secret']);
        $this->post('http://harbor.smilesuite.localhost/login', ['email' => 'receptionist@harbor.example.test', 'password' => 'SmileSuite-demo-2026!'])->assertRedirect('/security')->assertSessionMissing('mfa_confirmed')->assertSessionMissing('mfa_enrollment_secret');
        $this->get('http://harbor.smilesuite.localhost/dashboard')->assertRedirect('/security');
    }

    public function test_mfa_confirmation_is_bound_to_current_user(): void
    {
        $owner = $this->account('owner');
        $this->actingAs($owner)->withSession(['mfa_confirmed' => true, 'mfa_user_id' => $owner->id + 1000])->get('http://harbor.smilesuite.localhost/dashboard')->assertRedirect('/security');
    }

    public function test_current_care_assignment_does_not_authorize_notes_on_old_encounter(): void
    {
        $practitioner = $this->account('practitioner');
        [$old,$profile] = app(TenantRunner::class)->run(Tenant::findOrFail($practitioner->tenant_id), function () {
            $existing = Appointment::orderBy('start_at')->firstOrFail();
            $copy = $existing->replicate(['public_code']);
            $copy->public_code = 'synthetic-old-encounter';
            foreach (['start_at', 'end_at', 'occupied_start_at', 'occupied_end_at'] as $field) {
                $copy->$field = $existing->$field->subMonths(2);
            }
            $copy->status = 'completed';
            $copy->save();

            return [$copy, $existing->patient];
        });
        $this->actingAs($practitioner)->withSession(['mfa_confirmed' => true, 'mfa_user_id' => $practitioner->id])->post('http://harbor.smilesuite.localhost/patients/'.$profile->id.'/notes', ['appointment_id' => $old->id, 'content' => 'Should never be saved'])->assertForbidden();
        $this->assertDatabaseMissing('clinical_notes', ['appointment_id' => $old->id]);
    }
}
