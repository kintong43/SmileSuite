<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Operatory;
use App\Models\PatientProfile;
use App\Models\Practitioner;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRole;
use App\Reports\OperationalReportService;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantRunner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private function clinic(string $subdomain = 'reports', string $timezone = 'Asia/Manila'): Tenant
    {
        return Tenant::create(['name' => 'Synthetic reports clinic', 'subdomain' => $subdomain, 'timezone' => $timezone, 'currency' => 'PHP', 'lifecycle_status' => 'active']);
    }

    private function signIn(Tenant $tenant, string $role): User
    {
        $user = app(TenantRunner::class)->run($tenant, function () use ($role): User {
            $user = User::factory()->create(['status' => 'active']);
            UserRole::create(['user_id' => $user->id, 'role' => $role]);

            return $user;
        });
        $this->actingAs($user)->withSession(['mfa_confirmed' => true, 'mfa_user_id' => $user->id]);

        return $user;
    }

    private function appointment(Tenant $tenant, string $start, string $status = 'scheduled', int $price = 50000, string $serviceName = 'Synthetic consultation', string $practitionerName = 'Synthetic doctor'): void
    {
        app(TenantRunner::class)->run($tenant, function () use ($start, $status, $price, $serviceName, $practitionerName): void {
            $patient = PatientProfile::create(['first_name' => 'PRIVATE PATIENT', 'last_name' => 'IDENTITY', 'email' => 'private-patient@example.test']);
            $user = User::factory()->create(['name' => $practitionerName]);
            $practitioner = Practitioner::create(['user_id' => $user->id, 'active' => true]);
            $chair = Operatory::create(['name' => 'Synthetic chair '.$user->id, 'active' => true]);
            $service = Service::create(['name' => $serviceName, 'category' => 'General', 'duration_minutes' => 30, 'buffer_before_minutes' => 0, 'buffer_after_minutes' => 0, 'price_minor' => $price, 'currency' => 'PHP', 'active' => true, 'description' => 'PRIVATE CLINICAL CONTENT']);
            $date = CarbonImmutable::parse($start);
            Appointment::create(['public_code' => 'REPORT-'.fake()->uuid(), 'patient_id' => $patient->id, 'practitioner_id' => $practitioner->id, 'operatory_id' => $chair->id, 'service_id' => $service->id, 'start_at' => $date, 'end_at' => $date->addMinutes(30), 'occupied_start_at' => $date, 'occupied_end_at' => $date->addMinutes(30), 'status' => $status, 'price_minor' => $price, 'currency' => 'PHP', 'duration_minutes' => 30, 'buffer_before_minutes' => 0, 'buffer_after_minutes' => 0, 'timezone' => 'UTC', 'version' => 1]);
        });
    }

    public function test_report_uses_local_day_boundaries_and_current_status_without_other_clinic_or_patient_data(): void
    {
        $tenant = $this->clinic();
        $other = $this->clinic('other-reports');
        $this->appointment($tenant, '2026-10-26T15:59:59Z');
        $this->appointment($tenant, '2026-10-26T16:00:00Z', 'completed', 120000);
        $this->appointment($tenant, '2026-10-27T01:00:00Z', 'cancelled', 90000);
        $this->appointment($tenant, '2026-10-27T15:59:59Z', 'confirmed', 50000);
        $this->appointment($tenant, '2026-10-27T16:00:00Z');
        $this->appointment($other, '2026-10-27T01:00:00Z', 'completed', 999999, 'OTHER CLINIC SERVICE');

        $report = app(TenantRunner::class)->run($tenant, fn (): array => app(OperationalReportService::class)->generate('2026-10-27', '2026-10-27'));

        $this->assertSame(['appointments' => 3, 'completed' => 1, 'scheduled_value_minor' => 170000], $report['totals']);
        $this->assertSame('Asia/Manila', $report['filters']['timezone']);
        $this->assertSame(['cancelled' => 1, 'completed' => 1, 'confirmed' => 1], array_column($report['by_status'], 'appointments', 'label'));
        $this->assertSame(3, array_sum(array_column($report['by_service'], 'appointments')));
        $this->assertSame(3, array_sum(array_column($report['by_practitioner'], 'appointments')));
        $this->assertSame('2026-10-27', $report['by_date'][0]['label']);
        $this->assertStringNotContainsString('PRIVATE', json_encode($report));
        $this->assertStringNotContainsString('OTHER CLINIC', json_encode($report));
    }

    public function test_changing_date_range_changes_counts_and_empty_ranges_return_zero(): void
    {
        $tenant = $this->clinic();
        $this->appointment($tenant, '2026-10-27T01:00:00Z');
        $this->appointment($tenant, '2026-10-28T01:00:00Z', 'completed');

        app(TenantRunner::class)->run($tenant, function (): void {
            $service = app(OperationalReportService::class);
            $this->assertSame(2, $service->generate('2026-10-27', '2026-10-28')['totals']['appointments']);
            $this->assertSame(1, $service->generate('2026-10-28', '2026-10-28')['totals']['completed']);
            $empty = $service->generate('2026-10-29', '2026-10-29');
            $this->assertSame(['appointments' => 0, 'completed' => 0, 'scheduled_value_minor' => 0], $empty['totals']);
            $this->assertSame([], $empty['by_date']);
        });
    }

    public function test_dst_day_includes_both_repeated_hours_and_excludes_next_local_midnight(): void
    {
        $tenant = $this->clinic('dst-reports', 'America/New_York');
        foreach (['2026-11-01T04:00:00Z', '2026-11-01T05:30:00Z', '2026-11-01T06:30:00Z', '2026-11-02T04:59:59Z', '2026-11-02T05:00:00Z'] as $start) {
            $this->appointment($tenant, $start);
        }

        $report = app(TenantRunner::class)->run($tenant, fn (): array => app(OperationalReportService::class)->generate('2026-11-01', '2026-11-01'));

        $this->assertSame(4, $report['totals']['appointments']);
        $this->assertSame(4, $report['by_date'][0]['appointments']);
    }

    #[TestWith(['2026-02-30', '2026-03-01'])]
    #[TestWith(['2026-10-28', '2026-10-27'])]
    #[TestWith(['2025-01-01', '2026-01-02'])]
    #[TestWith(['2026-1-1', '2026-01-02'])]
    public function test_invalid_or_excessive_date_ranges_are_rejected(string $from, string $to): void
    {
        $tenant = $this->clinic();
        $this->expectException(ValidationException::class);

        app(TenantRunner::class)->run($tenant, fn (): array => app(OperationalReportService::class)->generate($from, $to));
    }

    #[TestWith(['patient'])]
    #[TestWith(['practitioner'])]
    public function test_non_operational_roles_cannot_read_or_export_reports(string $role): void
    {
        $tenant = $this->clinic();
        $this->signIn($tenant, $role);

        $this->get('http://reports.smilesuite.localhost/reports')->assertForbidden();
        $this->get('http://reports.smilesuite.localhost/reports/export')->assertForbidden();
    }

    public function test_unauthenticated_report_requests_redirect_to_login(): void
    {
        $this->clinic();

        $this->get('http://reports.smilesuite.localhost/reports')->assertRedirect('http://reports.smilesuite.localhost/login');
        $this->get('http://reports.smilesuite.localhost/reports/export')->assertRedirect('http://reports.smilesuite.localhost/login');
    }

    #[TestWith(['owner'])]
    #[TestWith(['receptionist'])]
    public function test_authorized_staff_can_filter_reports_without_patient_or_medical_data(string $role): void
    {
        $tenant = $this->clinic();
        $this->appointment($tenant, '2026-10-27T01:00:00Z', 'completed');
        $this->appointment($tenant, '2026-10-28T01:00:00Z');
        $this->signIn($tenant, $role);

        $this->get('http://reports.smilesuite.localhost/reports?from_date=2026-10-27&to_date=2026-10-27')
            ->assertOk()->assertSee('Operational reports')->assertSee('Scheduled value')
            ->assertViewHas('report', fn (array $report): bool => $report['totals']['appointments'] === 1 && $report['totals']['completed'] === 1)
            ->assertDontSee('PRIVATE PATIENT')->assertDontSee('PRIVATE CLINICAL CONTENT')->assertDontSee('private-patient@example.test');
    }

    public function test_client_tenant_ids_cannot_select_another_clinic_and_cross_host_sessions_are_rejected(): void
    {
        $tenant = $this->clinic();
        $other = $this->clinic('other-reports');
        $this->appointment($other, '2026-10-27T01:00:00Z', 'completed', 90000, 'OTHER CLINIC SERVICE');
        $this->signIn($tenant, 'owner');

        $this->get('http://reports.smilesuite.localhost/reports?from_date=2026-10-27&to_date=2026-10-27&tenant_id='.$other->id)
            ->assertOk()->assertViewHas('report', fn (array $report): bool => $report['totals']['appointments'] === 0)->assertDontSee('OTHER CLINIC SERVICE');
        $this->get('http://other-reports.smilesuite.localhost/reports')->assertForbidden();
    }

    public function test_invalid_http_filters_return_validation_errors(): void
    {
        $tenant = $this->clinic();
        $this->signIn($tenant, 'owner');

        $this->getJson('http://reports.smilesuite.localhost/reports?from_date=2026-10-28&to_date=2026-10-27')->assertUnprocessable()->assertJsonValidationErrors('to_date');
        $this->getJson('http://reports.smilesuite.localhost/reports/export?from_date[]=2026-10-27')->assertUnprocessable()->assertJsonValidationErrors('from_date');
    }

    public function test_csv_stream_has_only_aggregate_rows_and_neutralizes_spreadsheet_formulas(): void
    {
        $tenant = $this->clinic();
        $this->appointment($tenant, '2026-10-27T01:00:00Z', 'completed', 50000, '=SUM(1,2)', "\t+Doctor");
        $this->signIn($tenant, 'owner');

        $response = $this->get('http://reports.smilesuite.localhost/reports/export?from_date=2026-10-27&to_date=2026-10-27')->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertDownload('operational-report-2026-10-27-2026-10-27.csv');
        $csv = $response->streamedContent();
        $rows = array_map(fn (string $line): array => str_getcsv($line, ',', '"', ''), array_filter(explode("\n", trim($csv))));

        $this->assertSame(['group', 'label', 'currency', 'appointments', 'completed', 'scheduled_value_minor', 'from_date', 'to_date', 'timezone'], $rows[0]);
        $this->assertContains("'=SUM(1,2)", array_column($rows, 1));
        $this->assertContains("'\t+Doctor", array_column($rows, 1));
        $this->assertStringNotContainsString('PRIVATE', $csv);
        $this->assertStringNotContainsString('patient_id', $csv);
        $this->assertFalse(app()->bound(TenantContext::class));
    }

    public function test_report_screen_escapes_service_and_practitioner_names(): void
    {
        $tenant = $this->clinic();
        $this->appointment($tenant, '2026-10-27T01:00:00Z', 'completed', 50000, '<script>service()</script>', '<script>doctor()</script>');
        $this->signIn($tenant, 'owner');

        $this->get('http://reports.smilesuite.localhost/reports?from_date=2026-10-27&to_date=2026-10-27')->assertOk()
            ->assertSee('<script>service()</script>')->assertSee('<script>doctor()</script>')
            ->assertDontSee('<script>service()</script>', false)->assertDontSee('<script>doctor()</script>', false);
    }
}
