<?php

namespace App\Http\Middleware;

use App\Services\FeatureAccessService;
use Closure;

class EnsureBusinessFeature
{
    public function handle($request, Closure $next, string $feature)
    {
        if (! app(FeatureAccessService::class)->enabled($feature)) {
            abort(403, 'This feature is not enabled for the selected business industry.');
        }

        return $next($request);
    }
}
