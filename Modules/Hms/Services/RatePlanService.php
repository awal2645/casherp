<?php

namespace Modules\Hms\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Hms\Entities\HmsRatePlan;
use Modules\Hms\Entities\HmsRoomType;

class RatePlanService
{
    public function apply(
        int $businessId,
        int $propertyId,
        int $ratePlanId,
        int $roomTypeId,
        Carbon $arrival,
        Carbon $departure,
        int $adults,
        int $children,
        array $baseQuote,
        ?int $contactId = null,
        ?int $excludeTransactionId = null,
        int $requestedRoomCount = 1
    ): array {
        $plan = HmsRatePlan::where('business_id', $businessId)
            ->where('hms_property_id', $propertyId)
            ->where('is_active', true)
            ->with(['roomTypes' => fn ($query) => $query->where('hms_room_type_id', $roomTypeId)])
            ->find($ratePlanId);

        if (! $plan || $plan->roomTypes->isEmpty()) {
            throw ValidationException::withMessages(['rate_plan_id' => __('hms::lang.invalid_rate_plan')]);
        }

        $nights = (int) $baseQuote['nights'];
        $advanceDays = now()->startOfDay()->diffInDays($arrival->copy()->startOfDay(), false);
        if ($nights < (int) $plan->minimum_stay || ($plan->maximum_stay && $nights > (int) $plan->maximum_stay)) {
            throw ValidationException::withMessages(['rate_plan_id' => __('hms::lang.rate_plan_stay_restriction')]);
        }
        if ($advanceDays < (int) $plan->minimum_advance_days || ($plan->maximum_advance_days !== null && $advanceDays > (int) $plan->maximum_advance_days)) {
            throw ValidationException::withMessages(['rate_plan_id' => __('hms::lang.rate_plan_advance_restriction')]);
        }
        if ($plan->closed_to_arrival || $plan->closed_to_departure) {
            throw ValidationException::withMessages(['rate_plan_id' => __('hms::lang.rate_plan_closed')]);
        }
        if (($plan->valid_from && $arrival->toDateString() < $plan->valid_from->toDateString())
            || ($plan->valid_to && $departure->copy()->subSecond()->toDateString() > $plan->valid_to->toDateString())) {
            throw ValidationException::withMessages(['rate_plan_id' => __('hms::lang.rate_plan_date_restriction')]);
        }
        if ($plan->corporate_contact_id && (int) $plan->corporate_contact_id !== (int) $contactId) {
            throw ValidationException::withMessages(['rate_plan_id' => __('hms::lang.rate_plan_corporate_restriction')]);
        }

        $allowedDays = array_map('strtolower', (array) ($plan->days_of_week ?? []));
        if ($allowedDays) {
            foreach ($baseQuote['nightly'] as $night) {
                if (! in_array(strtolower(Carbon::parse($night['date'])->format('l')), $allowedDays, true)) {
                    throw ValidationException::withMessages(['rate_plan_id' => __('hms::lang.rate_plan_day_restriction')]);
                }
            }
        }

        $mapping = $plan->roomTypes->first();
        $roomType = HmsRoomType::where('business_id', $businessId)->findOrFail($roomTypeId);
        $this->assertAllotment(
            $businessId,
            $ratePlanId,
            $roomTypeId,
            $arrival,
            $departure,
            $mapping->allotment !== null ? (int) $mapping->allotment : null,
            $excludeTransactionId,
            $requestedRoomCount
        );
        $extraAdults = max(0, $adults - (int) $mapping->included_adults);
        $extraChildren = max(0, $children - (int) $mapping->included_children);
        $supplement = $extraAdults * (float) $mapping->extra_adult_rate
            + $extraChildren * (float) $mapping->extra_child_rate;

        $nightly = collect($baseQuote['nightly'])->map(function ($night) use ($mapping, $plan, $supplement) {
            $amount = $mapping->base_rate !== null ? (float) $mapping->base_rate : (float) $night['amount'];
            if ($mapping->base_rate === null) {
                $amount = $plan->adjustment_type === 'percentage'
                    ? $amount * (1 + ((float) $plan->adjustment_value / 100))
                    : $amount + (float) $plan->adjustment_value;
            }
            $night['amount'] = round(max(0, $amount + $supplement), 4);

            return $night;
        })->all();

        $total = round((float) collect($nightly)->sum('amount'), 4);

        return [
            'nights' => count($nightly),
            'nightly' => $nightly,
            'average_rate' => count($nightly) ? round($total / count($nightly), 4) : 0,
            'total' => $total,
            'rate_plan_id' => (int) $plan->id,
            'deposit_required' => round($total * (float) $plan->deposit_percent / 100, 4),
            'deposit_percent' => (float) $plan->deposit_percent,
            'is_refundable' => (bool) $plan->is_refundable,
            'tax_inclusive' => (bool) $plan->tax_inclusive,
        ];
    }

    private function assertAllotment(
        int $businessId,
        int $ratePlanId,
        int $roomTypeId,
        Carbon $arrival,
        Carbon $departure,
        ?int $allotment,
        ?int $excludeTransactionId,
        int $requestedRoomCount
    ): void {
        if ($allotment === null) {
            return;
        }

        $heldRooms = DB::table('hms_booking_lines as line')
            ->join('transactions as booking', 'booking.id', '=', 'line.transaction_id')
            ->where('booking.business_id', $businessId)
            ->where('booking.type', 'hms_booking')
            ->where('booking.hms_rate_plan_id', $ratePlanId)
            ->where('line.hms_room_type_id', $roomTypeId)
            ->when($excludeTransactionId, fn ($query) => $query->where('booking.id', '!=', $excludeTransactionId))
            ->where('booking.hms_booking_arrival_date_time', '<', $departure->format('Y-m-d H:i:s'))
            ->where('booking.hms_booking_departure_date_time', '>', $arrival->format('Y-m-d H:i:s'))
            ->where(function ($query) {
                $query->whereIn('booking.hms_booking_status', ['reserved', 'checked_in'])
                    ->orWhere(function ($tentative) {
                        $tentative->where('booking.hms_booking_status', 'tentative')
                            ->where(function ($activeHold) {
                                $activeHold->whereNull('booking.hms_hold_expires_at')
                                    ->orWhere('booking.hms_hold_expires_at', '>', now());
                            });
                    })
                    ->orWhere(function ($legacy) {
                        $legacy->whereNull('booking.hms_booking_status')
                            ->whereIn('booking.status', ['pending', 'confirmed'])
                            ->whereNull('booking.check_out');
                    });
            })
            ->count();

        if ($heldRooms + max(1, $requestedRoomCount) > $allotment) {
            throw ValidationException::withMessages([
                'rate_plan_id' => __('hms::lang.rate_plan_allotment_exhausted'),
            ]);
        }
    }
}
