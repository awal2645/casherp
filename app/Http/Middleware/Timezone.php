<?php

namespace App\Http\Middleware;

use Closure;

class Timezone
{
    public function handle($request, Closure $next)
    {
        $timezone = $request->session()->get('business.time_zone', config('app.timezone'));
        if (! empty($timezone)) {
            config(['app.timezone' => $timezone]);
            date_default_timezone_set($timezone);
        }

        return $next($request);
    }
}
