<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\ClinicHour;
use App\Models\Operatory;
use App\Models\PatientProfile;
use App\Models\Practitioner;
use App\Models\PractitionerShift;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public static function resources(): array
    {
        return [['practitioner', 'application'], ['operatory', 'application'], ['practitioner', 'constraint'], ['operatory', 'constraint']];
    }

    #[DataProvider('resources')]
    public function test_real_parallel_booking_connections_reserve_each_resource_once(string $resource, string $mode): void
    {
        $tenant = Tenant::create(['name' => 'Parallel', 'subdomain' => 'parallel', 'timezone' => 'Asia/Manila', 'currency' => 'PHP', 'lifecycle_status' => 'active']);
        app()->instance(TenantContext::class, new TenantContext($tenant));
        DB::table('billing_entitlements')->insert(['tenant_id' => $tenant->id, 'tier' => 'pro', 'bookings_enabled' => true, 'over_limit' => false, 'practitioner_limit' => 10, 'chair_limit' => 10, 'reconciled_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $actor = User::create(['name' => 'Receptionist', 'email' => 'parallel@example.test', 'password' => 'password', 'status' => 'active', 'email_verified_at' => now()]);
        DB::table('user_roles')->insert(['tenant_id' => $tenant->id, 'user_id' => $actor->id, 'role' => 'receptionist']);
        $patient = PatientProfile::create(['first_name' => 'Synthetic', 'last_name' => 'Parallel']);
        $service = Service::create(['name' => 'Consultation', 'category' => 'General', 'duration_minutes' => 30, 'buffer_before_minutes' => 0, 'buffer_after_minutes' => 15, 'price_minor' => 10000, 'currency' => 'PHP', 'active' => true]);
        $practitioners = $chairs = [];
        for ($index = 0; $index < 2; $index++) {
            $doctor = User::create(['name' => 'Doctor '.$index, 'email' => 'doctor'.$index.'@example.test', 'password' => 'password', 'status' => 'active', 'email_verified_at' => now()]);
            DB::table('user_roles')->insert(['tenant_id' => $tenant->id, 'user_id' => $doctor->id, 'role' => 'practitioner']);
            $practitioners[] = $practitioner = Practitioner::create(['user_id' => $doctor->id, 'active' => true]);
            $chairs[] = $chair = Operatory::create(['name' => 'Chair '.$index, 'active' => true]);
            DB::table('practitioner_services')->insert(['tenant_id' => $tenant->id, 'practitioner_id' => $practitioner->id, 'service_id' => $service->id]);
            DB::table('operatory_services')->insert(['tenant_id' => $tenant->id, 'operatory_id' => $chair->id, 'service_id' => $service->id]);
            foreach (range(1, 7) as $weekday) {
                PractitionerShift::create(['practitioner_id' => $practitioner->id, 'weekday' => $weekday, 'local_start' => '09:00', 'local_end' => '17:00']);
            }
        }
        foreach (range(1, 7) as $weekday) {
            ClinicHour::create(['weekday' => $weekday, 'local_start' => '09:00', 'local_end' => '17:00']);
        }
        $gate = tempnam(sys_get_temp_dir(), 'smilesuite-race-');
        unlink($gate);
        $code = <<<'PHP'
        require $argv[1].'/vendor/autoload.php';
        $app = require $argv[1].'/bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $input = json_decode(base64_decode($argv[2]), true);
        config(['database.default' => 'pgsql', 'database.connections.pgsql' => $input['connection']]);
        Illuminate\Support\Facades\DB::purge();
        Illuminate\Support\Facades\DB::statement("SET statement_timeout = '5s'");
        Carbon\CarbonImmutable::setTestNow('2026-10-26 00:00:00 UTC');
        Illuminate\Support\Carbon::setTestNow('2026-10-26 00:00:00 UTC');
        $app->instance(App\Tenancy\TenantContext::class, new App\Tenancy\TenantContext(App\Models\Tenant::findOrFail($input['tenant'])));
        // Both processes establish distinct live PostgreSQL sessions before the parent opens the barrier.
        $pid = Illuminate\Support\Facades\DB::selectOne('select pg_backend_pid() as pid')->pid;
        file_put_contents($input['gate'].'.'.$input['key'].'.ready', (string) $pid);
        $deadline = microtime(true) + 15;
        while (! file_exists($input['gate'])) { if (microtime(true) > $deadline) { exit(3); } usleep(10000); }
        try {
            if ($input['mode'] === 'constraint') {
                $data = $input['data'];
                $id = Illuminate\Support\Facades\DB::transaction(function () use ($input, $data) {
                    $id = Illuminate\Support\Facades\DB::table('appointments')->insertGetId(array_merge($data, ['tenant_id' => $input['tenant'], 'public_code' => Illuminate\Support\Str::ulid()->toBase32(), 'start_at' => '2026-10-27 02:00:00+00', 'end_at' => '2026-10-27 02:30:00+00', 'occupied_start_at' => '2026-10-27 02:00:00+00', 'occupied_end_at' => '2026-10-27 02:45:00+00', 'status' => 'scheduled', 'price_minor' => 10000, 'currency' => 'PHP', 'duration_minutes' => 30, 'buffer_before_minutes' => 0, 'buffer_after_minutes' => 15, 'timezone' => 'Asia/Manila', 'version' => 1, 'payment_method' => 'pay_at_clinic', 'created_at' => now(), 'updated_at' => now()]));
                    usleep(350000);
                    return $id;
                }, 3);
                echo 'booked:'.$id.PHP_EOL;
            } else {
                $appointment = $app->make(App\Scheduling\BookingService::class)->book(App\Models\User::findOrFail($input['actor']), $input['data'], $input['key']);
                echo 'booked:'.$appointment->id.PHP_EOL;
            }
        } catch (Illuminate\Validation\ValidationException $exception) {
            echo 'conflict'.PHP_EOL;
        } catch (Illuminate\Database\QueryException $exception) {
            if (($exception->errorInfo[0] ?? '') !== '23P01') { throw $exception; }
            echo 'conflict'.PHP_EOL;
        }
        PHP;
        $processes = $outputs = [];
        try {
            for ($index = 0; $index < 2; $index++) {
                $data = ['patient_id' => $patient->id, 'service_id' => $service->id, 'practitioner_id' => $practitioners[$resource === 'practitioner' ? 0 : $index]->id, 'operatory_id' => $chairs[$resource === 'operatory' ? 0 : $index]->id, 'start_at' => '2026-10-27T10:00:00+08:00'];
                $input = base64_encode(json_encode(['connection' => config('database.connections.pgsql'), 'tenant' => $tenant->id, 'actor' => $actor->id, 'data' => $data, 'key' => 'parallel-'.$index, 'gate' => $gate, 'mode' => $mode]));
                $outputPath = $gate.'.'.$index.'.out';
                $errorPath = $gate.'.'.$index.'.err';
                $process = proc_open([PHP_BINARY, '-r', $code, base_path(), $input], [['pipe', 'r'], ['file', $outputPath, 'w'], ['file', $errorPath, 'w']], $pipes, base_path());
                $this->assertIsResource($process);
                fclose($pipes[0]);
                $processes[] = [$process, $pipes, $outputPath, $errorPath];
                $outputs[$index] = '';
            }
            $deadline = microtime(true) + 15;
            do {
                if (is_file($gate.'.parallel-0.ready') && is_file($gate.'.parallel-1.ready')) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            $diagnostic = file_get_contents($gate.'.0.err').file_get_contents($gate.'.1.err');
            $this->assertFileExists($gate.'.parallel-0.ready', $diagnostic);
            $this->assertFileExists($gate.'.parallel-1.ready', $diagnostic);
            $this->assertNotSame(file_get_contents($gate.'.parallel-0.ready'), file_get_contents($gate.'.parallel-1.ready'), 'Workers must use different PostgreSQL backend connections.');
            touch($gate);
            foreach ($processes as $index => [$process, $pipes, $outputPath, $errorPath]) {
                $exit = proc_close($process);
                $outputs[$index] = file_get_contents($outputPath);
                $error = file_get_contents($errorPath);
                $this->assertSame('', $error, $error);
                $this->assertSame(0, $exit, implode("\n", $outputs));
                unset($processes[$index]);
            }
            $combined = implode("\n", $outputs);
            $this->assertSame(1, substr_count($combined, 'booked:'), $combined);
            $this->assertSame(1, substr_count($combined, 'conflict'), $combined);
            $this->assertSame(1, Appointment::count());
            $this->assertSame($mode === 'application' ? 1 : 0, DB::table('outbox_messages')->where('tenant_id', $tenant->id)->count());
        } finally {
            touch($gate);
            foreach ($processes as [$process, $pipes]) {
                if (is_resource($process)) {
                    proc_terminate($process);
                }
                foreach ($pipes as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }
                if (is_resource($process)) {
                    proc_close($process);
                }
            }
            @unlink($gate);
            @unlink($gate.'.parallel-0.ready');
            @unlink($gate.'.parallel-1.ready');
            foreach ([0, 1] as $index) {
                @unlink($gate.'.'.$index.'.out');
                @unlink($gate.'.'.$index.'.err');
            }
        }
    }
}
