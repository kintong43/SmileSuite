<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

class AuthController extends Controller
{
    public function loginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt(['email' => mb_strtolower(trim($data['email'])), 'password' => $data['password'], 'status' => 'active'])) {
            throw ValidationException::withMessages(['email' => 'The email or password is incorrect for this clinic.']);
        }
        $request->session()->regenerate();
        $request->session()->forget(['mfa_confirmed', 'mfa_user_id', 'mfa_enrollment_secret', 'mfa_enrollment']);
        $request->session()->put('tenant_id', app(TenantContext::class)->id());
        $request->session()->put('auth_stamp', hash('sha256', (string) $request->user()->remember_token));
        if ($request->user()->hasRole('owner', 'practitioner', 'receptionist')) {
            return redirect('/security');
        }

        return redirect('/portal');
    }

    public function demo(Request $request, string $role)
    {
        abort_unless(app()->environment('local') && config('smilesuite.sandbox'), 404);
        abort_unless(in_array($role, ['owner', 'practitioner', 'receptionist', 'patient'], true), 404);
        $user = User::get()->first(fn ($user) => $user->hasRole($role));
        abort_unless($user, 404);
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget(['mfa_enrollment_secret', 'mfa_enrollment']);
        $request->session()->put(['tenant_id' => app(TenantContext::class)->id(), 'mfa_confirmed' => true, 'mfa_user_id' => $user->id]);
        $request->session()->put('auth_stamp', hash('sha256', (string) $user->remember_token));

        return redirect($role === 'patient' ? '/portal' : '/dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    public function security(Request $request, TwoFactorAuthenticationProvider $provider)
    {
        abort_unless($request->user()->hasRole('owner', 'practitioner', 'receptionist'), 403);
        $enrolling = ! $request->user()->two_factor_confirmed_at;
        $enrollment = $request->session()->get('mfa_enrollment');
        if ($enrolling && (! $enrollment || (int) $enrollment['user_id'] !== $request->user()->id || $enrollment['expires_at'] < now()->timestamp)) {
            $enrollment = ['user_id' => $request->user()->id, 'secret' => $provider->generateSecretKey(), 'expires_at' => now()->addMinutes(10)->timestamp];
            $request->session()->put('mfa_enrollment', $enrollment);
        }
        $secret = $enrolling ? $enrollment['secret'] : null;
        $qr = $secret ? $provider->qrCodeUrl('SmileSuite · '.app(TenantContext::class)->tenant->name, $request->user()->email, $secret) : null;

        return view('auth.security', compact('enrolling', 'secret', 'qr'));
    }

    public function verifyMfa(Request $request, TwoFactorAuthenticationProvider $provider)
    {
        $data = $request->validate(['code' => 'required|string|size:6']);
        $user = $request->user();
        abort_unless($user->hasRole('owner', 'practitioner', 'receptionist'), 403);
        $enrollment = $request->session()->get('mfa_enrollment');
        $validEnrollment = $enrollment && (int) $enrollment['user_id'] === $user->id && $enrollment['expires_at'] >= now()->timestamp;
        $secret = $user->two_factor_confirmed_at && $user->two_factor_secret ? decrypt($user->two_factor_secret) : ($validEnrollment ? $enrollment['secret'] : null);
        if (! $secret || ! $provider->verify($secret, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'Enter a current code from your authenticator.']);
        }
        if (! $user->two_factor_confirmed_at) {
            $user->forceFill(['two_factor_secret' => encrypt($secret), 'two_factor_confirmed_at' => now()])->save();
        }
        $request->session()->forget(['mfa_enrollment_secret', 'mfa_enrollment']);
        $request->session()->regenerate();
        $request->session()->put('mfa_confirmed', true);
        $request->session()->put('mfa_user_id', $user->id);

        return redirect('/dashboard');
    }

    public function forgotForm()
    {
        return view('auth.password', ['mode' => 'request']);
    }

    public function forgot(Request $request)
    {
        $data = $request->validate(['email' => 'required|email']);
        $user = User::where('email', mb_strtolower(trim($data['email'])))->where('status', 'active')->first();
        if ($user) {
            $tenantId = app(TenantContext::class)->id();
            $existing = DB::table('password_reset_tokens')->where('tenant_id', $tenantId)->where('email', $user->email)->first();
            if (! $existing || now()->diffInSeconds($existing->created_at, true) >= 60) {
                $token = Str::random(64);
                DB::table('password_reset_tokens')->updateOrInsert(['tenant_id' => $tenantId, 'email' => $user->email], ['token' => Hash::make($token), 'created_at' => now()]);
                $url = url('/reset-password/'.$token).'?email='.urlencode($user->email);
                Mail::raw("A password reset was requested for your clinic account. Open this link within 60 minutes: {$url}", fn ($mail) => $mail->to($user->email)->subject('Reset your clinic account password'));
            }
        }

        return back()->with('success', 'If that account exists here, a reset link has been sent.');
    }

    public function resetForm(string $token)
    {
        return view('auth.password', ['mode' => 'reset', 'token' => $token]);
    }

    public function reset(Request $request, string $token)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()]]);
        DB::transaction(function () use ($data, $token) {
            $query = DB::table('password_reset_tokens')->where('tenant_id', app(TenantContext::class)->id())->where('email', mb_strtolower(trim($data['email'])));
            $reset = (clone $query)->lockForUpdate()->first();
            if (! $reset || now()->diffInMinutes($reset->created_at, true) > 60 || ! Hash::check($token, $reset->token)) {
                throw ValidationException::withMessages(['email' => 'This reset link is invalid or expired.']);
            }
            $user = User::where('email', $reset->email)->firstOrFail();
            $user->forceFill(['password' => Hash::make($data['password']), 'remember_token' => Str::random(60)])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $query->delete();
        });

        return redirect('/login')->with('success', 'Password updated. Sign in again.');
    }

    public function verifyEmail(Request $request, User $user)
    {
        abort_unless($request->user()->is($user) && hash_equals((string) $request->query('hash'), sha1($user->email)), 403);
        $user->forceFill(['email_verified_at' => now()])->save();

        return redirect('/portal')->with('success', 'Email verified.');
    }
}
