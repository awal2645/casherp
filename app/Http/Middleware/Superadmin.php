<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class Superadmin
{
    public function handle($request, Closure $next)
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
