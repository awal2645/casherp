<?php

namespace App\Http\Middleware;

use Closure;

class EcomApi
{
    public function handle($request, Closure $next)
    {
        return $next($request);
    }
}
