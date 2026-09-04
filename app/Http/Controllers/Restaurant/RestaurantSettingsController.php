<?php

namespace App\Http\Controllers\Restaurant;

use App\Business;
use App\Http\Controllers\Controller;
use App\Restaurant\RestaurantOperationSetting;
use App\Services\RestaurantContextService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RestaurantSettingsController extends Controller
{
    public function edit(RestaurantContextService $context)
    {
        $this->authorizeManage();
        $businessId = $context->businessId();
        $business = Business::with('industry')->findOrFail($businessId);
        $profile = (array) config('restaurant_operations.industry_profiles.'.optional($business->industry)->code, []);
        $settings = RestaurantOperationSetting::firstOrCreate(
            ['business_id' => $businessId],
            [
                'enabled_channels' => $profile['default_channels'] ?? ['dine_in', 'counter', 'takeaway'],
                'reservation_policy' => [
                    'hold_minutes' => config('restaurant_operations.default_settings.reservation_hold_minutes'),
                    'table_turn_minutes' => config('restaurant_operations.default_settings.default_table_turn_minutes'),
                ],
                'kitchen_policy' => [
                    'warning_minutes' => config('restaurant_operations.default_settings.kitchen_warning_minutes'),
                    'critical_minutes' => config('restaurant_operations.default_settings.kitchen_critical_minutes'),
                    'require_void_reason' => true,
                ],
                'inventory_policy' => ['allow_negative_ingredient_stock' => false],
                'register_policy' => ['require_variance_reason' => true, 'require_independent_approval' => true],
            ]
        );

        return view('restaurant.operations.settings', compact('settings', 'business', 'profile'));
    }

    public function update(Request $request, RestaurantContextService $context)
    {
        $this->authorizeManage();
        $businessId = $context->businessId();
        $data = $request->validate([
            'enabled_channels' => ['required', 'array', 'min:1'],
            'enabled_channels.*' => [Rule::in(array_keys(config('restaurant_operations.service_channels', [])))],
            'reservation_hold_minutes' => ['required', 'integer', 'min:0', 'max:10080'],
            'default_table_turn_minutes' => ['required', 'integer', 'min:15', 'max:1440'],
            'kitchen_warning_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'kitchen_critical_minutes' => ['required', 'integer', 'gt:kitchen_warning_minutes', 'max:2880'],
            'allow_negative_ingredient_stock' => ['nullable', 'boolean'],
            'require_void_reason' => ['nullable', 'boolean'],
            'require_register_variance_reason' => ['nullable', 'boolean'],
            'require_register_approval' => ['nullable', 'boolean'],
        ]);
        RestaurantOperationSetting::updateOrCreate(['business_id' => $businessId], [
            'enabled_channels' => array_values(array_unique($data['enabled_channels'])),
            'reservation_policy' => [
                'hold_minutes' => $data['reservation_hold_minutes'],
                'table_turn_minutes' => $data['default_table_turn_minutes'],
            ],
            'kitchen_policy' => [
                'warning_minutes' => $data['kitchen_warning_minutes'],
                'critical_minutes' => $data['kitchen_critical_minutes'],
                'require_void_reason' => ! empty($data['require_void_reason']),
            ],
            'inventory_policy' => ['allow_negative_ingredient_stock' => ! empty($data['allow_negative_ingredient_stock'])],
            'register_policy' => [
                'require_variance_reason' => ! empty($data['require_register_variance_reason']),
                'require_independent_approval' => ! empty($data['require_register_approval']),
            ],
            'is_active' => true,
        ]);

        return back()->with('status', ['success' => 1, 'msg' => 'Restaurant operating policy saved for this company only.']);
    }

    private function authorizeManage(): void
    {
        if (! auth()->user()->can('restaurant.settings.manage') && ! auth()->user()->can('business_settings.access')) {
            abort(403, 'Unauthorized action.');
        }
    }
}
