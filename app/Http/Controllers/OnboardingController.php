<?php

namespace App\Http\Controllers;

use App\Models\BillingEntitlement;
use App\Models\PatientProfile;
use App\Models\StaffInvitation;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRole;
use App\Tenancy\TenantRunner;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class OnboardingController extends Controller
{
    public function landing(Request $request)
    {
        if ($request->attributes->has('tenant')) {
            return redirect('/login');
        }
        abort_if($request->getHost() === config('tenancy.admin_host'), 404);

        return view('welcome');
    }

    public function clinicForm()
    {
        return view('auth.register', ['type' => 'clinic']);
    }

    public function clinic(Request $request, TenantRunner $runner)
    {
        abort_if($request->attributes->has('tenant') || $request->getHost() === config('tenancy.admin_host'), 404);
        $request->merge(['subdomain' => strtolower(trim((string) $request->input('subdomain'))), 'email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate(['name' => 'required|string|max:100', 'clinic_name' => 'required|string|max:100', 'subdomain' => 'required|string|max:63|regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/|not_in:www,admin,api,support,mail,status,app,billing|unique:tenants,subdomain', 'timezone' => 'required|timezone:all', 'email' => 'required|email|max:254', 'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()], 'synthetic' => 'accepted']);
        $sandbox = app()->environment('local') && config('smilesuite.sandbox');
        try {
            $tenant = DB::transaction(function () use ($data, $runner, $sandbox) {
                $tenant = Tenant::create(['name' => $data['clinic_name'], 'subdomain' => $data['subdomain'], 'timezone' => $data['timezone'], 'currency' => 'PHP', 'lifecycle_status' => $sandbox ? 'active' : 'pending', 'billing_status' => $sandbox ? 'sandbox' : 'pending']);
                $runner->run($tenant, function () use ($data, $sandbox) {
                    $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
                    UserRole::create(['user_id' => $user->id, 'role' => 'owner']);
                    BillingEntitlement::create(['tier' => $sandbox ? 'starter' : 'none', 'bookings_enabled' => $sandbox, 'chair_limit' => $sandbox ? 1 : 0, 'practitioner_limit' => $sandbox ? 2 : 0, 'over_limit' => false, 'reconciled_at' => now()]);
                });

                return $tenant;
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['subdomain' => 'That clinic address has just been reserved. Choose another.']);
        }
        $scheme = $request->isSecure() ? 'https' : 'http';
        $port = in_array($request->getPort(), [80, 443]) ? '' : ':'.$request->getPort();

        return redirect()->away($scheme.'://'.$tenant->subdomain.'.'.config('tenancy.domain').$port.'/login');
    }

    public function patientForm()
    {
        return view('auth.register', ['type' => 'patient']);
    }

    public function patient(Request $request)
    {
        abort_unless((app()->environment(['local', 'testing']) && config('smilesuite.sandbox')) || config('smilesuite.real_patient_data'), 403);
        $data = $request->validate(['first_name' => 'required|string|max:100', 'last_name' => 'required|string|max:100', 'date_of_birth' => 'required|date|before_or_equal:'.now()->subYears(18)->format('Y-m-d'), 'email' => 'required|email|max:254', 'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()], 'synthetic' => 'accepted']);
        $email = mb_strtolower(trim($data['email']));
        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'This clinic already has an account with that email.']);
        }
        $user = DB::transaction(function () use ($data, $email) {
            $user = User::create(['name' => $data['first_name'].' '.$data['last_name'], 'email' => $email, 'password' => $data['password']]);
            UserRole::create(['user_id' => $user->id, 'role' => 'patient']);
            PatientProfile::create(['user_id' => $user->id, 'first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'date_of_birth' => $data['date_of_birth'], 'email' => $email]);

            return $user;
        });
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('auth_stamp', hash('sha256', (string) $user->remember_token));
        $this->sendVerification($user);

        return redirect('/portal')->with('success', 'Account created. Check your email to verify before booking.');
    }

    public function verification(Request $request)
    {
        if (! $request->user()->email_verified_at) {
            $this->sendVerification($request->user());
        }

        return back()->with('success', 'Verification email sent.');
    }

    private function sendVerification(User $user): void
    {
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['user' => $user->id, 'hash' => sha1($user->email)]);
        Mail::raw("Verify your clinic account email using this link within one hour: {$url}", fn ($mail) => $mail->to($user->email)->subject('Verify your clinic account'));
    }

    public function invitation(string $token)
    {
        $invitation = StaffInvitation::where('token_hash', hash('sha256', $token))->whereNull('accepted_at')->where('expires_at', '>', now())->firstOrFail();

        return view('auth.invitation', compact('token', 'invitation'));
    }

    public function accept(Request $request, string $token)
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()]]);
        $user = DB::transaction(function () use ($data, $token) {
            $invitation = StaffInvitation::where('token_hash', hash('sha256', $token))->whereNull('accepted_at')->where('expires_at', '>', now())->lockForUpdate()->firstOrFail();
            if (User::where('email', $invitation->email)->exists()) {
                throw ValidationException::withMessages(['name' => 'An account already exists. Ask the clinic owner to review your roles.']);
            }
            $user = User::create(['name' => $data['name'], 'email' => $invitation->email, 'password' => $data['password'], 'email_verified_at' => now()]);
            foreach ($invitation->roles as $role) {
                abort_unless(in_array($role, ['practitioner', 'receptionist'], true), 403);
                UserRole::create(['user_id' => $user->id, 'role' => $role]);
            }
            $invitation->update(['accepted_at' => now()]);

            return $user;
        });
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('auth_stamp', hash('sha256', (string) $user->remember_token));

        return redirect('/security');
    }
}
