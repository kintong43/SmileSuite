<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role = 'patient'): User
    {
        $this->seed();

        return app(TenantRunner::class)->run(Tenant::where('subdomain', 'harbor')->firstOrFail(), fn () => User::where('email', $role.'@harbor.example.test')->firstOrFail());
    }

    public function test_patient_updates_only_own_profile_and_contact(): void
    {
        $user = $this->account();
        $this->actingAs($user)->post('http://harbor.smilesuite.localhost/profile', ['name' => 'Alex Synthetic', 'email' => $user->email, 'first_name' => 'Alex', 'last_name' => 'Synthetic', 'phone' => '09000000000'])->assertRedirect('/profile');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Alex Synthetic', 'email_verified_at' => $user->email_verified_at]);
        $this->assertDatabaseHas('patient_profiles', ['user_id' => $user->id, 'first_name' => 'Alex', 'last_name' => 'Synthetic', 'phone' => '09000000000']);
        $this->assertDatabaseHas('audit_events', ['actor_id' => $user->id, 'action' => 'profile.updated']);
    }

    public function test_profile_rejects_role_and_tenant_injection(): void
    {
        $user = $this->account();
        $this->actingAs($user)->post('http://harbor.smilesuite.localhost/profile', ['name' => 'Injected', 'email' => $user->email, 'roles' => ['owner'], 'tenant_id' => 999])->assertSessionHasErrors(['roles', 'tenant_id']);
        $this->assertDatabaseMissing('user_roles', ['user_id' => $user->id, 'role' => 'owner']);
    }

    public function test_email_change_requires_password_and_restarts_verification(): void
    {
        Mail::fake();
        $user = $this->account();
        $payload = ['name' => $user->name, 'email' => 'new-address@example.test', 'first_name' => 'Alex', 'last_name' => 'Example'];
        $this->actingAs($user)->post('http://harbor.smilesuite.localhost/profile', $payload)->assertSessionHasErrors('current_password');
        $this->post('http://harbor.smilesuite.localhost/profile', $payload + ['current_password' => 'SmileSuite-demo-2026!'])->assertRedirect('/profile');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'new-address@example.test', 'email_verified_at' => null]);
        $this->assertDatabaseHas('patient_profiles', ['user_id' => $user->id, 'email' => 'new-address@example.test']);
    }

    public function test_duplicate_same_clinic_email_is_rejected_but_other_clinic_email_is_independent(): void
    {
        $user = $this->account();
        $this->actingAs($user)->post('http://harbor.smilesuite.localhost/profile', ['name' => $user->name, 'email' => 'owner@harbor.example.test', 'current_password' => 'SmileSuite-demo-2026!', 'first_name' => 'Alex', 'last_name' => 'Example'])->assertSessionHasErrors('email');
        $this->post('http://harbor.smilesuite.localhost/profile', ['name' => $user->name, 'email' => 'owner@cedar.example.test', 'current_password' => 'SmileSuite-demo-2026!', 'first_name' => 'Alex', 'last_name' => 'Example'])->assertRedirect('/profile');
    }

    public function test_password_change_rotates_revocation_token_and_rejects_wrong_current_password(): void
    {
        $user = $this->account();
        $this->actingAs($user)->post('http://harbor.smilesuite.localhost/profile/password', ['current_password' => 'wrong', 'password' => 'Changed-password-2026!', 'password_confirmation' => 'Changed-password-2026!'])->assertSessionHasErrors('current_password');
        $this->post('http://harbor.smilesuite.localhost/profile/password', ['current_password' => 'SmileSuite-demo-2026!', 'password' => 'Changed-password-2026!', 'password_confirmation' => 'Changed-password-2026!'])->assertRedirect('/login');
        $this->assertTrue(Hash::check('Changed-password-2026!', DB::table('users')->where('id', $user->id)->value('password')));
        $this->assertNotNull(DB::table('users')->where('id', $user->id)->value('remember_token'));
    }

    public function test_profile_requests_require_login_and_matching_tenant(): void
    {
        $user = $this->account();
        $this->post('http://harbor.smilesuite.localhost/profile', ['name' => 'Guest'])->assertRedirect('/login');
        $this->actingAs($user)->post('http://cedar.smilesuite.localhost/profile', ['name' => 'Cross clinic'])->assertForbidden();
    }

    public function test_profile_page_shows_own_contact_and_password_forms(): void
    {
        $user = $this->account();
        $this->actingAs($user)->get('http://harbor.smilesuite.localhost/profile')->assertOk()->assertSee('My profile')->assertSee($user->email)->assertSee('Change password')->assertDontSee('owner@cedar.example.test');
    }

    public function test_password_change_invalidates_an_existing_reset_link(): void
    {
        $user = $this->account();
        DB::table('password_reset_tokens')->insert(['tenant_id' => $user->tenant_id, 'email' => $user->email, 'token' => Hash::make('old-reset-token'), 'created_at' => now()]);
        $this->actingAs($user)->post('http://harbor.smilesuite.localhost/profile/password', ['current_password' => 'SmileSuite-demo-2026!', 'password' => 'Changed-password-2026!', 'password_confirmation' => 'Changed-password-2026!'])->assertRedirect('/login');
        $this->assertDatabaseMissing('password_reset_tokens', ['tenant_id' => $user->tenant_id, 'email' => $user->email]);
        $this->post('http://harbor.smilesuite.localhost/reset-password/old-reset-token', ['email' => $user->email, 'password' => 'Another-password-2026!', 'password_confirmation' => 'Another-password-2026!'])->assertSessionHasErrors();
        $this->assertTrue(Hash::check('Changed-password-2026!', DB::table('users')->where('id', $user->id)->value('password')));
    }
}
