<?php

namespace App\Http\Middleware;

use App\Services\BusinessContextService;
use Closure;
use Illuminate\Support\Facades\Auth;

class SetSessionData
{
    private BusinessContextService $businessContext;

    public function __construct(BusinessContextService $businessContext)
    {
        $this->businessContext = $businessContext;
    }

    /**
     * Checks if session data is set or not for a user. If data is not set then set it.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $user = Auth::user();
        $sessionBusinessId = (int) $request->session()->get('user.business_id');
        $business = null;

        if ($sessionBusinessId > 0) {
            $business = $user->accessibleBusinesses()
                ->whereKey($sessionBusinessId)
                ->where('is_active', true)
                ->with('currency')
                ->first();
        }

        // Recover safely if this session has no company, the company was
        // deactivated, or the user's access was removed after login.
        if (empty($business)) {
            $business = $this->businessContext->recoverActiveBusiness($user);
            abort_if(empty($business), 403, 'No active company is available for this account.');
            $this->businessContext->activate($request, $user, $business);
        } else {
            // Keep request-level legacy code aligned with this session without
            // rewriting the last-active pointer on every request.
            $user->setAttribute('business_id', $business->id);
            $user->setRelation('business', $business);

            if (! $request->session()->has('user')
                || (int) $request->session()->get('business.id') !== (int) $business->id
                || ! $request->session()->has('currency')) {
                $this->businessContext->activate($request, $user, $business);
            }
        }

        return $next($request);
    }
}
