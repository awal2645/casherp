<?php

namespace Modules\Hms\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Hms\Entities\HmsProperty;
use Modules\Hms\Entities\HmsRevenueBudget;
use Modules\Hms\Http\Controllers\Concerns\AuthorizesHmsRequests;
use Modules\Hms\Services\RevenueService;

class RevenueController extends Controller
{
    use AuthorizesHmsRequests;

    public function index(Request $request, RevenueService $service)
    {
        $businessId = $this->authorizeHms('hms.view_revenue', 'hms_revenue_operations');
        $properties = HmsProperty::where('business_id', $businessId)->where('is_active', true)->pluck('name', 'id');
        $propertyId = (int) ($request->input('property_id') ?: $properties->keys()->first());
        $start = Carbon::parse($request->input('start_date', now()->startOfMonth()->toDateString()));
        $end = Carbon::parse($request->input('end_date', now()->endOfMonth()->toDateString()));
        $metrics = $propertyId ? $service->dashboard($businessId, $propertyId, $start, $end) : null;
        $budget = $propertyId ? HmsRevenueBudget::where('business_id', $businessId)->where('hms_property_id', $propertyId)
            ->whereDate('month', $start->copy()->startOfMonth())->first() : null;

        return view('hms::revenue.index', compact('properties', 'propertyId', 'start', 'end', 'metrics', 'budget'));
    }

    public function saveBudget(Request $request)
    {
        $businessId = $this->authorizeHms('hms.manage_revenue', 'hms_revenue_operations');
        $data = $request->validate([
            'property_id' => ['required', 'integer', Rule::exists('hms_properties', 'id')->where('business_id', $businessId)],
            'month' => 'required|date', 'room_revenue_budget' => 'required|numeric|min:0',
            'occupancy_budget' => 'required|numeric|min:0|max:100', 'adr_budget' => 'required|numeric|min:0',
        ]);
        HmsRevenueBudget::updateOrCreate([
            'business_id' => $businessId, 'hms_property_id' => $data['property_id'],
            'month' => Carbon::parse($data['month'])->startOfMonth()->toDateString(),
        ], collect($data)->only(['room_revenue_budget', 'occupancy_budget', 'adr_budget'])->all() + ['created_by' => auth()->id()]);

        return back()->with('status', ['success' => 1, 'msg' => __('hms::lang.revenue_budget_saved')]);
    }
}
