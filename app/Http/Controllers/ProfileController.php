<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\PatientProfile;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $actor = $request->user();
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $emailChanging = $actor->email !== $request->input('email');
        $hasProfile = PatientProfile::where('user_id', $actor->id)->exists();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:254', Rule::unique('users', 'email')->where('tenant_id', TenantContext::current()->id())->ignore($actor->id)],
            'current_password' => $emailChanging ? ['required', 'current_password:web'] : ['nullable', 'string'],
            'first_name' => $hasProfile ? ['required', 'string', 'max:100'] : ['nullable', 'string', 'max:100'],
            'last_name' => $hasProfile ? ['required', 'string', 'max:100'] : ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:40'],
            'roles' => ['prohibited'], 'tenant_id' => ['prohibited'], 'user_id' => ['prohibited'], 'patient_id' => ['prohibited'], 'status' => ['prohibited'],
        ]);
        DB::transaction(function () use ($actor, $data, $emailChanging): void {
            $user = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $user->name = $data['name'];
            if ($emailChanging) {
                $user->email = $data['email'];
                $user->email_verified_at = null;
                $user->remember_token = Str::random(60);
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
            $user->save();
            $profile = PatientProfile::where('user_id', $user->id)->lockForUpdate()->first();
            if ($profile) {
                $profile->fill(['first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'phone' => $data['phone'] ?? null, 'email' => $user->email])->save();
            }
            AuditEvent::create(['actor_id' => $user->id, 'action' => 'profile.updated', 'resource_type' => 'user', 'resource_id' => $user->id, 'metadata' => ['email_changed' => $emailChanging], 'occurred_at' => now()]);
        });
        $actor->refresh();
        $request->session()->regenerate();
        $request->session()->put('auth_stamp', hash('sha256', (string) $actor->remember_token));
        if ($emailChanging) {
            $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['user' => $actor->id, 'hash' => sha1($actor->email)]);
            try {
                Mail::raw("Verify your updated clinic account email within one hour: {$url}", fn ($message) => $message->to($actor->email)->subject('Verify your updated clinic account'));
            } catch (\Throwable $exception) {
                return redirect('/profile')->with('success', 'Profile saved. Verification delivery is unavailable; use the portal to request a fresh link later.');
            }
        }

        return redirect('/profile')->with('success', $emailChanging ? 'Profile saved. Verify your new email before booking.' : 'Your profile has been updated.');
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate(['current_password' => ['required', 'current_password:web'], 'password' => ['required', 'confirmed', 'different:current_password', Password::min(12)->letters()->numbers()]]);
        $actor = $request->user();
        DB::transaction(function () use ($actor, $data): void {
            $user = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $user->forceFill(['password' => Hash::make($data['password']), 'remember_token' => Str::random(60)])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            AuditEvent::create(['actor_id' => $user->id, 'action' => 'profile.password_changed', 'resource_type' => 'user', 'resource_id' => $user->id, 'metadata' => [], 'occurred_at' => now()]);
        });
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')->with('success','Password updated. Sign in again. Other sessions have been revoked.');
    }
}
