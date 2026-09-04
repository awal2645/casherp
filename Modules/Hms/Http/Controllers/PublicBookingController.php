<?php

namespace Modules\Hms\Http\Controllers;

use App\Business;
use App\Contact;
use App\Http\Controllers\Controller;
use App\Services\FeatureAccessService;
use App\Utils\ContactUtil;
use App\Utils\Util;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Hms\Entities\HmsBookingLine;
use Modules\Hms\Entities\HmsProperty;
use Modules\Hms\Entities\HmsRatePlan;
use Modules\Hms\Entities\HmsRoomType;
use Modules\Hms\Entities\HmsTransactionClass;
use Modules\Hms\Services\BookingIntegrityService;
use Modules\Hms\Services\BookingLifecycleService;
use Modules\Hms\Services\FolioService;
use Modules\Hms\Services\GuestProfileService;
use Modules\Hms\Services\HmsSaasService;

class PublicBookingController extends Controller
{
    public function show(Request $request, string $slug, BookingIntegrityService $integrity)
    {
        $property = $this->property($slug);
        $arrival = Carbon::parse($request->input('arrival', now()->addDay()->toDateString()));
        $departure = Carbon::parse($request->input('departure', now()->addDays(2)->toDateString()));
        if ($departure->lte($arrival)) {
            $departure = $arrival->copy()->addDay();
        }
        $roomTypes = HmsRoomType::where('business_id', $property->business_id)
            ->where('hms_property_id', $property->id)->with(['media', 'rooms'])->get()
            ->filter(function ($type) use ($property, $arrival, $departure, $integrity) {
                return ! empty($integrity->availableRooms((int) $property->business_id, (int) $type->id, $arrival, $departure));
            });
        $ratePlans = HmsRatePlan::where('business_id', $property->business_id)->where('hms_property_id', $property->id)
            ->where('is_active', true)->where(function ($q) use ($arrival) {
                $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', $arrival);
            })->where(function ($q) use ($departure) {
                $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $departure->copy()->subDay());
            })->with('roomTypes')->get();
        $ratePlansByRoomType = [];
        foreach ($ratePlans as $ratePlan) {
            foreach ($ratePlan->roomTypes as $mapping) {
                $ratePlansByRoomType[(int) $mapping->hms_room_type_id][(int) $ratePlan->id] = $ratePlan->name;
            }
        }

