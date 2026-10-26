<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_landing_page_and_unknown_hosts(): void
    {
        $this->get('http://smilesuite.localhost/')->assertOk()->assertSee('SmileSuite');
        $this->get('http://unknown.invalid/')->assertNotFound();
    }

    public function test_clinic_requires_authentication_and_shows_its_name(): void
    {
        $this->seed();
        $this->get('http://harbor.smilesuite.localhost/login')->assertOk()->assertSee('Harbor');
        $this->get('http://harbor.smilesuite.localhost/dashboard')->assertRedirect('/login');
    }

    public function test_owner_operational_pages_render_without_clinical_content(): void
    {
        $this->seed();
        $user = app(TenantRunner::class)->run(Tenant::where('subdomain', 'harbor')->firstOrFail(), fn () => User::where('email', 'owner@harbor.example.test')->firstOrFail());
        $this->actingAs($user)->withSession(['mfa_confirmed' => true, 'mfa_user_id' => $user->id]);
        foreach (['dashboard', 'schedule', 'patients', 'services', 'settings', 'billing', 'store', 'appointments/create', 'notifications'] as $path) {
            $this->get('http://harbor.smilesuite.localhost/'.$path)->assertOk()->assertDontSee('Internal Server Error');
        }
    }

    public function test_patient_portal_and_own_intake_render(): void
    {
        $this->seed();
        [$user,$patient] = app(TenantRunner::class)->run(Tenant::where('subdomain', 'harbor')->firstOrFail(), function () {
            $user = User::where('email', 'patient@harbor.example.test')->firstOrFail();

            return [$user, $user->patientProfile];
        });
        $this->actingAs($user)->get('http://harbor.smilesuite.localhost/portal')->assertOk();
        $this->get('http://harbor.smilesuite.localhost/patients/'.$patient->id.'/clinical')->assertOk();
    }
}
