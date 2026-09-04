<?php

namespace Modules\Essentials\Http\Controllers\Concerns;

trait AuthorizesHrmRequests
{
    /**
     * Require the HRM package entitlement. Super administrators retain their
     * platform-level override, but a package entitlement never grants a user
     * an operational HR permission.
     */
    protected function authorizeHrmFeature(int $businessId): void
    {
        $user = auth()->user();

        abort_if(empty($user), 403, 'Unauthorized action.');

        if ($user->can('superadmin')) {
            return;
        }

        abort_unless(
            $this->moduleUtil->hasThePermissionInSubscription($businessId, 'essentials_module'),
            403,
            'Unauthorized action.'
        );
    }

    /**
     * Require both the HRM package entitlement and an explicit operational
     * ability. Business administrators may be allowed deliberately for
     * administrative workflows; this is independent from package access.
     *
     * @param  string|array<int, string>  $abilities
     */
    protected function authorizeHrmAction(int $businessId, $abilities, bool $allowBusinessAdmin = true): void
    {
        $this->authorizeHrmFeature($businessId);

        $user = auth()->user();
        if ($user->can('superadmin')) {
            return;
        }

        if ($allowBusinessAdmin && $this->moduleUtil->is_admin($user, $businessId)) {
            return;
        }

        foreach ((array) $abilities as $ability) {
            if ($user->canForBusiness($ability, $businessId)) {
                return;
            }
        }

        abort(403, 'Unauthorized action.');
    }
}
