<?php

namespace Tests\Feature;

use App\Models\PatientProfile;
use App\Models\PlatformAdmin;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantRunner;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Mockery\MockInterface;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class PlatformTest extends TestCase
{
    use RefreshDatabase;

    private function url(string $path = ''): string
    {
        return 'http://'.config('tenancy.admin_host').'/platform'.$path;
    }

    private function admin(bool $enrolled = true): PlatformAdmin
    {
        return PlatformAdmin::create([
            'name' => 'Synthetic operator', 'email' => 'operator@example.test', 'password' => 'Operator-password-123', 'status' => 'active',
            'two_factor_secret' => $enrolled ? encrypt('SYNTHETICKEY12345') : null,
            'two_factor_confirmed_at' => $enrolled ? now() : null,
        ]);
    }

    public function test_platform_routes_only_exist_on_administrator_host(): void
    {
        $tenant = Tenant::create(['name' => 'Synthetic clinic', 'subdomain' => 'platform-test']);
        foreach (['smilesuite.localhost', $tenant->subdomain.'.smilesuite.localhost', 'unknown.example.test'] as $host) {
            $this->get('http://'.$host.'/platform/login')->assertNotFound();
            $this->post('http://'.$host.'/platform/login', ['email' => 'operator@example.test', 'password' => 'irrelevant'])->assertNotFound();
        }
        $this->get($this->url('/login'))->assertOk()->assertSee('Platform sign in');
        $this->assertFalse(app()->bound(TenantContext::class));
    }

    public function test_clinic_session_and_mfa_do_not_authorize_platform(): void
    {
        $tenant = Tenant::create(['name' => 'Synthetic clinic', 'subdomain' => 'platform-test']);
        $user = app(TenantRunner::class)->run($tenant, fn () => User::create(['name' => 'Clinic owner', 'email' => 'owner@example.test', 'password' => 'Clinic-password-123']));
        $this->actingAs($user, 'web')->withSession(['mfa_confirmed' => true])->get($this->url())->assertRedirect($this->url('/login'));
        $this->assertGuest('platform');
    }

    public function test_platform_login_requires_its_own_mfa_on_every_login(): void
    {
        $admin = $this->admin();
        $this->withSession(['platform_mfa_admin_id' => $admin->id])->post($this->url('/login'), [
            'email' => strtoupper($admin->email), 'password' => 'Operator-password-123',
        ])->assertRedirect($this->url('/security'))->assertSessionMissing('platform_mfa_admin_id');
        $this->assertAuthenticatedAs($admin, 'platform');
        $this->assertGuest('web');
        $this->get($this->url())->assertRedirect($this->url('/security'));
    }

    public function test_mfa_enrollment_is_session_bound_and_saved_only_after_valid_code(): void
    {
        $admin = $this->admin(false);
        $this->mock(TwoFactorAuthenticationProvider::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generateSecretKey')->once()->andReturn('SYNTHETICKEY12345');
            $mock->shouldReceive('verify')->once()->with('SYNTHETICKEY12345', '123456')->andReturn(true);
        });
        $this->actingAs($admin, 'platform')->get($this->url('/security'))->assertOk()->assertSee('SYNTHETICKEY12345');
        $this->assertNull($admin->fresh()->two_factor_secret);
        $this->post($this->url('/security'), ['code' => '123456'])->assertRedirect($this->url())->assertSessionHas('platform_mfa_admin_id', $admin->id)->assertSessionMissing('platform_mfa_enrollment');
        $this->assertSame('SYNTHETICKEY12345', decrypt($admin->fresh()->two_factor_secret));
        $this->assertNotNull($admin->fresh()->two_factor_confirmed_at);
    }

    public function test_existing_mfa_challenge_rejects_invalid_code(): void
    {
        $admin = $this->admin();
        $this->mock(TwoFactorAuthenticationProvider::class, function (MockInterface $mock): void {
            $mock->shouldReceive('verify')->once()->with('SYNTHETICKEY12345', '000000')->andReturn(false);
        });
        $this->actingAs($admin, 'platform')->post($this->url('/security'), ['code' => '000000'])->assertSessionHasErrors('code')->assertSessionMissing('platform_mfa_admin_id');
        $this->get($this->url())->assertRedirect($this->url('/security'));
    }

    public function test_authenticator_challenge_verifies_real_totp_before_access(): void
    {
        $engine = new Google2FA;
        $secret = $engine->generateSecretKey();
        $admin = $this->admin();
        $admin->update(['two_factor_secret' => encrypt($secret)]);
        $this->actingAs($admin, 'platform')->get($this->url())->assertRedirect($this->url('/security'));
        $this->post($this->url('/security'), ['code' => $engine->getCurrentOtp($secret)])
            ->assertRedirect($this->url())->assertSessionHas('platform_mfa_admin_id', $admin->id);
        $this->get($this->url())->assertOk();
    }

    public function test_mfa_enrollment_from_other_identity_or_expired_session_cannot_be_confirmed(): void
    {
        $admin = $this->admin(false);
        $this->mock(TwoFactorAuthenticationProvider::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('verify');
        });
        $this->actingAs($admin, 'platform')->withSession(['platform_mfa_enrollment' => [
            'admin_id' => $admin->id + 1, 'secret' => 'SYNTHETICKEY12345', 'expires_at' => now()->addMinutes(10)->timestamp,
        ]])->post($this->url('/security'), ['code' => '123456'])->assertSessionHasErrors('code');
        $this->withSession(['platform_mfa_enrollment' => [
            'admin_id' => $admin->id, 'secret' => 'SYNTHETICKEY12345', 'expires_at' => now()->subMinute()->timestamp,
        ]])->post($this->url('/security'), ['code' => '123456'])->assertSessionHasErrors('code');
        $this->assertNull($admin->fresh()->two_factor_secret);
    }

    public function test_mfa_from_other_platform_identity_is_rejected(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'platform')->withSession(['platform_mfa_admin_id' => $admin->id + 1])->get($this->url())->assertRedirect($this->url('/security'));
    }

    public function test_registry_lists_only_operational_metadata_without_clinical_queries(): void
    {
        $tenant = Tenant::create(['name' => 'Synthetic registry clinic', 'subdomain' => 'platform-test', 'billing_status' => 'trialing']);
        app(TenantRunner::class)->run($tenant, fn () => PatientProfile::create(['first_name' => 'PrivatePatient', 'last_name' => 'Invisible']));
        $admin = $this->admin();
        DB::enableQueryLog();
        $this->actingAs($admin, 'platform')->withSession(['platform_mfa_admin_id' => $admin->id])->get($this->url())
            ->assertOk()->assertSee('Synthetic registry clinic')->assertSee('Trialing')->assertDontSee('PrivatePatient')->assertDontSee('/patients')->assertDontSee('/schedule')->assertHeader('Cache-Control', 'no-store, private');
        $queries = collect(DB::getQueryLog())->pluck('query')->implode('\n');
        DB::disableQueryLog();
        foreach (['patient_profiles', 'clinical_notes', 'intake_submissions', 'appointments'] as $table) {
            $this->assertStringNotContainsString($table, $queries);
        }
        $this->assertFalse(app()->bound(TenantContext::class));
        $this->get('http://'.config('tenancy.admin_host').'/patients')->assertNotFound();
    }

    public function test_lifecycle_change_is_atomic_and_audited_separately(): void
    {
        $tenant = Tenant::create(['name' => 'Synthetic registry clinic', 'subdomain' => 'platform-test', 'billing_status' => 'active']);
        $admin = $this->admin();
        $this->actingAs($admin, 'platform')->withSession(['platform_mfa_admin_id' => $admin->id])
            ->post($this->url('/tenants/'.$tenant->id.'/lifecycle'), ['lifecycle_status' => 'suspended', 'billing_status' => 'cancelled'])->assertRedirect($this->url());
        $this->assertSame('suspended', $tenant->fresh()->lifecycle_status);
        $this->assertSame('active', $tenant->fresh()->billing_status);
        $this->assertDatabaseHas('platform_audit_events', [
            'platform_admin_id' => $admin->id, 'tenant_id' => $tenant->id, 'action' => 'tenant.lifecycle_changed', 'previous_status' => 'active', 'new_status' => 'suspended',
        ]);
        $this->expectException(QueryException::class);
        DB::table('platform_audit_events')->where('tenant_id', $tenant->id)->delete();
    }

    public function test_unverified_or_disabled_admin_cannot_change_metadata(): void
    {
        $tenant = Tenant::create(['name' => 'Synthetic registry clinic', 'subdomain' => 'platform-test']);
        $admin = $this->admin();
        $path = $this->url('/tenants/'.$tenant->id.'/lifecycle');
        $this->actingAs($admin, 'platform')->post($path, ['lifecycle_status' => 'suspended'])->assertRedirect($this->url('/security'));
        $this->assertSame('active', $tenant->fresh()->lifecycle_status);
        $admin->update(['status' => 'disabled']);
        $this->withSession(['platform_mfa_admin_id' => $admin->id])->post($path, ['lifecycle_status' => 'suspended'])->assertRedirect($this->url('/login'));
        $this->assertGuest('platform');
    }

    public function test_command_creates_hashed_admin_without_mfa_and_refuses_reset(): void
    {
        $this->artisan('platform:admin', ['email' => ' NEW-OPERATOR@example.test ', 'name' => 'New operator'])
            ->expectsQuestion('Password (at least 12 characters with letters and numbers)', 'Strong-secret-1234')->assertSuccessful();
        $admin = PlatformAdmin::where('email', 'new-operator@example.test')->sole();
        $this->assertTrue(Hash::check('Strong-secret-1234', $admin->password));
        $this->assertNull($admin->two_factor_secret);
        $this->artisan('platform:admin', ['email' => 'new-operator@example.test', 'name' => 'Changed operator'])->assertFailed();
        $this->assertSame('New operator', $admin->fresh()->name);
    }

    public function test_command_rejects_weak_password(): void
    {
        $this->artisan('platform:admin', ['email' => 'weak-operator@example.test', 'name' => 'Operator'])
            ->expectsQuestion('Password (at least 12 characters with letters and numbers)', 'weak')->assertFailed();
        $this->assertDatabaseMissing('platform_admins', ['email' => 'weak-operator@example.test']);
    }

    public function test_platform_logout_clears_mfa_and_guard(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'platform')->withSession(['platform_mfa_admin_id' => $admin->id])->post($this->url('/logout'))
            ->assertRedirect($this->url('/login'))->assertSessionMissing('platform_mfa_admin_id');
        $this->assertGuest('platform');
    }
}
