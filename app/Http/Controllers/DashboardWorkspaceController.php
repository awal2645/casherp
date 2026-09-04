<?php

namespace App\Http\Controllers;

use App\Services\DashboardWorkspaceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DashboardWorkspaceController extends Controller
{
    public function index(Request $request, DashboardWorkspaceService $dashboard)
    {
        $businessId = (int) $request->session()->get('user.business_id');
        $data = $request->validate([
            'preset' => ['nullable', Rule::in(['today', 'last_7_days', 'last_30_days', 'this_month', 'this_quarter', 'this_year', 'custom'])],
            'start' => ['nullable', 'required_if:preset,custom', 'date_format:Y-m-d'],
            'end' => ['nullable', 'required_if:preset,custom', 'date_format:Y-m-d', 'after_or_equal:start'],
            'location_id' => ['nullable', 'integer', Rule::exists('business_locations', 'id')->where('business_id', $businessId)],
        ]);

        return response()->json(['data' => $dashboard->payload($request, $data)]);
    }

    public function updatePreferences(Request $request, DashboardWorkspaceService $dashboard)
    {
        $businessId = (int) $request->session()->get('user.business_id');
        $data = $request->validate([
            'date_preset' => ['required', Rule::in(['today', 'last_7_days', 'last_30_days', 'this_month', 'this_quarter', 'this_year'])],
            'default_location_id' => ['nullable', 'integer', Rule::exists('business_locations', 'id')->where('business_id', $businessId)],
            'density' => ['required', Rule::in(['comfortable', 'compact'])],
            'accent' => ['required', Rule::in(['ocean', 'violet', 'emerald', 'sunset'])],
            'hidden_sections' => ['nullable', 'array', 'max:6'],
            'hidden_sections.*' => ['string', 'distinct', Rule::in(['metrics', 'trend', 'attention', 'quick_actions', 'recent', 'onboarding'])],
        ]);
        $preference = $dashboard->savePreference($request, $data);

        return response()->json([
            'message' => 'Dashboard preferences saved.',
            'data' => ['preferences' => $preference->only(['default_location_id', 'date_preset', 'density', 'accent', 'hidden_sections'])],
        ]);
    }
}
