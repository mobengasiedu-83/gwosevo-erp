<?php

namespace App\Http\Middleware;

use App\Domain\Administration\Modules;
use App\Domain\Administration\Settings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OperationalState
{
    public function handle(Request $request, Closure $next)
    {
        $settings = app(Settings::class);
        if ($settings->get('maintenance_enabled') && ! $request->routeIs('login', 'logout', 'branding.logo', 'mfa.*', 'security.*', 'microsoft.*', 'password.*', 'invitation.*') && ! ($request->user()?->isActive() && Gate::allows('administer-system'))) {
            return response()->view('maintenance', ['message' => $settings->get('maintenance_message')], 503)->header('Retry-After', '300');
        }
        if ($request->routeIs('leads.*') && ! app(Modules::class)->enabled('crm')) {
            abort(404, 'CRM module is disabled.');
        }

        return $next($request);
    }
}
