<?php

namespace Modules\Hms\Services;

use App\Utils\ModuleUtil;
use Illuminate\Validation\ValidationException;
use Modules\Hms\Entities\HmsProperty;

class HmsSaasService
{
    public function allows(int $businessId, string $capability): bool
    {
        $moduleUtil = app(ModuleUtil::class);

        if (! $moduleUtil->isSuperadminInstalled()) {
            return true;
        }

        if (auth()->check() && auth()->user()->can('superadmin')) {
            return true;
        }

        $subscription = \Modules\Superadmin\Entities\Subscription::active_subscription($businessId);
        if (! $subscription) {
            return false;
        }

        $details = (array) ($subscription->package_details ?? []);

        // Capability flags can only refine an active HMS entitlement. Public
        // booking and webhook requests do not pass through the authenticated
        // HMS route middleware, so they must not revive HMS for a package that
        // does not include the base module.
        if (! (bool) ($details['hms_module'] ?? false)) {
            return false;
        }

        // Capabilities introduced after HMS 2.5 default to enabled for an
        // existing HMS subscription. Super Admin can explicitly disable them
        // when a package is next saved or renewed.
        return ! array_key_exists($capability, $details) || (bool) $details[$capability];
    }

    public function propertyLimit(int $businessId): int
    {
        $moduleUtil = app(ModuleUtil::class);
        if (! $moduleUtil->isSuperadminInstalled()) {
            return 0;
        }

        $subscription = \Modules\Superadmin\Entities\Subscription::active_subscription($businessId);
        if (! $subscription) {
            return 0;
        }

        $details = (array) ($subscription->package_details ?? []);
        if (array_key_exists('hms_max_properties', $details) && $details['hms_max_properties'] !== '') {
            return max(0, (int) $details['hms_max_properties']);
        }

        // One hotel property maps to one CashERP branch/location. Reusing the
        // branch allowance keeps older subscription snapshots meaningful.
        return isset($details['location_count']) ? max(0, (int) $details['location_count']) : 1;
    }

    public function assertCapability(int $businessId, string $capability): void
    {
        if (! $this->allows($businessId, $capability)) {
            abort(403, __('hms::lang.package_capability_unavailable'));
        }
    }

    public function assertPropertyCapacity(int $businessId): void
    {
        $limit = $this->propertyLimit($businessId);
        $used = HmsProperty::where('business_id', $businessId)->count();

        if ($limit > 0 && $used >= $limit) {
            throw ValidationException::withMessages([
                'property' => __('hms::lang.property_limit_reached', ['limit' => $limit]),
            ]);
        }
    }
}
