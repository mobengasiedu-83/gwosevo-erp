<?php

namespace App\Providers;

use App\Listeners\LogEmailActivity;
use App\Domain\Administration\Modules;
use App\Domain\Administration\Settings;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\CommercialQuote;
use App\Models\User;
use App\Policies\LeadPolicy;
use App\Policies\OpportunityPolicy;
use App\Policies\CommercialQuotePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Event;
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
        Event::listen(\Illuminate\Mail\Events\MessageSending::class, [LogEmailActivity::class, 'sending']);
        Event::listen(\Illuminate\Mail\Events\MessageSent::class, [LogEmailActivity::class, 'sent']);
        Event::listen(\Illuminate\Notifications\Events\NotificationFailed::class, [LogEmailActivity::class, 'failed']);
        RateLimiter::for('crm-lead-capture', fn ($request) => Limit::perMinute(20)->by($request->ip()));
        RateLimiter::for('identity-security', fn ($request) => Limit::perMinute(30)->by((string) ($request->user()?->id ?? $request->ip())));
        RateLimiter::for('identity-mfa', fn ($request) => Limit::perMinute(6)->by(($request->user()?->id ?? $request->session()->get('identity.pending.user_id', 'guest')).'|'.$request->ip()));
        RateLimiter::for('pbx-events', fn ($request) => Limit::perMinute(120)->by((string) ($request->route('connection') ?? $request->ip())));
        Gate::before(fn (User $user) => $user->hasAnyRole(['Super Admin', 'Superadmin', 'Administrator']) ? $user->isActive() : null);
        Gate::define('administer-system', fn (User $user) => $user->isSuperAdmin());
        View::composer('*', function ($view) {
            $view->with('erpSettings', app(Settings::class));
            $view->with('erpModules', app(Modules::class));
        });
        Gate::policy(Lead::class, LeadPolicy::class);
        Gate::policy(Opportunity::class, OpportunityPolicy::class);
        Gate::policy(CommercialQuote::class, CommercialQuotePolicy::class);
    }
}
