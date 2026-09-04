<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Restaurant\OrderFulfilment;
use App\Services\RestaurantContextService;
use App\Services\RestaurantFulfilmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema;
use App\Restaurant\RestaurantOperationSetting;
use App\Business;

class FulfilmentController extends Controller
{
    public function index(Request $request, RestaurantContextService $context)
    {
        $this->authorizeView();
        $businessId = $context->businessId();
        $locationIds = $context->permittedLocationIds($businessId);
        $orders = OrderFulfilment::where('business_id', $businessId)
            ->when($locationIds !== null, fn ($q) => $q->whereIn('location_id', $locationIds))
            ->when($request->filled('service_channel'), fn ($q) => $q->where('service_channel', $request->service_channel))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest()->paginate(50);
        $settings = RestaurantOperationSetting::where('business_id', $businessId)->where('is_active', true)->first();
        $profileCode = optional(optional(Business::with('industry')->find($businessId))->industry)->code;
        $enabledChannelCodes = (array) ($settings->enabled_channels
            ?? config('restaurant_operations.industry_profiles.'.$profileCode.'.default_channels', array_keys(config('restaurant_operations.service_channels', []))));
        $availableChannels = collect(config('restaurant_operations.service_channels', []))->only($enabledChannelCodes)->all();

        $hotelSources = ['hms_booking' => collect(), 'hms_event' => collect()];
        if (($request->user()->can('hms.post_restaurant_charges') || $request->user()->can('superadmin'))
            && Schema::hasTable('hms_booking_lines')) {
            $hotelSources['hms_booking'] = \Modules\Hms\Entities\HmsTransactionClass::where('business_id', $businessId)
                ->where('type', 'hms_booking')->whereIn('hms_booking_status', ['reserved', 'checked_in'])
                ->when($locationIds !== null, fn ($query) => $query->whereHas('hms_property', fn ($property) => $property->whereIn('location_id', $locationIds)))
                ->with(['contact', 'hms_booking_lines.room'])->get()->mapWithKeys(function ($booking) {
                    $rooms = $booking->hms_booking_lines->pluck('room.room_number')->filter()->join(', ');
                    return [$booking->id => '#'.$booking->id.' · '.optional($booking->contact)->name.' · '.$rooms];
                });
        }
        if (($request->user()->can('hms.post_restaurant_charges') || $request->user()->can('superadmin'))
            && Schema::hasTable('hms_event_bookings')) {
            $hotelSources['hms_event'] = \Modules\Hms\Entities\HmsEventBooking::where('business_id', $businessId)
                ->when($locationIds !== null, fn ($query) => $query->whereIn('location_id', $locationIds))
                ->whereIn('status', ['tentative', 'confirmed', 'in_progress'])->orderBy('starts_at')
                ->get()->mapWithKeys(fn ($event) => [$event->id => $event->event_number.' · '.$event->event_name]);
        }

        return view('restaurant.operations.fulfilments', compact('orders', 'hotelSources', 'availableChannels'));
    }

    public function update(Request $request, int $fulfilment, RestaurantContextService $context, RestaurantFulfilmentService $service)
    {
        if (! auth()->user()->can('restaurant.orders.manage') && ! auth()->user()->can('sell.update')) {
            abort(403, 'Unauthorized action.');
        }
        $businessId = $context->businessId();
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(config('restaurant_operations.fulfilment_statuses', [])))],
            'service_channel' => ['nullable', Rule::in(array_keys(config('restaurant_operations.service_channels', [])))],
            'room_reference' => ['nullable', 'string', 'max:100'],
            'promised_at' => ['nullable', 'date'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'delivery_address' => ['nullable', 'array'],
            'source_type' => ['nullable', Rule::in(['hms_booking', 'hms_event'])],
            'source_id' => ['nullable', 'integer', 'min:1', 'required_with:source_type'],
            'hms_booking_id' => ['nullable', 'integer', 'min:1'],
            'hms_event_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $scoped = OrderFulfilment::where('business_id', $businessId)->whereKey($fulfilment)->firstOrFail();
        $context->assertLocation($businessId, (int) $scoped->location_id, $request->user());
        if (in_array($data['service_channel'] ?? $scoped->service_channel, ['room_service', 'banqueting'], true)
            && (! $request->user()->can('hms.post_restaurant_charges') && ! $request->user()->can('superadmin'))) {
            abort(403, 'You cannot post restaurant charges to hotel or event folios.');
        }
        $channel = $data['service_channel'] ?? $scoped->service_channel;
        if ($channel === 'room_service' && ! empty($data['hms_booking_id'])) {
            $data['source_type'] = 'hms_booking';
            $data['source_id'] = (int) $data['hms_booking_id'];
        } elseif ($channel === 'banqueting' && ! empty($data['hms_event_id'])) {
            $data['source_type'] = 'hms_event';
            $data['source_id'] = (int) $data['hms_event_id'];
        }
        unset($data['hms_booking_id'], $data['hms_event_id']);
        $service->transition($businessId, $fulfilment, $data['status'], (int) $request->user()->id, $data);

        return back()->with('status', ['success' => 1, 'msg' => 'Order fulfilment updated.']);
    }

    private function authorizeView(): void
    {
        if (! auth()->user()->can('restaurant.orders.view') && ! auth()->user()->can('sell.view')) {
            abort(403, 'Unauthorized action.');
        }
    }
}
