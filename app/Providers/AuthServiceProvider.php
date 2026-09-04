<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        'App\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Gate::before(function ($user, $ability) {
            if (in_array($ability, ['backup', 'superadmin',
                'manage_modules', ])) {
                $administrator_list = config('constants.administrator_usernames');

                if (in_array(strtolower($user->username), explode(',', strtolower($administrator_list)))) {
                    return true;
                }
            } else {
                $businessId = (int) $user->business_id;
                if (app()->bound('request') && request()->hasSession()) {
                    $sessionBusinessId = (int) request()->session()->get('user.business_id');
                    if ($sessionBusinessId > 0) {
                        $businessId = $sessionBusinessId;
                    }
                }

                if ($businessId > 0
                    && $user->canAccessBusiness($businessId)
                    && $user->hasRole('Admin#'.$businessId)) {
                    return true;
                }
            }
        });
    }
}
