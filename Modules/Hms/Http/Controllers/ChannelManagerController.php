<?php

namespace Modules\Hms\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Hms\Entities\HmsChannelConnection;
use Modules\Hms\Entities\HmsChannelMapping;
use Modules\Hms\Entities\HmsProperty;
use Modules\Hms\Entities\HmsRatePlan;
use Modules\Hms\Entities\HmsRoomType;
use Modules\Hms\Http\Controllers\Concerns\AuthorizesHmsRequests;

class ChannelManagerController extends Controller
{
    use AuthorizesHmsRequests;

    public function index()
    {
        $businessId = $this->authorizeHms('hms.manage_channels', 'hms_channel_manager');
        $connections = HmsChannelConnection::where('business_id', $businessId)->with(['property', 'mappings', 'messages' => fn ($q) => $q->latest()->limit(10)])->get();
        $properties = HmsProperty::where('business_id', $businessId)->where('is_active', true)->pluck('name', 'id');
        $roomTypes = HmsRoomType::where('business_id', $businessId)->with('property')->get();
        $ratePlans = HmsRatePlan::where('business_id', $businessId)->where('is_active', true)->get();

        return view('hms::channels.index', compact('connections', 'properties', 'roomTypes', 'ratePlans'));
    }

    public function store(Request $request)
    {
        $businessId = $this->authorizeHms('hms.manage_channels', 'hms_channel_manager');
        $data = $request->validate([
            'hms_property_id' => ['required', 'integer', Rule::exists('hms_properties', 'id')->where('business_id', $businessId)],
            'provider' => ['required', Rule::in(['booking_com', 'expedia', 'airbnb', 'agoda', 'google_hotel', 'custom'])],
            'name' => 'required|string|max:191',
        ]);
        $property = HmsProperty::where('business_id', $businessId)->findOrFail($data['hms_property_id']);
        $secret = Str::random(64);
        HmsChannelConnection::create($data + [
            'business_id' => $businessId,
            'public_key' => 'hms_' . Str::random(32),
            'secret_encrypted' => Crypt::encryptString($secret),
            'status' => 'active',
            'created_by' => auth()->id(),
        ]);
        $property->update(['channel_manager_enabled' => true]);

        return back()->with('status', ['success' => 1, 'msg' => __('hms::lang.channel_connection_saved')])
            ->with('hms_channel_secret', $secret);
    }

    public function addMapping(Request $request, $connection)
    {
        $businessId = $this->authorizeHms('hms.manage_channels', 'hms_channel_manager');
        $model = HmsChannelConnection::where('business_id', $businessId)->findOrFail($connection);
        $data = $request->validate([
            'local_type' => ['required', Rule::in(['room_type', 'rate_plan'])],
            'local_id' => 'required|integer',
            'external_id' => 'required|string|max:120',
        ]);
        $local = $data['local_type'] === 'room_type'
            ? HmsRoomType::where('business_id', $businessId)->where('hms_property_id', $model->hms_property_id)->findOrFail($data['local_id'])
            : HmsRatePlan::where('business_id', $businessId)->where('hms_property_id', $model->hms_property_id)->findOrFail($data['local_id']);
        HmsChannelMapping::updateOrCreate([
            'hms_channel_connection_id' => $model->id,
            'local_type' => $data['local_type'],
            'local_id' => $local->id,
        ], [
            'business_id' => $businessId,
            'external_type' => $data['local_type'],
            'external_id' => $data['external_id'],
        ]);

        return back()->with('status', ['success' => 1, 'msg' => __('hms::lang.channel_mapping_saved')]);
    }

    public function rotateSecret($connection)
    {
        $businessId = $this->authorizeHms('hms.manage_channels', 'hms_channel_manager');
        $model = HmsChannelConnection::where('business_id', $businessId)->findOrFail($connection);
        $secret = Str::random(64);
        $model->update(['secret_encrypted' => Crypt::encryptString($secret)]);

        return back()->with('status', ['success' => 1, 'msg' => __('hms::lang.channel_secret_rotated')])
            ->with('hms_channel_secret', $secret);
    }

    public function toggle($connection)
    {
        $businessId = $this->authorizeHms('hms.manage_channels', 'hms_channel_manager');
        $model = HmsChannelConnection::where('business_id', $businessId)->findOrFail($connection);
        $model->update(['status' => $model->status === 'active' ? 'paused' : 'active']);

        return back()->with('status', ['success' => 1, 'msg' => __('hms::lang.channel_connection_status_updated')]);
    }
}
