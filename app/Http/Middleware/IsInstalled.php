<?php

namespace App\Http\Middleware;

use Closure;

class IsInstalled
{
    public function handle($request, Closure $next)
    {
        if (! isAppInstalled() && ! $request->is('install*')) {
            return redirect('/install');
        }

        return $next($request);
    }
}
