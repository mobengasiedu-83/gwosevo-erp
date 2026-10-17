<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdministrationAccess
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->isActive() && Gate::allows('administer-system'), 403);

        return $next($request);
    }
}
