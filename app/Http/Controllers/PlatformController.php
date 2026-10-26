<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

class PlatformController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware(function (Request $request, Closure $next) {
            if (! in_array($request->route()->getActionMethod(), ['loginForm', 'login'], true)) {
                $admin = Auth::guard('platform')->user();
                if (! $admin || $admin->status !== 'active') {
                    Auth::guard('platform')->logout();
                    $request->session()->forget(['platform_mfa_admin_id', 'platform_mfa_enrollment']);

                    return redirect()->route('platform.login');
                }
                if (! in_array($request->route()->getActionMethod(), ['security', 'verifyMfa', 'logout'], true)
                    && (! $admin->two_factor_confirmed_at || ! $admin->two_factor_secret
                        || (string) $request->session()->get('platform_mfa_admin_id') !== (string) $admin->id)) {
                    return redirect()->route('platform.security');
                }
            }

            $response = $next($request);
            $response->headers->set('Cache-Control', 'no-store, private');

            return $response;
        })];
    }

    public function loginForm()
    {
        return view('platform.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::guard('platform')->attempt([
            'email' => mb_strtolower(trim($data['email'])), 'password' => $data['password'], 'status' => 'active',
        ])) {
            throw ValidationException::withMessages(['email' => 'The platform administrator credentials are incorrect.']);
        }
        $request->session()->regenerate();
        $request->session()->forget(['platform_mfa_admin_id', 'platform_mfa_enrollment']);

        return redirect()->route('platform.security');
    }

    public function logout(Request $request)
    {
        Auth::guard('platform')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('platform.login');
    }

    public function security(Request $request, TwoFactorAuthenticationProvider $provider)
    {
        $admin = Auth::guard('platform')->user();
        $enrolling = ! $admin->two_factor_confirmed_at;
        $secret = null;
        if ($enrolling) {
            $enrollment = $request->session()->get('platform_mfa_enrollment');
            if (! $enrollment || (string) $enrollment['admin_id'] !== (string) $admin->id || $enrollment['expires_at'] < now()->timestamp) {
                $enrollment = ['admin_id' => $admin->id, 'secret' => $provider->generateSecretKey(), 'expires_at' => now()->addMinutes(10)->timestamp];
                $request->session()->put('platform_mfa_enrollment', $enrollment);
            }
            $secret = $enrollment['secret'];
        }

        return view('platform.security', compact('enrolling', 'secret'));
    }

    public function verifyMfa(Request $request, TwoFactorAuthenticationProvider $provider)
    {
        $data = $request->validate(['code' => ['required', 'string', 'regex:/^[0-9]{6}$/D']]);
        $admin = Auth::guard('platform')->user();
        $secret = null;
        if ($admin->two_factor_confirmed_at && $admin->two_factor_secret) {
            $secret = decrypt($admin->two_factor_secret);
        } elseif (! $admin->two_factor_confirmed_at) {
            $enrollment = $request->session()->get('platform_mfa_enrollment');
            if ($enrollment && (string) $enrollment['admin_id'] === (string) $admin->id && $enrollment['expires_at'] >= now()->timestamp) {
                $secret = $enrollment['secret'];
            }
        }
        if (! $secret || ! $provider->verify($secret, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'Enter a current code from your platform authenticator.']);
        }
        if (! $admin->two_factor_confirmed_at) {
            $admin->forceFill(['two_factor_secret' => encrypt($secret), 'two_factor_confirmed_at' => now()])->save();
        }
        $request->session()->regenerate();
        $request->session()->forget('platform_mfa_enrollment');
        $request->session()->put('platform_mfa_admin_id', $admin->id);

        return redirect()->route('platform.index');
    }

    public function index()
    {
        $tenants = Tenant::query()->select(['id', 'name', 'subdomain', 'lifecycle_status', 'billing_status'])->orderBy('name')->paginate(25);

        return view('platform.index', compact('tenants'));
    }

    public function lifecycle(Request $request, int $tenant)
    {
        $data = $request->validate(['lifecycle_status' => ['required', Rule::in(['pending', 'active', 'suspended', 'closed'])]]);
        DB::transaction(function () use ($tenant, $data): void {
            $clinic = Tenant::query()->select(['id', 'lifecycle_status'])->lockForUpdate()->findOrFail($tenant);
            if ($clinic->lifecycle_status === $data['lifecycle_status']) {
                return;
            }
            DB::table('platform_audit_events')->insert([
                'platform_admin_id' => Auth::guard('platform')->id(), 'tenant_id' => $clinic->id,
                'action' => 'tenant.lifecycle_changed', 'previous_status' => $clinic->lifecycle_status,
                'new_status' => $data['lifecycle_status'], 'occurred_at' => now(),
            ]);
            // Only operational metadata is writable from this platform surface.
            DB::table('tenants')->where('id', $clinic->id)->update(['lifecycle_status' => $data['lifecycle_status'], 'updated_at' => now()]);
        });

        return redirect()->route('platform.index')->with('success', 'Clinic lifecycle saved. Changes are recorded in the platform audit.');
    }
}
