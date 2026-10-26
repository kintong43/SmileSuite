<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\PatientProfile;
use App\Models\Practitioner;
use App\Models\Service;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    private function operational(Request $request)
    {
        abort_unless($request->user()->hasRole('owner', 'receptionist', 'practitioner'), 403);
        $query = Appointment::query();
        if (! $request->user()->hasRole('owner', 'receptionist')) {
            $query->whereIn('practitioner_id', Practitioner::where('user_id', $request->user()->id)->select('id'));
        }

        return $query;
    }

    public function index(Request $request)
    {
        $tenant = app(TenantContext::class)->tenant;
        $date = CarbonImmutable::now($tenant->timezone);
        $start = $date->startOfDay()->utc();
        $end = $date->endOfDay()->utc();
        $appointments = $this->operational($request)->with(['patient', 'practitioner.user', 'service', 'operatory'])->whereBetween('start_at', [$start, $end])->orderBy('start_at')->get();
        $week = $this->operational($request)->whereBetween('start_at', [$date->startOfWeek()->utc(), $date->endOfWeek()->utc()])->get();
        $metrics = ['appointments' => $appointments->where('status', '!=', 'cancelled')->count(), 'patients' => PatientProfile::count(), 'completed' => $appointments->where('status', 'completed')->count(), 'scheduled_value' => $appointments->where('status', '!=', 'cancelled')->sum('price_minor')];

        return view('dashboard', compact('appointments', 'metrics', 'date', 'week'));
    }

    public function schedule(Request $request)
    {
        $request->validate(['date' => 'nullable|date_format:Y-m-d']);
        $tenant = app(TenantContext::class)->tenant;
        $date = CarbonImmutable::parse($request->input('date', now($tenant->timezone)->format('Y-m-d')), $tenant->timezone);
        $appointments = $this->operational($request)->with(['patient', 'practitioner.user', 'service', 'operatory'])->whereBetween('start_at', [$date->startOfDay()->utc(), $date->endOfDay()->utc()])->orderBy('start_at')->get();
        $practitioners = Practitioner::with('user')->where('active', true)->get();

        return view('schedule', compact('appointments', 'date', 'practitioners'));
    }

    public function patients(Request $request)
    {
        $this->operational($request);
        $request->validate(['q' => 'nullable|string|max:100']);
        $query = PatientProfile::query();
        if (! $request->user()->hasRole('owner', 'receptionist')) {
            $query->whereIn('id', Appointment::whereIn('practitioner_id', Practitioner::where('user_id', $request->user()->id)->select('id'))->select('patient_id'));
        }
        if ($search = $request->input('q')) {
            $query->where(fn ($query) => $query->where('first_name', 'ilike', '%'.$search.'%')->orWhere('last_name', 'ilike', '%'.$search.'%'));
        }
        $patients = $query->orderBy('last_name')->paginate(15)->withQueryString();

        return view('patients', compact('patients'));
    }

    public function services(Request $request)
    {
        $this->operational($request);
        $services = Service::orderBy('category')->orderBy('name')->get();

        return view('services', compact('services'));
    }

    public function portal(Request $request)
    {
        $patient = PatientProfile::where('user_id', $request->user()->id)->first();
        $appointments = $patient ? Appointment::with(['service', 'practitioner.user', 'operatory'])->where('patient_id', $patient->id)->orderByDesc('start_at')->get() : collect();

        return view('portal', compact('patient', 'appointments'));
    }
}
