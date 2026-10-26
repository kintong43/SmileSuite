<?php

namespace App\Providers;

use App\Billing\StripeSubscriptionProvider;
use App\Billing\SubscriptionProvider;
use App\Billing\TenantSubscription;
use App\Billing\TenantSubscriptionItem;
use App\Models\Tenant;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;
use Laravel\Cashier\Cashier;
use Laravel\Fortify\Fortify;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Cashier::ignoreRoutes();
        Fortify::ignoreRoutes();
        $this->app->bind(SubscriptionProvider::class, StripeSubscriptionProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\View::composer('*', function (View $view) {
            $view->with('clinic', request()->attributes->get('tenant'));
        });
        Cashier::useCustomerModel(Tenant::class);
        Cashier::useSubscriptionModel(TenantSubscription::class);
        Cashier::useSubscriptionItemModel(TenantSubscriptionItem::class);
        URL::forceScheme($this->app->environment('production') ? 'https' : 'http');
        RateLimiter::for('login', fn (Request $request) => [Limit::perMinute(5)->by('account|'.$request->getHost().'|'.mb_strtolower(trim((string) $request->input('email'))).'|'.$request->ip()), Limit::perMinute(30)->by('ip|'.$request->getHost().'|'.$request->ip())]);
        RateLimiter::for('mfa', fn (Request $request) => Limit::perMinute(5)->by($request->getHost().'|'.($request->user()?->id ?? Auth::guard('platform')->id()).'|'.$request->ip()));
        RateLimiter::for('booking', fn (Request $request) => Limit::perMinute(30)->by($request->getHost().'|'.$request->user()?->id.'|'.$request->ip()));
    }
}
