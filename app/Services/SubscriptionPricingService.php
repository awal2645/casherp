<?php

namespace App\Services;

use App\Business;
use App\BusinessLocation;
use App\Product;
use App\Transaction;
use App\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Superadmin\Entities\Package;
use Modules\Superadmin\Entities\Subscription;
use Modules\Superadmin\Entities\SuperadminCoupon;

/**
 * Server-authoritative policy for SaaS package visibility, prices and limits.
 *
 * Browser fields and payment-provider metadata are identifiers only. Package
 * access, discounts, currency and the final amount must always be recomputed
 * here before a subscription is created.
 */
class SubscriptionPricingService
{
    public function availablePackages(int $businessId, bool $isSuperadmin = false): Collection
    {
        $ownerId = Business::whereKey($businessId)->value('owner_id');
        $accountBusinessIds = $ownerId
            ? Business::where('owner_id', $ownerId)->pluck('id')->map(fn ($id) => (int) $id)->all()
            : [$businessId];

        return Package::active()
            ->with(['premiumModules' => fn ($query) => $query->where('premium_module_plans.is_active', true)])
            ->orderBy('sort_order')
            ->get()
            ->filter(function (Package $package) use ($accountBusinessIds, $isSuperadmin) {
                if ($isSuperadmin) {
                    return true;
                }
                if ($package->is_private) {
                    return false;
                }

                $targetBusinesses = array_map('intval', (array) $package->businesses);
                return empty($targetBusinesses) || ! empty(array_intersect($accountBusinessIds, $targetBusinesses));
            })
            ->values();
    }

    public function packageForBusiness(int $packageId, int $businessId, bool $isSuperadmin = false): Package
    {
        $package = Package::active()->findOrFail($packageId);

        if (! $isSuperadmin) {
            if ($package->is_private) {
                throw ValidationException::withMessages([
                    'package_id' => __('superadmin::lang.not_allowed_for_package'),
                ]);
            }

            $businesses = array_map('intval', (array) $package->businesses);
            $ownerId = Business::whereKey($businessId)->value('owner_id');
            $accountBusinessIds = $ownerId
                ? Business::where('owner_id', $ownerId)->pluck('id')->map(fn ($id) => (int) $id)->all()
                : [$businessId];
            if (! empty($businesses) && empty(array_intersect($accountBusinessIds, $businesses))) {
                throw ValidationException::withMessages([
                    'package_id' => __('superadmin::lang.not_allowed_for_package'),
                ]);
            }
        }

        return $package;
    }

    /**
     * @return array{base_amount: float, discount_amount: float, amount: float, coupon_code: ?string, coupon: ?SuperadminCoupon}
     */
    public function quote(Package $package, int $businessId, ?string $couponCode = null, bool $strict = false): array
    {
        $baseAmount = round(max(0, (float) $package->price), 4);
        $couponCode = trim((string) $couponCode);
        $coupon = null;
        $discount = 0.0;

        if ($couponCode !== '') {
            $coupon = SuperadminCoupon::whereRaw('LOWER(coupon_code) = ?', [mb_strtolower($couponCode)])->first();
            $valid = $coupon && $this->couponApplies($coupon, $package->id, $businessId);

            if (! $valid) {
                if ($strict) {
                    throw ValidationException::withMessages(['coupon_code' => __('superadmin::lang.invalid_coupon')]);
                }
                $coupon = null;
            }
        }

        if ($coupon) {
            if ($coupon->discount_type === 'percentage') {
                $discount = $baseAmount * min(100, max(0, (float) $coupon->discount)) / 100;
            } else {
                $discount = min($baseAmount, max(0, (float) $coupon->discount));
            }
        }

        return [
            'base_amount' => $baseAmount,
            'discount_amount' => round($discount, 4),
            'amount' => round(max(0, $baseAmount - $discount), 4),
            'coupon_code' => $coupon ? $coupon->coupon_code : null,
            'coupon' => $coupon,
        ];
    }

    public function assertExpectedAmount($paidAmount, float $expectedAmount, string $field = 'amount'): void
    {
        if (! is_numeric($paidAmount) || abs((float) $paidAmount - $expectedAmount) > 0.01) {
            throw ValidationException::withMessages([$field => 'The verified payment amount does not match the package price.']);
        }
    }

    public function assertExpectedCurrency(?string $paidCurrency, string $expectedCurrency): void
    {
        if (strtoupper(trim((string) $paidCurrency)) !== strtoupper($expectedCurrency)) {
            throw ValidationException::withMessages(['currency' => 'The verified payment currency does not match the system currency.']);
        }
    }

