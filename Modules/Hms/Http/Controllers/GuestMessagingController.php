<?php

namespace Modules\Hms\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Hms\Entities\HmsGuestMessageLog;
use Modules\Hms\Entities\HmsGuestMessageRule;
use Modules\Hms\Entities\HmsProperty;
use Modules\Hms\Http\Controllers\Concerns\AuthorizesHmsRequests;

class GuestMessagingController extends Controller
{
    use AuthorizesHmsRequests;

    public function index()
    {
        $businessId = $this->authorizeHms('hms.manage_guest_messages', 'hms_guest_experience');
        $rules = HmsGuestMessageRule::where('business_id', $businessId)->latest()->get();
        $logs = HmsGuestMessageLog::where('business_id', $businessId)
            ->with(['rule', 'booking', 'contact'])
            ->latest()
            ->limit(100)
            ->get();
        $properties = HmsProperty::where('business_id', $businessId)->where('is_active', true)->pluck('name', 'id');

        return view('hms::guest_messages.index', compact('rules', 'logs', 'properties'));
    }

    public function store(Request $request)
    {
        $businessId = $this->authorizeHms('hms.manage_guest_messages', 'hms_guest_experience');
        $data = $request->validate([
            'hms_property_id' => ['nullable', 'integer', Rule::exists('hms_properties', 'id')->where('business_id', $businessId)],
            'name' => 'required|string|max:191',
            'event_type' => ['required', Rule::in(['pre_arrival', 'arrival_day', 'in_stay', 'pre_departure', 'post_departure'])],
            'channel' => ['required', Rule::in(['email'])],
            'timing_offset_minutes' => 'required|integer|min:-525600|max:525600',
            'subject' => 'required|string|max:191',
            'body' => 'required|string|max:10000',
            'requires_marketing_consent' => 'nullable|boolean',
        ]);
        $data['requires_marketing_consent'] = $request->boolean('requires_marketing_consent');
        HmsGuestMessageRule::create($data + ['business_id' => $businessId, 'is_active' => true, 'created_by' => auth()->id()]);

        return back()->with('status', ['success' => 1, 'msg' => __('hms::lang.message_rule_saved')]);
    }

    public function toggle($rule)
    {
        $businessId = $this->authorizeHms('hms.manage_guest_messages', 'hms_guest_experience');
        $model = HmsGuestMessageRule::where('business_id', $businessId)->findOrFail($rule);
        $model->update(['is_active' => ! $model->is_active]);

        return back()->with('status', ['success' => 1, 'msg' => __('hms::lang.message_rule_saved')]);
    }
}
