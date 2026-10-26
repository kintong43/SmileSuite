<?php

namespace App\Http\Controllers;

use App\Clinical\ClinicalAccess;
use App\Models\Appointment;
use App\Models\ClinicalNote;
use App\Models\ConsentRecord;
use App\Models\IntakeSubmission;
use App\Models\PatientProfile;
use App\Models\Practitioner;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClinicalController extends Controller
{
    public function export(Request $request, PatientProfile $patient, ClinicalAccess $access)
    {
        $this->guardEnvironment();
        abort_unless($patient->user_id === $request->user()->id && $request->user()->email_verified_at, 403);
        $versions = DB::transaction(function () use ($request, $patient, $access) {
            $access->audit($request->user(), 'intake.export', $patient);

            return IntakeSubmission::where('patient_id', $patient->id)->orderBy('id')->get()->map(fn ($intake) => ['version_id' => $intake->id, 'form_version' => $intake->form_version, 'submitted_at' => $intake->submitted_at->toIso8601String(), 'supersedes_id' => $intake->supersedes_id, 'answers' => $intake->answers])->all();
        });

        return response()->json(['clinic' => $request->attributes->get('tenant')->name, 'intake_versions' => $versions])->header('Content-Disposition', 'attachment; filename="my-intake.json"')->header('Cache-Control', 'no-store, private');
    }

    private function guardEnvironment(): void
    {
        abort_unless((app()->environment(['local', 'testing']) && config('smilesuite.sandbox')) || config('smilesuite.real_patient_data'), 403, 'Patient records are awaiting operational approval.');
    }

    public function show(Request $request, PatientProfile $patient, ClinicalAccess $access)
    {
        $this->guardEnvironment();
        abort_unless($access->canRead($request->user(), $patient), 403);
        $access->audit($request->user(), 'clinical.read', $patient);
        $intakeQuery = IntakeSubmission::where('patient_id', $patient->id);
        $ids = $access->permittedAppointmentIds($request->user(), $patient);
        if ($patient->user_id !== $request->user()->id) {
            $latestGeneralId = IntakeSubmission::where('patient_id', $patient->id)->whereNull('appointment_id')->max('id');
            $intakeQuery->where(fn ($q) => $q->whereIn('appointment_id', $ids)->orWhere('id', $latestGeneralId));
        }
        $intakes = $intakeQuery->orderByDesc('submitted_at')->get();
        $notes = $access->canWriteNote($request->user(), $patient) ? ClinicalNote::where('patient_id', $patient->id)->whereIn('appointment_id', $ids)->orderByDesc('created_at')->get() : collect();
        $appointments = Appointment::where('patient_id', $patient->id)->when($patient->user_id !== $request->user()->id, fn ($q) => $q->whereIn('id', $ids))->orderByDesc('start_at')->get();

        return view('clinical', compact('patient', 'intakes', 'notes', 'appointments'));
    }

    public function intake(Request $request, PatientProfile $patient, ClinicalAccess $access)
    {
        $this->guardEnvironment();
        abort_unless($patient->user_id === $request->user()->id && $request->user()->email_verified_at, 403);
        $data = $request->validate(['appointment_id' => 'nullable|integer', 'allergies' => 'nullable|string|max:3000', 'medications' => 'nullable|string|max:3000', 'medical_history' => 'nullable|string|max:5000', 'concerns' => 'nullable|string|max:3000', 'consent' => 'accepted']);
        if (! empty($data['appointment_id'])) {
            Appointment::whereKey($data['appointment_id'])->where('patient_id', $patient->id)->firstOrFail();
        }
        DB::transaction(function () use ($request, $patient, $data, $access) {
            PatientProfile::whereKey($patient->id)->lockForUpdate()->firstOrFail();
            $previous = IntakeSubmission::where('patient_id', $patient->id)->latest('id')->first();
            IntakeSubmission::create(['patient_id' => $patient->id, 'appointment_id' => $data['appointment_id'] ?? null, 'form_version' => '1', 'answers' => collect($data)->only(['allergies', 'medications', 'medical_history', 'concerns'])->all(), 'submitted_at' => now(), 'author_id' => $request->user()->id, 'supersedes_id' => $previous?->id]);
            ConsentRecord::create(['patient_id' => $patient->id, 'user_id' => $request->user()->id, 'purpose' => 'care_intake', 'notice_version' => 'synthetic-v1', 'granted_at' => now(), 'capture_method' => 'authenticated_form']);
            $access->audit($request->user(), 'intake.submit', $patient);
        });

        return back()->with('success', 'Your intake has been saved as a new version.');
    }

    public function note(Request $request, PatientProfile $patient, ClinicalAccess $access)
    {
        $this->guardEnvironment();
        abort_unless($access->canWriteNote($request->user(), $patient), 403);
        $data = $request->validate(['appointment_id' => 'required|integer', 'content' => 'required|string|max:20000', 'supersedes_id' => 'nullable|integer']);
        $practitioner = Practitioner::where('user_id', $request->user()->id)->where('active', true)->firstOrFail();
        Appointment::whereKey($data['appointment_id'])->where('patient_id', $patient->id)->where('practitioner_id', $practitioner->id)->firstOrFail();
        DB::transaction(function () use ($request, $patient, $data, $practitioner, $access) {
            Tenant::whereKey($patient->tenant_id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array((int) $data['appointment_id'], $access->permittedAppointmentIds($request->user(), $patient), true), 403);
            PatientProfile::whereKey($patient->id)->lockForUpdate()->firstOrFail();
            $previous = empty($data['supersedes_id']) ? null : ClinicalNote::whereKey($data['supersedes_id'])->where('patient_id', $patient->id)->where('appointment_id', $data['appointment_id'])->where('practitioner_id', $practitioner->id)->firstOrFail();
            if (! $previous && ClinicalNote::where('appointment_id', $data['appointment_id'])->exists()) {
                throw ValidationException::withMessages(['supersedes_id' => 'This encounter already has a signed note. Select the latest note to amend it.']);
            }
            if ($previous && ClinicalNote::where('supersedes_id', $previous->id)->exists()) {
                throw ValidationException::withMessages(['supersedes_id' => 'This note already has an amendment. Amend its latest version.']);
            }
            ClinicalNote::create(['patient_id' => $patient->id, 'appointment_id' => $data['appointment_id'], 'practitioner_id' => $practitioner->id, 'content' => $data['content'], 'note_version' => ($previous?->note_version ?? 0) + 1, 'signed_at' => now(), 'supersedes_id' => $previous?->id]);
            $access->audit($request->user(), 'clinical.note.sign', $patient);
        });

        return back()->with('success', 'Encounter note signed and saved.');
    }
}