    public function assertCanSubscribe(Package $package, int $businessId): void
    {
        $this->assertResourceCompatibility($package, $businessId);

        $business = Business::findOrFail($businessId);
        $ownerId = (int) $business->owner_id;
        $companyIds = Business::where('owner_id', $ownerId)->pluck('id');

        if ($package->is_one_time) {
            $alreadyUsed = Subscription::whereIn('business_id', $companyIds)
                ->where('package_id', $package->id)
                ->exists();
            if ($alreadyUsed) {
                throw ValidationException::withMessages([
                    'package_id' => __('superadmin::lang.maximum_subscription_limit_exceed'),
                ]);
            }
        }

        // Zero-cost plans must not be repeatedly activated to extend access.
        if ((float) $package->price === 0.0) {
            $freePlanExists = Subscription::whereIn('business_id', $companyIds)
                ->where('package_id', $package->id)
                ->whereIn('status', ['approved', 'waiting'])
                ->exists();
            if ($freePlanExists) {
                throw ValidationException::withMessages([
                    'package_id' => __('superadmin::lang.maximum_subscription_limit_exceed'),
                ]);
            }
        }

        $pendingExists = Subscription::whereIn('business_id', $companyIds)
            ->where('package_id', $package->id)
            ->waiting()
            ->exists();
        if ($pendingExists) {
            throw ValidationException::withMessages([
                'package_id' => 'This package already has a payment awaiting approval.',
            ]);
        }
    }

    public function assertResourceCompatibility(Package $package, int $businessId, bool $includeCurrentInvoiceUsage = false): void
    {
        $business = Business::findOrFail($businessId);
        $companyIds = Business::where('owner_id', $business->owner_id)->pluck('id');

        if ((int) $package->max_businesses > 0 && $companyIds->count() > (int) $package->max_businesses) {
            throw ValidationException::withMessages([
                'package_id' => "This plan allows {$package->max_businesses} companies, but this account currently owns {$companyIds->count()}.",
            ]);
        }

        foreach ($companyIds as $companyId) {
            $limits = [
                'users' => [(int) $package->user_count, User::forBusiness($companyId)->where('allow_login', 1)->count()],
                'locations' => [(int) $package->location_count, BusinessLocation::where('business_id', $companyId)->count()],
                'products' => [(int) $package->product_count, Product::where('business_id', $companyId)->count()],
            ];

            foreach ($limits as $resource => [$limit, $used]) {
                if ($limit > 0 && $used > $limit) {
                    throw ValidationException::withMessages([
                        'package_id' => "This plan allows {$limit} {$resource} per company, but company #{$companyId} currently uses {$used}.",
                    ]);
                }
            }

            if ($includeCurrentInvoiceUsage && (int) $package->invoice_count > 0) {
                $activeSubscription = Subscription::active_subscription((int) $companyId);
                $invoiceCount = $activeSubscription
                    ? Transaction::where('business_id', $companyId)
                        ->where('type', 'sell')
                        ->where('status', 'final')
                        ->whereBetween('created_at', [
                            $activeSubscription->start_date->startOfDay(),
                            $activeSubscription->end_date->endOfDay(),
                        ])->count()
                    : 0;
                if ($invoiceCount > (int) $package->invoice_count) {
                    throw ValidationException::withMessages([
                        'package_id' => "This plan allows {$package->invoice_count} invoices per plan period, but company #{$companyId} currently uses {$invoiceCount}.",
                    ]);
                }
            }
        }
    }

    public function assertZeroCostCheckoutAvailable(Package $package, int $businessId, float $amount): void
    {
        if ($amount > 0) {
            return;
        }

        $ownerId = (int) Business::whereKey($businessId)->value('owner_id');
        $companyIds = Business::where('owner_id', $ownerId)->pluck('id');
        if (Subscription::whereIn('business_id', $companyIds)
            ->where('package_id', $package->id)
            ->whereIn('status', ['approved', 'waiting'])
            ->exists()) {
            throw ValidationException::withMessages([
                'package_id' => __('superadmin::lang.maximum_subscription_limit_exceed'),
            ]);
        }
    }

    private function couponApplies(SuperadminCoupon $coupon, int $packageId, int $businessId): bool
    {
        if (! $coupon->is_active) {
            return false;
        }

        if ($coupon->expiry_date && Carbon::parse($coupon->expiry_date)->endOfDay()->isPast()) {
            return false;
        }

        $packages = array_map('intval', (array) $coupon->applied_on_packages);
        $businesses = array_map('intval', (array) $coupon->applied_on_business);

        return (empty($packages) || in_array($packageId, $packages, true))
            && (empty($businesses) || in_array($businessId, $businesses, true));
    }
}
