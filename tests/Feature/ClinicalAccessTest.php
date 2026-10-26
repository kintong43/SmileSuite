<?php

namespace Tests\Feature;

use App\Clinical\ClinicalAccess;
use App\Models\PatientProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicalAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_and_receptionist_have_no_implicit_clinical_access(): void
    {
        $this->seed();
        app(TenantRunner::class)->run(Tenant::where('subdomain', 'harbor')->firstOrFail(), function () {
            $patient = PatientProfile::firstOrFail();
            $owner = User::get()->first(fn ($user) => $user->hasRole('owner'));
            $this->assertFalse(app(ClinicalAccess::class)->canRead($owner, $patient));
        });
    }

    public function test_patient_cannot_read_another_patients_intake(): void
    {
        $this->seed();
        app(TenantRunner::class)->run(Tenant::where('subdomain', 'harbor')->firstOrFail(), function () {
            $user = User::get()->first(fn ($user) => $user->hasRole('patient'));
            $other = PatientProfile::create(['first_name' => 'Synthetic', 'last_name' => 'Other']);
            $this->assertFalse(app(ClinicalAccess::class)->canRead($user, $other));
        });
    }
}
