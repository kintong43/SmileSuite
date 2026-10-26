<?php

namespace App\Http\Controllers;

use App\Clinical\ClinicalAccess;
use App\Models\Appointment;
use App\Models\AuditEvent;
use App\Models\CareAssignment;
use App\Models\PatientProfile;
use App\Models\Practitioner;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CareAssignmentController extends Controller
{
    private function actor(Request $request): array
    {
        abort_unless((app()->environment(['local', 'testing']) && config('smilesuite.sandbox')) || config('smilesuite.real_patient_data'), 403, 'Patient records are awaiting operational approval.');
        $user = User::whereKey($request->user()->id)->where('status', 'active')->first();
        abort_unless($user && $user->hasRole('practitioner'), 403);
        $practitioner = Practitioner::where('user_id', $user->id)->where('active', true)->first();
        abort_unless($practitioner, 403);

        return [$user, $practitioner];
    }

    public function index(Request $request, PatientProfile $patient, ClinicalAccess $access)
    {
        [$actor, $practitioner] = $this->actor($request);
        $assignments = CareAssignment::where('patient_id', $patient->id)
            ->where(fn ($query) => $query->where('granted_by', $actor->id)->orWhere('practitioner_id', $practitioner->id))
            ->orderByDesc('id')->get();
        $hasCareAccess = $access->canWriteNote($actor, $patient);
        abort_unless($hasCareAccess || $assignments->contains('granted_by', $actor->id), 403);
        $appointments = $hasCareAccess ? Appointment::where('patient_id', $patient->id)
            ->whereIn('id', $this->delegableIds($actor, $practitioner, $patient, $access))->orderByDesc('start_at')->get() : collect();
        $practitioners = Practitioner::with('user')->where('active', true)->whereKeyNot($practitioner->id)
            ->whereHas('user', fn ($query) => $query->where('status', 'active')->whereHas('roles', fn ($roles) => $roles->where('role', 'practitioner')))->get();
        $recipientNames = Practitioner::with('user')->whereIn('id', $assignments->pluck('practitioner_id'))->get()->mapWithKeys(fn ($recipient) => [$recipient->id => $recipient->user->name]);
        $issuerNames = User::whereIn('id', $assignments->pluck('granted_by'))->pluck('name', 'id');
        $this->audit($actor, 'care_assignment.list', null, ['patient_id' => $patient->id]);

        return response()->view('care-assignments', compact('patient', 'assignments', 'appointments', 'practitioners', 'recipientNames', 'issuerNames', 'actor'))
            ->header('Cache-Control', 'no-store, private');
    }

    public function grant(Request $request, PatientProfile $patient, ClinicalAccess $access)
    {
        $this->actor($request);
        $data = $request->validate([
            'appointment_id' => 'required|integer|min:1', 'practitioner_id' => 'required|integer|min:1',
            'valid_to' => ['required', 'date', 'regex:/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2}(?:\.\d{1,6})?)?(?:Z|[+-]\d{2}:\d{2})\z/'],
            'purpose' => 'required|in:follow_up,covering_care,consultation',
        ]);
        $expires = CarbonImmutable::parse($data['valid_to'])->utc();
        if ($expires <= now() || $expires > now()->addDays(90)) {
            throw ValidationException::withMessages(['valid_to' => 'Choose an expiry in the future and within 90 days.']);
        }
        DB::transaction(function () use ($request, $patient, $access, $data, $expires): void {
            Tenant::whereKey($patient->tenant_id)->lockForUpdate()->firstOrFail();
            [$actor, $practitioner] = $this->actor($request);
            Appointment::whereKey($data['appointment_id'])->where('patient_id', $patient->id)->lockForUpdate()->firstOrFail();
            abort_unless($access->canWriteNote($actor, $patient)
                && in_array((int) $data['appointment_id'], $this->delegableIds($actor, $practitioner, $patient, $access), true), 403);
            $recipient = Practitioner::whereKey($data['practitioner_id'])->where('active', true)->firstOrFail();
            abort_unless($recipient->user->status === 'active' && $recipient->user->hasRole('practitioner'), 422);
            if ($recipient->id === $practitioner->id) {
                throw ValidationException::withMessages(['practitioner_id' => 'Select another active practitioner.']);
            }
            if (CareAssignment::where('patient_id', $patient->id)->where('appointment_id', $data['appointment_id'])
                ->where('practitioner_id', $recipient->id)->whereNull('revoked_at')->where('valid_from', '<=', now())
                ->where(fn ($query) => $query->whereNull('valid_to')->orWhere('valid_to', '>', now()))->exists()) {
                throw ValidationException::withMessages(['practitioner_id' => 'This practitioner already has an active assignment for this encounter.']);
            }
            $assignment = CareAssignment::create([
                'patient_id' => $patient->id, 'appointment_id' => $data['appointment_id'], 'practitioner_id' => $recipient->id,
                'granted_by' => $actor->id, 'valid_from' => now(), 'valid_to' => $expires, 'purpose' => $data['purpose'],
            ]);
            $this->audit($actor, 'care_assignment.grant', $assignment->id, ['patient_id' => $patient->id, 'appointment_id' => (int) $data['appointment_id'], 'practitioner_id' => $recipient->id, 'purpose' => $data['purpose'], 'valid_to' => $expires->toIso8601String()]);
        }, 3);

        return redirect('/patients/'.$patient->id.'/care-assignments')->with('success', 'Encounter access delegated until the selected expiry.');
    }

    public function revoke(Request $request, PatientProfile $patient, CareAssignment $assignment)
    {
        $this->actor($request);
        DB::transaction(function () use ($request, $patient, $assignment): void {
            Tenant::whereKey($patient->tenant_id)->lockForUpdate()->firstOrFail();
            [$actor] = $this->actor($request);
            $record = CareAssignment::whereKey($assignment->id)->where('patient_id', $patient->id)->lockForUpdate()->firstOrFail();
            abort_unless($record->granted_by === $actor->id, 403);
            if ($record->revoked_at !== null) {
                return;
            }
            $record->update(['revoked_at' => now()]);
            $this->audit($actor, 'care_assignment.revoke', $record->id, ['patient_id' => $patient->id, 'appointment_id' => $record->appointment_id, 'practitioner_id' => $record->practitioner_id]);
        }, 3);

        return redirect('/patients/'.$patient->id.'/care-assignments')->with('success', 'Delegated access revoked.');
    }

    private function audit(User $actor, string $action, ?int $id, array $metadata): void
    {
        AuditEvent::create(['actor_id' => $actor->id, 'action' => $action, 'resource_type' => 'care_assignment', 'resource_id' => $id, 'metadata' => $metadata, 'occurred_at' => now()]);
    }

    private function delegableIds(User $actor, Practitioner $practitioner, PatientProfile $patient, ClinicalAccess $access): array
    {
        $permitted = $access->permittedAppointmentIds($actor, $patient);
        $broadAssignment = CareAssignment::where('patient_id', $patient->id)->where('practitioner_id', $practitioner->id)
            ->whereNull('appointment_id')->whereNull('revoked_at')->where('valid_from', '<=', now())
            ->where(fn ($query) => $query->whereNull('valid_to')->orWhere('valid_to', '>', now()))->exists();
        if ($broadAssignment) {
            return $permitted;
        }

        return Appointment::where('patient_id', $patient->id)->whereIn('id', $permitted)->where('practitioner_id', $practitioner->id)
            ->whereIn('status', ['scheduled', 'confirmed', 'checked_in', 'in_chair'])->pluck('id')->all();
    }
}
