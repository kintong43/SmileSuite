<?php

namespace App\Tenancy;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        // Clear any leaked worker state even for platform and invalid hosts.
        app()->forgetInstance(TenantContext::class);
        $host = strtolower($request->getHost());
        $domain = strtolower(config('tenancy.domain', 'smilesuite.localhost'));
        $platformHosts = config('tenancy.platform_hosts', [$domain, 'localhost', '127.0.0.1', 'admin.'.$domain]);
        if (in_array($host, $platformHosts, true)) {
            return $next($request);
        }
        if (! str_ends_with($host, '.'.$domain)) {
            abort(404);
        }
        $subdomain = substr($host, 0, -strlen('.'.$domain));
        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/D', $subdomain)) {
            abort(404);
        }
        $tenant = Tenant::where('subdomain', $subdomain)->firstOrFail();
        $request->attributes->set('tenant', $tenant);

        return app(TenantRunner::class)->run($tenant, fn () => $next($request));
    }
}
