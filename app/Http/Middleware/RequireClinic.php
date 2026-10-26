<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RequireClinic
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(app()->bound(TenantContext::class), 404);
        $context = app(TenantContext::class);
        abort_unless($context->tenant->exists, 404);
        if ($request->user()) {
            abort_unless($request->user()->tenant_id === $context->id() && $request->user()->status === 'active', 403);
            if (! hash_equals(hash('sha256', (string) $request->user()->remember_token), (string) $request->session()->get('auth_stamp'))) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect('/login');
            }
        }

        return $next($request);
    }
}
