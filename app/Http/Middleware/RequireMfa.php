<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireMfa
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && $user->hasRole('owner', 'practitioner', 'receptionist') && (! $request->session()->get('mfa_confirmed') || (int) $request->session()->get('mfa_user_id') !== $user->id)) {
            return redirect('/security');
        }

        return $next($request);
    }
}
