<?php

namespace App\Http\Controllers\Superadmin;

use App\BusinessModuleOrder;
use App\Feature;
use App\Http\Controllers\Controller;
use App\Notifications\PremiumModuleOrderNotification;
use App\PremiumModulePlan;
use App\Services\PremiumModuleEntitlementService;
use App\System;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Modules\Superadmin\Entities\Package;

class PremiumModulePlanController extends Controller
{
    public function index()
    {
        $this->authorizeSuperadmin();

        return view('superadmin::premium_modules.index', [
            'plans' => PremiumModulePlan::with(['feature', 'packages'])->orderBy('sort_order')->get(),
            'features' => Feature::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'packages' => Package::orderBy('sort_order')->get(),
            'orders' => BusinessModuleOrder::with(['plan', 'business', 'requester'])
                ->latest()
                ->take(100)
                ->get(),
            'currency' => System::getCurrency(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeSuperadmin();
        PremiumModulePlan::create($this->validatedPlan($request));

        return back()->with('status', ['success' => 1, 'msg' => 'Premium module plan created.']);
    }

    public function update(Request $request, PremiumModulePlan $premiumModulePlan)
    {
        $this->authorizeSuperadmin();
        $premiumModulePlan->update($this->validatedPlan($request, $premiumModulePlan->id));

        return back()->with('status', ['success' => 1, 'msg' => 'Premium module plan updated.']);
    }

    public function updatePackages(Request $request, PremiumModulePlan $premiumModulePlan)
    {
        $this->authorizeSuperadmin();
        $data = $request->validate([
            'packages' => ['nullable', 'array'],
            'packages.*.included' => ['nullable', 'boolean'],
            'packages.*.allowance' => ['nullable', 'integer', 'min:0'],
        ]);
        $sync = [];
        foreach ((array) ($data['packages'] ?? []) as $packageId => $settings) {
            if (! empty($settings['included']) && Package::whereKey($packageId)->exists()) {
                $sync[(int) $packageId] = [
                    'included_allowance_override' => array_key_exists('allowance', $settings)
                        ? $settings['allowance']
                        : null,
                ];
            }
        }
        $premiumModulePlan->packages()->sync($sync);

        return back()->with('status', ['success' => 1, 'msg' => 'Package module allowances updated.']);
    }

    public function approve(
        BusinessModuleOrder $order,
        PremiumModuleEntitlementService $entitlements
    ) {
        $this->authorizeSuperadmin();
        $entitlements->approveOrder($order, auth()->id());
        $this->notifyOrderStakeholders($order->refresh(), 'approved');

        return back()->with('status', ['success' => 1, 'msg' => 'Module order approved and entitlement activated.']);
    }

    public function decline(Request $request, BusinessModuleOrder $order)
    {
        $this->authorizeSuperadmin();
        $data = $request->validate(['review_note' => ['required', 'string', 'max:2000']]);
        abort_unless($order->status === 'pending', 422, 'This order has already been reviewed.');
        $order->update($data + [
            'status' => 'declined',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);
        $this->notifyOrderStakeholders($order->refresh(), 'declined');

        return back()->with('status', ['success' => 1, 'msg' => 'Module order declined.']);
    }

    private function validatedPlan(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'feature_id' => ['required', 'integer', 'exists:features,id'],
            'code' => ['required', 'alpha_dash', 'max:100', Rule::unique('premium_module_plans', 'code')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'module_price' => ['required', 'numeric', 'min:0'],
            'billing_interval' => ['required', Rule::in(['month', 'year', 'one_time'])],
            'billing_interval_count' => ['required', 'integer', 'min:1', 'max:120'],
            'allowance_name' => ['required', 'string', 'max:100'],
            'included_allowance' => ['required', 'integer', 'min:0'],
            'allowance_reset' => ['required', Rule::in(['monthly', 'subscription', 'never'])],
            'capacity_increment' => ['required', 'integer', 'min:0'],
            'capacity_price' => ['required', 'numeric', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function authorizeSuperadmin(): void
    {
        abort_unless(auth()->user()->can('superadmin'), 403, 'Unauthorized action.');
    }

    private function notifyOrderStakeholders(BusinessModuleOrder $order, string $event): void
    {
        try {
            $order->loadMissing(['plan', 'business']);
            $userIds = collect([
                $order->requested_by,
                optional($order->business)->owner_id,
            ])->filter()->unique()->values();

            User::whereIn('id', $userIds)->get()->each(function (User $user) use ($order, $event) {
                $user->notify(new PremiumModuleOrderNotification($order, $event));
            });
        } catch (\Throwable $exception) {
            Log::warning('Unable to create premium module review notification.', [
                'order_id' => $order->id,
                'event' => $event,
                'exception' => get_class($exception),
            ]);
        }
    }
}
