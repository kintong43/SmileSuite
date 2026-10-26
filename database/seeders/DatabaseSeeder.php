<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\BillingEntitlement;
use App\Models\ClinicHour;
use App\Models\Operatory;
use App\Models\OperatoryService;
use App\Models\PatientProfile;
use App\Models\Practitioner;
use App\Models\PractitionerBreak;
use App\Models\PractitionerService;
use App\Models\PractitionerShift;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRole;
use App\Tenancy\TenantRunner;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Synthetic demonstration seeding is restricted to local/testing environments.');
        }
        foreach ([['harbor', 'Harbor Dental Studio', 'Lea Harbor', 'Mika Sol'], ['cedar', 'Cedar Family Dentistry', 'Noel Cedar', 'Ari Vale']] as [$subdomain,$name,$ownerName,$doctorName]) {
            $tenant = Tenant::firstOrCreate(['subdomain' => $subdomain], [
                'name' => $name, 'timezone' => 'Asia/Manila', 'currency' => 'PHP', 'lifecycle_status' => 'active',
                'billing_status' => 'sandbox', 'branding' => ['tagline' => 'Synthetic demonstration clinic', 'accent' => '#16665c', 'synthetic' => true],
            ]);
            app(TenantRunner::class)->run($tenant, function () use ($tenant, $subdomain, $ownerName, $doctorName): void {
                DB::transaction(function () use ($tenant, $subdomain, $ownerName, $doctorName): void {
                    $password = Hash::make('SmileSuite-demo-2026!');
                    $accounts = [];
                    foreach (['owner' => $ownerName, 'practitioner' => $doctorName, 'receptionist' => 'Sam Welcome', 'patient' => 'Alex Example'] as $role => $name) {
                        $user = User::firstOrCreate(['email' => $role.'@'.$subdomain.'.example.test'], ['name' => $name.' (Demo)', 'password' => $password, 'email_verified_at' => now(), 'status' => 'active']);
                        UserRole::firstOrCreate(['user_id' => $user->id, 'role' => $role]);
                        $accounts[$role] = $user;
                    }
                    $doctor = Practitioner::firstOrCreate(['user_id' => $accounts['practitioner']->id], ['display_title' => 'Dr.', 'specialization' => 'General dentistry', 'active' => true]);
                    $chair = Operatory::firstOrCreate(['name' => 'Chair 1'], ['active' => true]);
                    $services = [];
                    foreach ([['Dental consultation', 'Consultation', 30, 50000], ['Routine cleaning', 'Preventive care', 45, 120000], ['Comprehensive examination', 'Assessment', 60, 180000]] as [$name,$category,$duration,$price]) {
                        $service = Service::firstOrCreate(['name' => $name], ['category' => $category, 'description' => 'Synthetic demo service; clinical suitability is assessed by the clinic.', 'duration_minutes' => $duration, 'buffer_before_minutes' => 0, 'buffer_after_minutes' => 15, 'price_minor' => $price, 'currency' => 'PHP', 'active' => true]);
                        PractitionerService::firstOrCreate(['practitioner_id' => $doctor->id, 'service_id' => $service->id]);
                        OperatoryService::firstOrCreate(['operatory_id' => $chair->id, 'service_id' => $service->id]);
                        $services[] = $service;
                    }
                    for ($weekday = 1; $weekday <= 7; $weekday++) {
                        ClinicHour::firstOrCreate(['weekday' => $weekday, 'local_start' => '08:00:00', 'local_end' => '18:00:00']);
                        PractitionerShift::firstOrCreate(['practitioner_id' => $doctor->id, 'weekday' => $weekday, 'local_start' => '08:00:00', 'local_end' => '18:00:00']);
                        PractitionerBreak::firstOrCreate(['practitioner_id' => $doctor->id, 'weekday' => $weekday, 'local_start' => '12:00:00', 'local_end' => '13:00:00']);
                    }
                    $patients = [];
                    foreach ([['Alex', 'Example', $accounts['patient']->id], ['Jamie', 'Sample', null], ['Taylor', 'Demo', null], ['Riley', 'Test', null]] as [$first,$last,$userId]) {
                        $patient = PatientProfile::firstOrCreate(['first_name' => $first, 'last_name' => $last], ['user_id' => $userId, 'email' => strtolower($first).'@'.$subdomain.'.example.test', 'date_of_birth' => '1990-01-15', 'status' => 'active']);
                        $patients[] = $patient;
                    }
                    if (class_exists(BillingEntitlement::class)) {
                        BillingEntitlement::firstOrCreate(['tenant_id' => $tenant->id], ['tier' => 'starter', 'bookings_enabled' => true, 'chair_limit' => 1, 'practitioner_limit' => 2, 'over_limit' => false, 'reconciled_at' => now()]);
                    }
                    $today = CarbonImmutable::today($tenant->timezone);
                    $next = $today->addDay();
                    while ($next->isWeekend()) {
                        $next = $next->addDay();
                    }
                    foreach ([$today, $next] as $date) {
                        foreach (['09:00', '10:30', '14:00'] as $index => $time) {
                            $service = $services[$index];
                            $start = CarbonImmutable::parse($date->format('Y-m-d').' '.$time, $tenant->timezone)->utc();
                            $end = $start->addMinutes($service->duration_minutes);
                            $code = strtoupper($subdomain).'-DEMO-'.$date->format('Ymd').'-'.($index + 1);
                            if (Appointment::where('public_code', $code)->exists()) {
                                continue;
                            }
                            $appointment = Appointment::create([
                                'public_code' => $code, 'patient_id' => $patients[$index]->id, 'practitioner_id' => $doctor->id, 'service_id' => $service->id, 'operatory_id' => $chair->id,
                                'start_at' => $start, 'end_at' => $end, 'occupied_start_at' => $start, 'occupied_end_at' => $end->addMinutes(15), 'status' => $index === 1 ? 'confirmed' : 'scheduled',
                                'price_minor' => $service->price_minor, 'currency' => 'PHP', 'duration_minutes' => $service->duration_minutes, 'buffer_before_minutes' => 0, 'buffer_after_minutes' => 15,
                                'timezone' => $tenant->timezone, 'version' => 1, 'payment_method' => 'pay_at_clinic',
                            ]);
                            AppointmentEvent::create(['appointment_id' => $appointment->id, 'actor_id' => $accounts['receptionist']->id, 'new_state' => $appointment->status, 'reason' => 'Synthetic demonstration appointment', 'occurred_at' => now(), 'version' => 1]);
                        }
                    }
                });
            });
        }
    }
}
