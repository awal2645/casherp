<?php

namespace App\Http\Middleware;

use Closure;

class Language
{
    public function handle($request, Closure $next)
    {
        if ($request->session()->has('user.language')) {
            app()->setLocale($request->session()->get('user.language'));
        }

        return $next($request);
    }
}
