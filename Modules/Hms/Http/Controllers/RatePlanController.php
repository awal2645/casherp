<?php

namespace Modules\Hms\Http\Controllers;

use App\Contact;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Hms\Entities\HmsProperty;
use Modules\Hms\Entities\HmsRatePlan;
use Modules\Hms\Entities\HmsRatePlanRoomType;
use Modules\Hms\Entities\HmsRoomType;
use Modules\Hms\Http\Controllers\Concerns\AuthorizesHmsRequests;

class RatePlanController extends Controller
{
    use AuthorizesHmsRequests;

    public function index()
    {
        $businessId = $this->authorizeHms('hms.manage_rate_plans', 'hms_revenue_operations');
        $properties = HmsProperty::where('business_id', $businessId)->where('is_active', true)->pluck('name', 'id');
        $roomTypes = HmsRoomType::where('business_id', $businessId)->with('property')->orderBy('type')->get();
        $plans = HmsRatePlan::where('business_id', $businessId)->with(['property', 'roomTypes.roomType'])->orderBy('name')->get();
        $corporateContacts = Contact::where('business_id', $businessId)->whereIn('type', ['customer', 'both'])->pluck('name', 'id');

        return view('hms::rate_plans.index', compact('properties', 'roomTypes', 'plans', 'corporateContacts'));
    }

    public function store(Request $request)
    {
        $businessId = $this->authorizeHms('hms.manage_rate_plans', 'hms_revenue_operations');
        $data = $this->validated($request, $businessId);

        DB::transaction(function () use ($data, $businessId) {
            $roomType = HmsRoomType::where('business_id', $businessId)
                ->where('hms_property_id', $data['hms_property_id'])->findOrFail($data['hms_room_type_id']);
            if ((int) $data['included_adults'] > (int) $roomType->no_of_adult
                || (int) $data['included_children'] > (int) $roomType->no_of_child
                || ((int) $data['included_adults'] + (int) $data['included_children']) > (int) $roomType->max_occupancy) {
                throw ValidationException::withMessages([
                    'included_adults' => __('hms::lang.included_occupancy_exceeds_capacity'),
                ]);
            }
            $plan = HmsRatePlan::create(collect($data)->except([
                'hms_room_type_id', 'included_adults', 'included_children', 'base_rate',
                'extra_adult_rate', 'extra_child_rate', 'allotment',
            ])->all() + ['business_id' => $businessId, 'created_by' => auth()->id()]);
            HmsRatePlanRoomType::create([
                'business_id' => $businessId,
                'hms_rate_plan_id' => $plan->id,
                'hms_room_type_id' => $roomType->id,
                'included_adults' => $data['included_adults'],
                'included_children' => $data['included_children'],
                'base_rate' => $data['base_rate'] ?? null,
                'extra_adult_rate' => $data['extra_adult_rate'] ?? 0,
                'extra_child_rate' => $data['extra_child_rate'] ?? 0,
                'allotment' => $data['allotment'] ?? null,
            ]);
        });

        return back()->with('status', ['success' => 1, 'msg' => __('hms::lang.rate_plan_saved')]);
    }

    public function toggle($plan)
    {
        $businessId = $this->authorizeHms('hms.manage_rate_plans', 'hms_revenue_operations');
        $model = HmsRatePlan::where('business_id', $businessId)->findOrFail($plan);
        $model->update(['is_active' => ! $model->is_active]);

        return back()->with('status', ['success' => 1, 'msg' => __('hms::lang.rate_plan_saved')]);
    }

    private function validated(Request $request, int $businessId): array
    {
        $data = $request->validate([
            'hms_property_id' => ['required', 'integer', Rule::exists('hms_properties', 'id')->where('business_id', $businessId)],
            'hms_room_type_id' => ['required', 'integer', Rule::exists('hms_room_types', 'id')->where('business_id', $businessId)],
            'name' => 'required|string|max:191',
            'code' => [
                'required', 'alpha_dash', 'max:40',
                Rule::unique('hms_rate_plans', 'code')->where(fn ($query) => $query
                    ->where('business_id', $businessId)
                    ->where('hms_property_id', (int) $request->input('hms_property_id'))),
            ],
            'meal_plan' => ['required', Rule::in(['room_only', 'breakfast', 'half_board', 'full_board', 'all_inclusive'])],
            'adjustment_type' => ['required', Rule::in(['fixed', 'percentage'])],
            'adjustment_value' => 'required|numeric|min:-1000000|max:1000000',
            'included_adults' => 'required|integer|min:1|max:20',
            'included_children' => 'required|integer|min:0|max:20',
            'base_rate' => 'nullable|numeric|min:0',
            'extra_adult_rate' => 'nullable|numeric|min:0',
            'extra_child_rate' => 'nullable|numeric|min:0',
            'allotment' => 'nullable|integer|min:1',
            'minimum_stay' => 'required|integer|min:1|max:365',
            'maximum_stay' => 'nullable|integer|gte:minimum_stay|max:3650',
            'minimum_advance_days' => 'required|integer|min:0|max:3650',
            'maximum_advance_days' => 'nullable|integer|gte:minimum_advance_days|max:3650',
            'deposit_percent' => 'required|numeric|min:0|max:100',
            'free_cancellation_hours' => 'required|integer|min:0|max:8760',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
            'days_of_week' => 'nullable|array',
            'days_of_week.*' => [Rule::in(['monday','tuesday','wednesday','thursday','friday','saturday','sunday'])],
            'corporate_contact_id' => ['nullable', 'integer', Rule::exists('contacts', 'id')->where('business_id', $businessId)],
            'cancellation_policy' => 'nullable|string|max:5000',
        ]);
        foreach (['closed_to_arrival', 'closed_to_departure', 'is_refundable', 'tax_inclusive'] as $boolean) {
            $data[$boolean] = $request->boolean($boolean);
        }
        $data['is_active'] = true;

        return $data;
    }
}
