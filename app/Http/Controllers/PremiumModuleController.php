<?php

namespace App\Http\Controllers;

use App\BusinessModuleOrder;
use App\Notifications\PremiumModuleOrderNotification;
use App\PremiumModulePlan;
use App\Services\PremiumModuleEntitlementService;
use App\System;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PremiumModuleController extends Controller
{
    public function index(Request $request, PremiumModuleEntitlementService $entitlements)
    {
        $this->ensureCanPurchase($request);
        $businessId = (int) $request->session()->get('user.business_id');

        return view('premium_modules.index', [
            'catalog' => $entitlements->catalog($businessId),
            'currency' => System::getCurrency(),
            'orders' => BusinessModuleOrder::where('business_id', $businessId)
                ->with('plan')
                ->latest()
                ->take(20)
                ->get(),
        ]);
    }

    public function store(Request $request, PremiumModuleEntitlementService $entitlements)
    {
        $this->ensureCanPurchase($request);
        $businessId = (int) $request->session()->get('user.business_id');
        $data = $request->validate([
            'premium_module_plan_id' => [
                'required',
                'integer',
                Rule::exists('premium_module_plans', 'id')->where(
                    fn ($query) => $query->where('is_active', true)->whereNull('deleted_at')
                ),
            ],
            'order_type' => ['required', Rule::in(['module', 'capacity'])],
            'increments' => ['required', 'integer', 'min:1', 'max:100'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'customer_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $plan = PremiumModulePlan::where('is_active', true)
            ->findOrFail($data['premium_module_plan_id']);
        $status = $entitlements->status($businessId, $plan);

        if ($data['order_type'] === 'module') {
            if ($status['entitled']) {
                throw ValidationException::withMessages([
                    'order_type' => 'This company already has access to the selected module.',
                ]);
            }
            $data['increments'] = 1;
            $quantity = (int) $plan->included_allowance;
            $unitPrice = (float) $plan->module_price;
        } else {
            if (! $status['entitled']) {
                throw ValidationException::withMessages([
                    'order_type' => 'Activate the module before purchasing extra capacity.',
                ]);
            }
            if ((int) $plan->capacity_increment < 1) {
                throw ValidationException::withMessages([
                    'order_type' => 'Extra capacity is not available for this module.',
                ]);
            }
            $quantity = (int) $plan->capacity_increment * (int) $data['increments'];
            $unitPrice = (float) $plan->capacity_price;
        }

        $currency = System::getCurrency();
        $order = BusinessModuleOrder::create($data + [
            'business_id' => $businessId,
            'requested_by' => $request->user()->id,
            'allowance_quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $unitPrice * (int) $data['increments'],
            'currency' => $currency->code,
            'status' => 'pending',
        ]);
        $this->notifyOrderStakeholders($order, 'submitted');

        return redirect()->route('premium-modules.index')->with('status', [
            'success' => 1,
            'msg' => 'The module upgrade request was submitted for review.',
        ]);
    }

    public function cancel(Request $request, BusinessModuleOrder $order)
    {
        $this->ensureCanPurchase($request);
        abort_unless(
            (int) $order->business_id === (int) $request->session()->get('user.business_id'),
            403
        );
        abort_unless($order->status === 'pending', 422, 'Only pending requests can be cancelled.');
        $order->update(['status' => 'cancelled']);
        $this->notifyOrderStakeholders($order->refresh(), 'cancelled');

        return redirect()->route('premium-modules.index')->with('status', [
            'success' => 1,
            'msg' => 'The module upgrade request was cancelled.',
        ]);
    }

    private function ensureCanPurchase(Request $request): void
    {
        $businessId = (int) $request->session()->get('user.business_id');
        $isOwner = (int) $request->user()->id === (int) optional($request->user()->accessibleBusinesses()->find($businessId))->owner_id;
        abort_unless($isOwner || $request->user()->can('premium_modules.purchase'), 403, 'Unauthorized action.');
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
            Log::warning('Unable to create premium module order notification.', [
                'order_id' => $order->id,
                'event' => $event,
                'exception' => get_class($exception),
            ]);
        }
    }
}
