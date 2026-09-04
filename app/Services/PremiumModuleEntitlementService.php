<?php

namespace App\Services;

use App\BusinessModuleEntitlement;
use App\BusinessModuleOrder;
use App\BusinessModuleUsage;
use App\PremiumModulePlan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Superadmin\Entities\Subscription;

class PremiumModuleEntitlementService
{
    public function catalog(int $businessId)
    {
        return PremiumModulePlan::with('feature')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (PremiumModulePlan $plan) use ($businessId) {
                return $this->status($businessId, $plan);
            });
    }

    public function status(int $businessId, PremiumModulePlan $plan): array
    {
        $subscription = Subscription::active_subscription($businessId);
        $packageAllocation = null;
        if ($subscription) {
            $packageAllocation = DB::table('package_premium_modules')
                ->where('package_id', $subscription->package_id)
                ->where('premium_module_plan_id', $plan->id)
                ->first();
        }

        $entitlements = BusinessModuleEntitlement::where('business_id', $businessId)
            ->where('premium_module_plan_id', $plan->id)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->get();

        $isEntitled = $packageAllocation !== null || $entitlements->isNotEmpty();
        $allowances = [];
        if ($packageAllocation !== null) {
            $allowances[] = $packageAllocation->included_allowance_override === null
                ? (int) $plan->included_allowance
                : (int) $packageAllocation->included_allowance_override;
        }
        foreach ($entitlements as $entitlement) {
            $allowances[] = (int) $entitlement->base_allowance
                + (int) $entitlement->extra_allowance;
        }

        // Zero is deliberately unlimited only after entitlement. Without an
        // entitlement the same zero must never accidentally unlock a module.
        $unlimited = $isEntitled && in_array(0, $allowances, true);
        $allowance = $unlimited ? 0 : array_sum($allowances);
        [$periodStart, $periodEnd] = $this->period($plan, $subscription);
        $used = (int) BusinessModuleUsage::where('business_id', $businessId)
            ->where('premium_module_plan_id', $plan->id)
            ->where('period_start', $periodStart->toDateString())
            ->value('used_quantity');

        return [
            'plan' => $plan,
            'entitled' => $isEntitled,
            'included_with_package' => $packageAllocation !== null,
            'allowance' => $allowance,
            'unlimited' => $unlimited,
            'used' => $used,
            'remaining' => $unlimited ? null : max(0, $allowance - $used),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'pending_order' => BusinessModuleOrder::where('business_id', $businessId)
                ->where('premium_module_plan_id', $plan->id)
                ->where('status', 'pending')
                ->exists(),
        ];
    }

    public function consume(int $businessId, string $planCode, int $quantity = 1): void
    {
        if ($quantity < 1) {
            return;
        }

        $plan = PremiumModulePlan::where('code', $planCode)->where('is_active', true)->first();
        if (! $plan) {
            return; // Backwards compatible until Super Admin activates pricing.
        }

        DB::transaction(function () use ($businessId, $plan, $quantity) {
            $status = $this->status($businessId, $plan);
            if (! $status['entitled']) {
                throw ValidationException::withMessages([
                    'subscription' => $plan->name.' requires an active module entitlement.',
                ]);
            }
            BusinessModuleUsage::firstOrCreate(
                [
                    'business_id' => $businessId,
                    'premium_module_plan_id' => $plan->id,
                    'period_start' => $status['period_start']->toDateString(),
                ],
                [
                    'period_end' => optional($status['period_end'])->toDateString(),
                    'used_quantity' => 0,
                ]
            );
            $usage = BusinessModuleUsage::where('business_id', $businessId)
                ->where('premium_module_plan_id', $plan->id)
                ->where('period_start', $status['period_start']->toDateString())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $status['unlimited']
                && (int) $usage->used_quantity + $quantity > $status['allowance']) {
                throw ValidationException::withMessages([
                    'subscription' => 'The '.$plan->allowance_name.' allowance has been reached. Buy more capacity or upgrade the package.',
                ]);
            }

            $usage->increment('used_quantity', $quantity);
        });
    }

    public function assertCapacity(
        int $businessId,
        string $planCode,
        int $currentQuantity,
        int $additionalQuantity = 1
    ): void {
        $plan = PremiumModulePlan::where('code', $planCode)->where('is_active', true)->first();
        if (! $plan) {
            return;
        }

        $status = $this->status($businessId, $plan);
        if (! $status['entitled']) {
            throw ValidationException::withMessages([
                'subscription' => $plan->name.' requires an active module entitlement.',
            ]);
        }
        if (! $status['unlimited']
            && $currentQuantity + $additionalQuantity > $status['allowance']) {
            throw ValidationException::withMessages([
                'subscription' => 'The '.$plan->allowance_name.' capacity is full. Buy another capacity block or upgrade the package.',
            ]);
        }
    }

    public function approveOrder(BusinessModuleOrder $order, int $reviewerId): void
    {
        DB::transaction(function () use ($order, $reviewerId) {
            $locked = BusinessModuleOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['order' => 'This module order has already been reviewed.']);
            }

            $plan = $locked->plan;
            $months = $plan->billing_interval === 'year'
                ? 12 * (int) $plan->billing_interval_count
                : (int) $plan->billing_interval_count;
            $endsAt = $plan->billing_interval === 'one_time'
                ? null
                : now()->copy()->addMonths(max(1, $months));

            BusinessModuleEntitlement::create([
                'business_id' => $locked->business_id,
                'premium_module_plan_id' => $plan->id,
                'source' => $locked->order_type === 'capacity' ? 'capacity_purchase' : 'module_purchase',
                'base_allowance' => $locked->order_type === 'module' ? $plan->included_allowance : 0,
                'extra_allowance' => $locked->order_type === 'capacity' ? $locked->allowance_quantity : 0,
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => $endsAt,
                'amount_paid' => $locked->total_price,
                'payment_reference' => $locked->payment_reference,
                'approved_by' => $reviewerId,
            ]);

            DB::table('business_features')->updateOrInsert(
                [
                    'business_id' => $locked->business_id,
                    'feature_id' => $plan->feature_id,
                ],
                [
                    'is_enabled' => true,
                    'source' => 'premium_entitlement',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $locked->update([
                'status' => 'approved',
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
            ]);
        });
    }

    private function period(PremiumModulePlan $plan, ?Subscription $subscription): array
    {
        if ($plan->allowance_reset === 'monthly') {
            return [now()->startOfMonth(), now()->endOfMonth()];
        }
        if ($plan->allowance_reset === 'subscription' && $subscription) {
            return [Carbon::parse($subscription->start_date), Carbon::parse($subscription->end_date)];
        }

        return [Carbon::create(2000, 1, 1)->startOfDay(), null];
    }
}