        return view('hms::public.booking', compact('property', 'arrival', 'departure', 'roomTypes', 'ratePlansByRoomType'));
    }

    public function store(
        Request $request,
        string $slug,
        BookingIntegrityService $integrity,
        BookingLifecycleService $lifecycle,
        GuestProfileService $guests,
        FolioService $folios,
        ContactUtil $contactUtil,
        Util $util
    ) {
        $property = $this->property($slug);
        $businessId = (int) $property->business_id;
        $data = $request->validate([
            'room_type_id' => ['required', 'integer', Rule::exists('hms_room_types', 'id')->where(fn ($q) => $q->where('business_id', $businessId)->where('hms_property_id', $property->id))],
            'rate_plan_id' => ['nullable', 'integer', Rule::exists('hms_rate_plans', 'id')->where(fn ($q) => $q->where('business_id', $businessId)->where('hms_property_id', $property->id)->where('is_active', true))],
            'arrival' => 'required|date|after_or_equal:today',
            'departure' => 'required|date|after:arrival',
            'adults' => 'required|integer|min:1|max:20',
            'children' => 'required|integer|min:0|max:20',
            'name' => 'required|string|max:191',
            'email' => 'required|email:rfc|max:191',
            'mobile' => 'required|string|max:30',
            'preferred_language' => 'nullable|string|max:12',
            'nationality' => 'nullable|string|max:80',
            'accessibility_needs' => 'nullable|string|max:3000',
            'special_requests' => 'nullable|string|max:5000',
            'marketing_consent' => 'nullable|boolean',
            'terms_accepted' => 'accepted',
            'website' => 'nullable|size:0',
        ]);
        $arrival = Carbon::parse($data['arrival'] . ' ' . substr($property->default_check_in_time, 0, 5));
        $departure = Carbon::parse($data['departure'] . ' ' . substr($property->default_check_out_time, 0, 5));

        return DB::transaction(function () use ($data, $request, $property, $businessId, $arrival, $departure, $integrity, $lifecycle, $guests, $folios, $contactUtil, $util) {
            $available = $integrity->availableRooms($businessId, (int) $data['room_type_id'], $arrival, $departure);
            if (empty($available)) {
                throw ValidationException::withMessages(['room_type_id' => __('hms::lang.room_no_longer_available')]);
            }
            $roomId = (int) array_key_first($available);
            $calculation = $integrity->prepare($businessId, $arrival, $departure, [[
                'room_id' => $roomId, 'type_id' => (int) $data['room_type_id'],
                'no_of_adult' => (int) $data['adults'], 'no_of_child' => (int) $data['children'],
            ]], [], [
                'hms_property_id' => $property->id,
                'hms_rate_plan_id' => $data['rate_plan_id'] ?? null,
                'contact_id' => null,
            ]);

            $business = Business::findOrFail($businessId);
            $actorId = (int) ($property->created_by ?: $business->owner_id);
            if ($actorId <= 0) {
                throw ValidationException::withMessages([
                    'booking' => __('hms::lang.public_booking_unavailable'),
                ]);
            }
            $contact = Contact::where('business_id', $businessId)->where('email', $data['email'])->first();
            if (! $contact) {
                $result = $contactUtil->createNewContact([
                    'business_id' => $businessId, 'type' => 'customer', 'name' => $data['name'],
                    'email' => $data['email'], 'mobile' => $data['mobile'], 'created_by' => $actorId,
                ]);
                $contact = $result['data'];
            }
            $guest = $guests->sync($businessId, $contact->id, [
                'preferred_language' => $data['preferred_language'] ?? null,
                'nationality' => $data['nationality'] ?? null,
                'accessibility_needs' => $data['accessibility_needs'] ?? null,
                'marketing_consent' => $request->boolean('marketing_consent'),
                'consent_source' => 'public_booking_engine',
            ]);
            $prefix = optional(json_decode($business->hms_settings))->prefix;
            $count = $util->setAndGetReferenceCount('hms_booking', $businessId);
            $booking = HmsTransactionClass::create([
                'business_id' => $businessId, 'location_id' => $property->location_id, 'type' => 'hms_booking',
                'status' => 'pending', 'contact_id' => $contact->id, 'created_by' => $actorId,
                'ref_no' => $util->generateReferenceNumber('hms_booking', $count, $businessId, $prefix),
                'transaction_date' => now(), 'payment_status' => 'due',
                'total_before_tax' => $calculation['total_before_tax'], 'final_total' => $calculation['final_total'],
                'tax_id' => $calculation['tax_id'], 'tax_amount' => $calculation['tax_amount'],
                'discount_amount' => 0, 'hms_booking_arrival_date_time' => $arrival,
                'hms_booking_departure_date_time' => $departure, 'hms_property_id' => $property->id,
                'hms_rate_plan_id' => $calculation['hms_rate_plan_id'], 'hms_guest_profile_id' => $guest->id,
                'hms_booking_source' => 'website', 'hms_hold_expires_at' => now()->addMinutes(30),
                'hms_special_requests' => $data['special_requests'] ?? null,
            ]);
            $booking->hms_booking_lines()->saveMany(array_map(fn ($line) => new HmsBookingLine($line), $calculation['room_lines']));
            $lifecycle->initialize($booking, 'pending', $actorId);
            $folio = $folios->syncBooking($booking->fresh(), $actorId);
            if ($calculation['deposit_required'] > 0) {
                $folios->scheduleDeposit($folio, (float) $calculation['deposit_required'], now()->toDateString(), $actorId, __('hms::lang.public_booking_deposit'));
            }

            return redirect()->route('hms.public_booking.show', $property->public_slug)
                ->with('booking_confirmation', $booking->ref_no);
        });
    }

    private function property(string $slug): HmsProperty
    {
        $property = HmsProperty::where('public_slug', $slug)->where('booking_engine_enabled', true)->where('is_active', true)->firstOrFail();
        if (! app(FeatureAccessService::class)->enabled('hms', (int) $property->business_id)
            || ! app(HmsSaasService::class)->allows((int) $property->business_id, 'hms_guest_experience')) {
            abort(404);
        }

        return $property;
    }
}
