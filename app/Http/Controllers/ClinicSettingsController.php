<?php

namespace App\Http\Controllers;

use App\Billing\EntitlementService;
use App\Models\Appointment;
use App\Models\AuditEvent;
use App\Models\ClinicHour;
use App\Models\Operatory;
use App\Models\PatientProfile;
use App\Models\Practitioner;
use App\Models\PractitionerBreak;
use App\Models\PractitionerShift;
use App\Models\ScheduleBlock;
use App\Models\Service;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Models\UserRole;
use App\Scheduling\CalendarRules;
use App\Scheduling\ScheduleGuard;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClinicSettingsController extends Controller
{
    private function owner(Request $request): void
    {
        $actor = User::whereKey($request->user()->id)->where('status', 'active')->first();
        abort_unless($actor && $actor->hasRole('owner'), 403);
    }

    private function audit(Request $request, string $action, string $type, ?int $id = null, array $metadata = []): void
    {
        AuditEvent::create(['actor_id' => $request->user()->id, 'action' => $action, 'resource_type' => $type, 'resource_id' => $id, 'metadata' => $metadata, 'occurred_at' => now()]);
    }

    public function index(Request $request)
    {
        $this->owner($request);

        return view('settings', ['users' => User::orderBy('name')->get(), 'operatories' => Operatory::with('services')->get(), 'practitioners' => Practitioner::with(['user', 'services'])->get(), 'hours' => ClinicHour::orderBy('weekday')->get(), 'blocks' => ScheduleBlock::where('ends_at', '>', now())->orderBy('starts_at')->get(), 'invitations' => StaffInvitation::whereNull('accepted_at')->get(), 'allServices' => Service::where('active', true)->get()]);
    }

    public function staff(Request $request, ScheduleGuard $guard, User $user)
    {
        $this->owner($request);
        $data = $request->validate(['roles' => 'present|nullable|array|max:3', 'roles.*' => 'required|distinct|in:owner,practitioner,receptionist', 'status' => 'required|in:active,disabled']);
        $changed = $guard->mutate(function () use ($request, $data, $user): bool {
            // Refresh authorization and target under the shared clinic scheduling lock.
            $this->owner($request);
            $target = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $priorRoles = $target->roleNames();
            $roles = $data['roles'] ?? [];
            if (in_array('patient', $priorRoles, true)) {
                $roles[] = 'patient';
            }
            if ($roles === []) {
                throw ValidationException::withMessages(['roles' => 'Keep at least one clinic role.']);
            }
            if ($target->status === 'active' && in_array('owner', $priorRoles, true)
                && ($data['status'] !== 'active' || ! in_array('owner', $roles, true))) {
                $anotherOwner = User::where('status', 'active')->whereKeyNot($target->id)
                    ->whereHas('roles', fn ($query) => $query->where('role', 'owner'))->exists();
                if (! $anotherOwner) {
                    throw ValidationException::withMessages(['roles' => 'Keep at least one active clinic owner.']);
                }
            }
            if (in_array('practitioner', $priorRoles, true)
                && ($data['status'] !== 'active' || ! in_array('practitioner', $roles, true))) {
                $practitionerIds = Practitioner::where('user_id', $target->id)->pluck('id');
                if (Appointment::whereIn('practitioner_id', $practitionerIds)
                    ->whereIn('status', ['scheduled', 'confirmed', 'checked_in', 'in_chair'])
                    ->where('occupied_end_at', '>', now())->exists()) {
                    throw ValidationException::withMessages(['roles' => 'Resolve this practitioner’s upcoming appointments before removing access.']);
                }
            }
            sort($roles);
            $before = $priorRoles;
            sort($before);
            if ($roles === $before && $target->status === $data['status']) {
                return false;
            }
            UserRole::where('user_id', $target->id)->whereNotIn('role', $roles)->delete();
            foreach ($roles as $role) {
                UserRole::firstOrCreate(['user_id' => $target->id, 'role' => $role]);
            }
            $priorStatus = $target->status;
            $target->status = $data['status'];
            $target->remember_token = Str::random(60);
            $target->save();
            // User IDs are global primary keys; the tenant is established by the scoped target lookup.
            DB::table('sessions')->where('user_id', $target->id)->delete();
            $this->audit($request, 'staff.update', 'user', $target->id, ['prior_roles' => $before, 'roles' => $roles, 'prior_status' => $priorStatus, 'status' => $target->status]);

            return true;
        });
        if ($changed && $user->id === $request->user()->id) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login')->with('success', 'Your access was updated. Sign in again to continue.');
        }

        return back()->with('success', 'Team access updated. Changed accounts must sign in again.');
    }

    public function deactivate(Request $request, ScheduleGuard $guard, string $type, int $resource)
    {
        $this->owner($request);
        abort_unless(in_array($type, ['chair', 'practitioner', 'service'], true), 404);
        $guard->mutate(function () use ($request, $type, $resource): void {
            $this->owner($request);
            $model = match ($type) {
                'chair' => Operatory::class,
                'practitioner' => Practitioner::class,
                'service' => Service::class,
            };
            $target = $model::whereKey($resource)->lockForUpdate()->firstOrFail();
            $target->update(['active' => false]);
            $this->audit($request, 'resource.deactivate', $type, $target->id);
        });

        return back()->with('success', 'Resource deactivated. Historical appointments are preserved.');
    }

    public function eligibility(Request $request, ScheduleGuard $guard)
    {
        $this->owner($request);
        $data = $request->validate(['type' => 'required|in:chair,practitioner', 'resource_id' => 'required|integer|min:1', 'service_ids' => 'sometimes|array', 'service_ids.*' => 'integer|distinct|min:1']);
        $guard->mutate(function () use ($request, $data): void {
            $this->owner($request);
            $model = $data['type'] === 'chair' ? Operatory::class : Practitioner::class;
            $resource = $model::whereKey($data['resource_id'])->lockForUpdate()->firstOrFail();
            $serviceIds = array_map('intval', $data['service_ids'] ?? []);
            if (Service::whereIn('id', $serviceIds)->where('active', true)->count() !== count($serviceIds)) {
                throw ValidationException::withMessages(['service_ids' => 'Select active services from this clinic.']);
            }
            $table = $data['type'] === 'chair' ? 'operatory_services' : 'practitioner_services';
            $column = $data['type'] === 'chair' ? 'operatory_id' : 'practitioner_id';
            $tenantId = TenantContext::current()->id();
            DB::table($table)->where('tenant_id', $tenantId)->where($column, $resource->id)->whereNotIn('service_id', $serviceIds)->delete();
            foreach ($serviceIds as $serviceId) {
                DB::table($table)->updateOrInsert(['tenant_id' => $tenantId, $column => $resource->id, 'service_id' => $serviceId], []);
            }
            $this->audit($request, 'resource.eligibility', $data['type'], $resource->id);
        });

        return back()->with('success', 'Compatible services updated.');
    }

    public function profile(Request $request, ScheduleGuard $guard)
    {
        $this->owner($request);
        $data = $request->validate(['name' => 'required|string|max:100', 'timezone' => 'required|timezone:all', 'currency' => 'required|string|size:3|regex:/^[A-Z]{3}$/', 'slot_interval_minutes' => 'required|integer|in:5,10,15,20,30,60', 'booking_horizon_days' => 'required|integer|min:1|max:365', 'minimum_lead_minutes' => 'required|integer|min:0|max:10080', 'cancellation_window_hours' => 'required|integer|min:0|max:168']);
        $guard->mutate(function () use ($request, $data) {
            app(TenantContext::class)->tenant->update($data);
            $this->audit($request, 'clinic.update', 'tenant', app(TenantContext::class)->id());
        });

        return back()->with('success', 'Clinic preferences updated.');
    }

    public function service(Request $request, ScheduleGuard $guard)
    {
        $this->owner($request);
        $data = $request->validate(['id' => 'nullable|integer', 'name' => 'required|string|max:120', 'category' => 'required|string|max:80', 'description' => 'nullable|string|max:1000', 'duration_minutes' => 'required|integer|min:5|max:480', 'buffer_before_minutes' => 'required|integer|min:0|max:120', 'buffer_after_minutes' => 'required|integer|min:0|max:120', 'price_minor' => 'required|integer|min:0|max:100000000', 'active' => 'nullable|boolean']);
        $guard->mutate(function () use ($request, $data) {
            $service = empty($data['id']) ? new Service : Service::findOrFail($data['id']);
            $service->fill(collect($data)->except('id')->all());
            $service->currency = app(TenantContext::class)->tenant->currency;
            $service->active = $request->boolean('active', true);
            $service->save();
            $this->audit($request, 'service.save', 'service', $service->id);
        });

        return back()->with('success', 'Service saved. Existing appointment snapshots are preserved.');
    }

    public function resource(Request $request, ScheduleGuard $guard, EntitlementService $entitlements)
    {
        $this->owner($request);
        $data = $request->validate(['type' => 'required|in:chair,practitioner', 'name' => 'required|string|max:120', 'user_id' => 'nullable|integer', 'specialization' => 'nullable|string|max:100', 'service_ids' => 'nullable|array', 'service_ids.*' => 'integer']);
        $guard->mutate(function () use ($request, $data, $entitlements) {
            $entitlements->assertCanAddResource($data['type']);
            $serviceIds = Service::whereIn('id', $data['service_ids'] ?? [])->pluck('id');
            abort_unless($serviceIds->count() === count(array_unique($data['service_ids'] ?? [])), 422);
            if ($data['type'] === 'chair') {
                $resource = Operatory::create(['name' => $data['name'], 'active' => true]);
                $table = 'operatory_services';
                $column = 'operatory_id';
            } else {
                $user = User::findOrFail($data['user_id'] ?? 0);
                abort_unless($user->hasRole('practitioner'), 422);
                $resource = Practitioner::create(['user_id' => $user->id, 'display_title' => $data['name'], 'specialization' => $data['specialization'] ?? null, 'active' => true]);
                $table = 'practitioner_services';
                $column = 'practitioner_id';
            }
            foreach ($serviceIds as $serviceId) {
                DB::table($table)->insert(['tenant_id' => app(TenantContext::class)->id(), $column => $resource->id, 'service_id' => $serviceId]);
            }
            $this->audit($request, 'resource.create', $data['type'], $resource->id);
        });

        return back()->with('success', 'Scheduling resource created.');
    }

    public function hours(Request $request, ScheduleGuard $guard)
    {
        $this->owner($request);
        $data = $request->validate(['type' => 'required|in:clinic,shift,break', 'weekday' => 'required|integer|min:1|max:7', 'local_start' => 'required|date_format:H:i', 'local_end' => 'required|date_format:H:i|after:local_start', 'practitioner_id' => 'nullable|integer']);
        $guard->mutate(function () use ($request, $data) {
            $attributes = collect($data)->except(['type', 'practitioner_id'])->all();
            if ($data['type'] === 'clinic') {
                ClinicHour::updateOrCreate(['weekday' => $data['weekday']], $attributes);
            } else {
                $attributes['practitioner_id'] = Practitioner::findOrFail($data['practitioner_id'] ?? 0)->id;
                ($data['type'] === 'shift' ? PractitionerShift::class : PractitionerBreak::class)::create($attributes);
            }
            $this->audit($request, 'availability.update', $data['type']);
        });

        return back()->with('success', 'Availability updated.');
    }

    public function block(Request $request, ScheduleGuard $guard)
    {
        $this->owner($request);
        $data = $request->validate(['resource_type' => 'required|in:clinic,practitioner,operatory', 'resource_id' => 'nullable|integer', 'starts_at' => 'required|date', 'ends_at' => 'required|date|after:starts_at', 'reason' => 'required|string|max:200']);
        $rules = app(CalendarRules::class);
        $data['starts_at'] = $rules->instant($data['starts_at'], TenantContext::current()->tenant->timezone);
        $data['ends_at'] = $rules->instant($data['ends_at'], TenantContext::current()->tenant->timezone);
        $guard->mutate(function () use ($request, $data) {
            $id = $data['resource_id'] ?? null;
            if ($data['resource_type'] === 'practitioner') {
                Practitioner::findOrFail($id);
            }
            if ($data['resource_type'] === 'operatory') {
                Operatory::findOrFail($id);
            }
            ScheduleBlock::create(['resource_type' => $data['resource_type'], 'practitioner_id' => $data['resource_type'] === 'practitioner' ? $id : null, 'operatory_id' => $data['resource_type'] === 'operatory' ? $id : null, 'starts_at' => $data['starts_at'], 'ends_at' => $data['ends_at'], 'reason' => $data['reason']]);
            $this->audit($request, 'availability.block', $data['resource_type'], $id);
        });

        return back()->with('success', 'Schedule block saved.');
    }

    public function patient(Request $request)
    {
        abort_unless($request->user()->hasRole('owner', 'receptionist'), 403);
        abort_unless((app()->environment(['local', 'testing']) && config('smilesuite.sandbox')) || config('smilesuite.real_patient_data'), 403);
        $data = $request->validate(['first_name' => 'required|string|max:100', 'last_name' => 'required|string|max:100', 'email' => 'nullable|email|max:254', 'phone' => 'nullable|string|max:40', 'date_of_birth' => 'nullable|date|before_or_equal:'.now()->subYears(18)->format('Y-m-d')]);
        DB::transaction(function () use ($request, $data) {
            $patient = PatientProfile::create($data);
            $this->audit($request, 'patient.create', 'patient_profile', $patient->id);
        });

        return back()->with('success', 'Walk-in profile created. No account has been linked.');
    }

    public function invite(Request $request)
    {
        $this->owner($request);
        $data = $request->validate(['email' => 'required|email|max:254', 'roles' => 'required|array|min:1', 'roles.*' => 'in:practitioner,receptionist']);
        $token = Str::random(64);
        DB::transaction(function () use ($request, $data, $token) {
            StaffInvitation::create(['email' => mb_strtolower($data['email']), 'roles' => $data['roles'], 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(2)]);
            $this->audit($request, 'staff.invite', 'staff_invitation');
        });
        $url = url('/invitation/'.$token);
        Mail::raw("You have been invited to a clinic workspace. Accept within 48 hours: {$url}", fn ($mail) => $mail->to($data['email'])->subject('Your clinic workspace invitation'));

        return back()->with('success', 'Staff invitation sent.');
    }
}
