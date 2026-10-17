<?php

namespace App\Providers;

use App\Domain\Administration\Modules;
use App\Domain\Administration\Settings;
use App\Models\Lead;
use App\Models\User;
use App\Policies\LeadPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(Settings::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('identity-security', fn ($request) => Limit::perMinute(30)->by((string) ($request->user()?->id ?? $request->ip())));
        RateLimiter::for('identity-mfa', fn ($request) => Limit::perMinute(6)->by(($request->user()?->id ?? $request->session()->get('identity.pending.user_id', 'guest')).'|'.$request->ip()));
        Gate::define('administer-system', fn (User $user) => $user->isActive() && $user->hasRole('Administrator'));
        View::composer('*', function ($view) {
            $view->with('erpSettings', app(Settings::class));
            $view->with('erpModules', app(Modules::class));
        });
        Gate::policy(Lead::class, LeadPolicy::class);
    }
}
