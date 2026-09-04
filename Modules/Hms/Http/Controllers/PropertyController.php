<?php

namespace Modules\Hms\Http\Controllers;

use App\Business;
use App\BusinessLocation;
use App\Currency;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Hms\Entities\HmsProperty;
use Modules\Hms\Http\Controllers\Concerns\AuthorizesHmsRequests;
use Modules\Hms\Services\HmsSaasService;

class PropertyController extends Controller
{
    use AuthorizesHmsRequests;

    public function index()
    {
        $businessId = $this->authorizeHms('hms.manage_properties');
        $properties = HmsProperty::where('business_id', $businessId)->with(['location', 'currency'])->orderBy('name')->get();
        $locations = BusinessLocation::where('business_id', $businessId)->pluck('name', 'id');
        $currencies = Currency::orderBy('code')->get()->mapWithKeys(fn ($currency) => [$currency->id => $currency->code . ' - ' . $currency->currency]);
        $business = Business::findOrFail($businessId);
        $propertyLimit = app(HmsSaasService::class)->propertyLimit($businessId);

        return view('hms::properties.index', compact('properties', 'locations', 'currencies', 'business', 'propertyLimit'));
    }

    public function store(Request $request)
    {
        $businessId = $this->authorizeHms('hms.manage_properties');
        app(HmsSaasService::class)->assertPropertyCapacity($businessId);
        $data = $this->validated($request, $businessId);
        $data['business_id'] = $businessId;
        $data['created_by'] = auth()->id();
        $data['business_date'] = $data['business_date'] ?? now()->toDateString();
        $data['booking_engine_enabled'] = $request->boolean('booking_engine_enabled');
        $data['channel_manager_enabled'] = $request->boolean('channel_manager_enabled');
        $data['is_active'] = $request->boolean('is_active', true);
        HmsProperty::create($data);

        return back()->with('status', ['success' => 1, 'msg' => __('hms::lang.property_saved')]);
    }

    public function update(Request $request, $property)
    {
        $businessId = $this->authorizeHms('hms.manage_properties');
        $model = HmsProperty::where('business_id', $businessId)->findOrFail($property);
        $data = $this->validated($request, $businessId, $model->id);
        // Once a property exists, its operating date advances only through the
        // auditable night-close workflow; form edits cannot skip or rewind it.
        unset($data['business_date']);
        $data['booking_engine_enabled'] = $request->boolean('booking_engine_enabled');
        $data['channel_manager_enabled'] = $request->boolean('channel_manager_enabled');
        $data['is_active'] = $request->boolean('is_active');
        $model->update($data);

        return back()->with('status', ['success' => 1, 'msg' => __('hms::lang.property_saved')]);
    }

    private function validated(Request $request, int $businessId, ?int $propertyId = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:191',
            'code' => ['required', 'string', 'max:40', Rule::unique('hms_properties', 'code')->where('business_id', $businessId)->ignore($propertyId)],
            'public_slug' => ['nullable', 'alpha_dash', 'max:191', Rule::unique('hms_properties', 'public_slug')->ignore($propertyId)],
            'location_id' => ['nullable', 'integer', Rule::exists('business_locations', 'id')->where('business_id', $businessId), Rule::unique('hms_properties', 'location_id')->where('business_id', $businessId)->ignore($propertyId)],
            'currency_id' => 'nullable|integer|exists:currencies,id',
            'timezone' => ['required', Rule::in(timezone_identifiers_list())],
            'business_date' => 'nullable|date',
            'default_check_in_time' => 'required|date_format:H:i',
            'default_check_out_time' => 'required|date_format:H:i',
            'booking_engine_enabled' => 'nullable|boolean',
            'channel_manager_enabled' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);
    }
}
