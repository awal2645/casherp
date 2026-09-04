<?php

namespace Modules\Hms\Http\Controllers\Concerns;

use App\Utils\ModuleUtil;
use Modules\Hms\Services\HmsSaasService;

trait AuthorizesHmsRequests
{
    protected function hmsBusinessId(): int
    {
        return (int) request()->session()->get('user.business_id');
    }

    protected function authorizeHms(string $permission, ?string $capability = null): int
    {
        $businessId = $this->hmsBusinessId();
        $moduleUtil = app(ModuleUtil::class);

        if (! (auth()->user()->can('superadmin') || $moduleUtil->hasThePermissionInSubscription($businessId, 'hms_module'))) {
            abort(403, 'Unauthorized action.');
        }
        if (! (auth()->user()->can('superadmin') || auth()->user()->can($permission))) {
            abort(403, 'Unauthorized action.');
        }
        if ($capability) {
            app(HmsSaasService::class)->assertCapability($businessId, $capability);
        }

        return $businessId;
    }
}
