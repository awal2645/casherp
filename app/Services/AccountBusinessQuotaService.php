<?php

namespace App\Services;

use App\Business;
use App\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Superadmin\Entities\Subscription;

class AccountBusinessQuotaService
{
    /**
     * Return the active plan that covers an owner's companies.
     *
     * Legacy subscriptions without max_businesses are intentionally treated as
     * one-company plans. That prevents an old package from accidentally
     * receiving unlimited companies during this upgrade.
     */
    public function activePlanFor(User $owner, bool $lockForUpdate = false): ?Subscription
    {
        $businessIds = $owner->ownedBusinesses()->pluck('id');

        if ($businessIds->isEmpty()) {
            return null;
        }

        $today = Carbon::today()->toDateString();

        $query = Subscription::whereIn('business_id', $businessIds)
            ->approved()
            ->whereNull('covered_by_subscription_id')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->orderByDesc('end_date');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    public function usageFor(User $owner): array
    {
        $subscription = $this->activePlanFor($owner);

        $maximum = empty($subscription)
            ? 0
            : (int) ($subscription->package_details['max_businesses'] ?? 1);
        $current = Business::where('owner_id', $owner->id)->count();
        $unlimited = ! empty($subscription) && $maximum === 0;

        return [
            'subscription' => $subscription,
            'current' => $current,
            'maximum' => $maximum,
            'unlimited' => $unlimited,
            'remaining' => $unlimited ? null : max(0, $maximum - $current),
            'can_create' => ! empty($subscription) && ($unlimited || $current < $maximum),
        ];
    }

    public function ensureCanCreate(User $owner, bool $lockForUpdate = false): Subscription
    {
        $subscription = $this->activePlanFor($owner, $lockForUpdate);

        if (empty($subscription)) {
            throw ValidationException::withMessages([
                'company' => 'An active package is required before another company can be added.',
            ]);
        }

        $maximum = (int) ($subscription->package_details['max_businesses'] ?? 1);
        $currentCount = Business::where('owner_id', $owner->id)
            ->when($lockForUpdate, fn ($query) => $query->lockForUpdate())
            ->count();

        if ($maximum !== 0 && $currentCount >= $maximum) {
            throw ValidationException::withMessages([
                'company' => "Your package allows {$maximum} company or companies. Upgrade the package to add another company.",
            ]);
        }

        return $subscription;
    }
}
