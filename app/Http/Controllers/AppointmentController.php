<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Operatory;
use App\Models\PatientProfile;
use App\Models\Practitioner;
use App\Models\Service;
use App\Scheduling\AvailabilityService;
use App\Scheduling\BookingService;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function edit(Request $request, Appointment $appointment)
    {
        $isStaff = $request->user()->hasRole('owner', 'receptionist', 'practitioner');
        abort_unless($isStaff || $appointment->patient->user_id === $request->user()->id, 403);
        if ($request->user()->hasRole('practitioner') && ! $request->user()->hasRole('owner', 'receptionist')) {
            abort_unless($appointment->practitioner->user_id === $request->user()->id, 403);
        }
        abort_unless(in_array($appointment->status, ['scheduled', 'confirmed'], true), 422);

        return view('appointments.create', ['editing' => $appointment, 'patients' => collect([$appointment->patient]), 'services' => collect([$appointment->service]), 'practitioners' => Practitioner::with('user')->where('active', true)->get(), 'operatories' => Operatory::where('active', true)->get(), 'isStaff' => $isStaff]);
    }

    public function create(Request $request)
    {
        $isStaff = $request->user()->hasRole('owner', 'receptionist', 'practitioner');
        $patients = $isStaff ? PatientProfile::orderBy('last_name')->get() : PatientProfile::where('user_id', $request->user()->id)->get();
        $services = Service::where('active', true)->get();
        $practitioners = Practitioner::with('user')->where('active', true)->get();
        $operatories = Operatory::where('active', true)->get();

        return view('appointments.create', compact('patients', 'services', 'practitioners', 'operatories', 'isStaff'));
    }

    public function slots(Request $request, AvailabilityService $availability)
    {
        $data = $request->validate(['service_id' => 'required|integer', 'date' => 'required|date_format:Y-m-d', 'practitioner_id' => 'nullable|integer', 'operatory_id' => 'nullable|integer']);

        return response()->json(['slots' => $availability->slots(Service::findOrFail($data['service_id']), $data['date'], $data['practitioner_id'] ?? null, $data['operatory_id'] ?? null)]);
    }

    public function store(Request $request, BookingService $booking)
    {
        abort_unless((app()->environment(['local', 'testing']) && config('smilesuite.sandbox')) || config('smilesuite.real_patient_data'), 403);
        $data = $request->validate(['patient_id' => 'required|integer', 'service_id' => 'required|integer', 'practitioner_id' => 'required|integer', 'operatory_id' => 'required|integer', 'start_at' => 'required|string', 'idempotency_key' => 'required|string|min:16|max:120', 'override_reason' => 'nullable|string|max:500']);
        $appointment = $booking->book($request->user(), $data, $data['idempotency_key']);

        return redirect($request->user()->hasRole('owner', 'receptionist', 'practitioner') ? '/schedule?date='.$appointment->start_at->setTimezone(app(TenantContext::class)->tenant->timezone)->format('Y-m-d') : '/portal')->with('success', 'Appointment booked. Your reservation is confirmed.');
    }

    public function transition(Request $request, Appointment $appointment, BookingService $booking)
    {
        $data = $request->validate(['status' => 'required|string', 'version' => 'required|integer|min:1', 'reason' => 'nullable|string|max:500']);
        $booking->transition($request->user(), $appointment, $data['status'], $data['version'], $data['reason'] ?? null);

        return back()->with('success', 'Appointment updated.');
    }

    public function reschedule(Request $request, Appointment $appointment, BookingService $booking)
    {
        $data = $request->validate(['version' => 'required|integer|min:1', 'reason' => 'required|string|max:500', 'start_at' => 'required|string', 'practitioner_id' => 'required|integer', 'operatory_id' => 'required|integer']);
        $booking->reschedule($request->user(), $appointment, $data, $data['version'], $data['reason']);

        return back()->with('success', 'Appointment rescheduled.');
    }
}
