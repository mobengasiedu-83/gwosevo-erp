<?php

namespace App\Http\Middleware;

use App\Domain\Identity\IdentityAudit;
use Closure;
use Illuminate\Http\Request;

class IdentityAccessAudit
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        if ($request->isMethod('GET') && $response->getStatusCode() < 400) {
            IdentityAudit::change('access.viewed', $request->user()->id, null, ['route' => $request->route()->getName()]);
        }

        return $response->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }
}
