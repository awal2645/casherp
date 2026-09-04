<?php

namespace Modules\Hms\Services;

use App\TaxRate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Hms\Entities\HmsCoupon;
use Modules\Hms\Entities\HmsExtra;
use Modules\Hms\Entities\HmsGroupBooking;
use Modules\Hms\Entities\HmsRoom;
use Modules\Hms\Entities\HmsRoomType;

class BookingIntegrityService
{
    public function __construct(
        protected RoomRateService $roomRateService,
        protected ?RatePlanService $ratePlanService = null
    )
    {
    }

    /**
     * Validate inventory, capacity and prices, then return trusted booking lines
     * and totals. Browser-submitted prices are intentionally ignored.
     */
    public function prepare(
        int $businessId,
        Carbon $arrival,
        Carbon $departure,
        array $requestedRooms,
        array $requestedExtras,
        array $financialInput,
        ?int $excludeTransactionId = null
    ): array {
        if ($departure->copy()->startOfDay()->lessThanOrEqualTo($arrival->copy()->startOfDay())) {
            throw ValidationException::withMessages([
                'departure_date' => __('hms::lang.departure_after_arrival'),
            ]);
        }

        if (empty($requestedRooms)) {
            throw ValidationException::withMessages([
                'rooms' => __('hms::lang.at_least_one_room_required'),
            ]);
        }

        $roomIds = array_map('intval', array_column($requestedRooms, 'room_id'));
        if (count($roomIds) !== count(array_unique($roomIds))) {
            throw ValidationException::withMessages([
                'rooms' => __('hms::lang.duplicate_room_selected'),
            ]);
        }

        $rooms = HmsRoom::with('type')
            ->whereHas('type', fn ($query) => $query->where('business_id', $businessId))
            ->whereIn('id', $roomIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($rooms->count() !== count($roomIds)) {
            throw ValidationException::withMessages([
                'rooms' => __('hms::lang.invalid_room_selection'),
            ]);
        }

        $propertyIds = $rooms->pluck('hms_property_id')->filter()->map(fn ($id) => (int) $id)->unique();
        if ($propertyIds->count() !== 1) {
            throw ValidationException::withMessages(['rooms' => __('hms::lang.rooms_must_share_property')]);
        }
        $propertyId = (int) $propertyIds->first();
        if (! empty($financialInput['hms_property_id']) && (int) $financialInput['hms_property_id'] !== $propertyId) {
            throw ValidationException::withMessages(['hms_property_id' => __('hms::lang.room_property_mismatch')]);
        }

        $groupId = ! empty($financialInput['hms_group_booking_id']) ? (int) $financialInput['hms_group_booking_id'] : null;
        if ($groupId) {
            $group = HmsGroupBooking::where('business_id', $businessId)
                ->where('hms_property_id', $propertyId)
                ->whereIn('status', ['tentative', 'confirmed'])
                ->findOrFail($groupId);
            if ($arrival->lt($group->arrival_at) || $departure->gt($group->departure_at)) {
                throw ValidationException::withMessages(['hms_group_booking_id' => __('hms::lang.group_stay_restriction')]);
            }
        }

        $roomLines = [];
        $adultsTotal = 0;
        $childrenTotal = 0;
        $ratePlanDepositPercent = null;
        $ratePlanTaxInclusive = false;
        $requestedTypeCounts = collect($requestedRooms)
            ->countBy(fn ($room) => (int) $room['type_id']);

        foreach ($requestedRooms as $index => $requestedRoom) {
            $room = $rooms->get((int) $requestedRoom['room_id']);
            $typeId = (int) $requestedRoom['type_id'];
            $adults = (int) $requestedRoom['no_of_adult'];
            $children = (int) $requestedRoom['no_of_child'];

            if ((int) $room->hms_room_type_id !== $typeId) {
                throw ValidationException::withMessages([
                    "rooms.$index.type_id" => __('hms::lang.room_type_mismatch'),
                ]);
            }

            if (Schema::hasColumn('hms_rooms', 'housekeeping_status')
                && $room->housekeeping_status === 'out_of_order') {
                throw ValidationException::withMessages([
                    "rooms.$index.room_id" => __('hms::lang.room_out_of_order'),
                ]);
            }

            $type = $room->type;
            if ($adults < 1 || $children < 0
                || $adults > (int) $type->no_of_adult
                || $children > (int) $type->no_of_child
                || ($adults + $children) > (int) $type->max_occupancy) {
                throw ValidationException::withMessages([
                    "rooms.$index.no_of_adult" => __('hms::lang.room_capacity_exceeded', [
                        'room' => $room->room_number,
                    ]),
                ]);
            }

            $this->assertAvailable(
                $businessId,
                (int) $room->id,
                $arrival,
                $departure,
                $excludeTransactionId,
                $groupId
            );

            $quote = $this->roomRateService->quote(
                $typeId,
                $arrival,
                $departure,
                $adults,
                $children
            );

            if (! empty($financialInput['hms_rate_plan_id'])) {
                $quote = ($this->ratePlanService ?: new RatePlanService())->apply(
                    $businessId,
                    $propertyId,
                    (int) $financialInput['hms_rate_plan_id'],
                    $typeId,
                    $arrival,
                    $departure,
                    $adults,
                    $children,
                    $quote,
                    ! empty($financialInput['contact_id']) ? (int) $financialInput['contact_id'] : null,
                    $excludeTransactionId,
                    (int) ($requestedTypeCounts[$typeId] ?? 1)
                );
                $ratePlanDepositPercent = (float) ($quote['deposit_percent'] ?? 0);
                $ratePlanTaxInclusive = (bool) ($quote['tax_inclusive'] ?? false);
            }

            $roomLines[] = [
                'hms_property_id' => $propertyId,
                'hms_room_id' => (int) $room->id,
                'hms_room_type_id' => $typeId,
                'adults' => $adults,
                'childrens' => $children,
                'price' => $quote['average_rate'],
                'total_price' => $quote['total'],
            ];

            $adultsTotal += $adults;
            $childrenTotal += $children;
        }

        $nights = $arrival->copy()->startOfDay()->diffInDays($departure->copy()->startOfDay());
        $extraLines = $this->prepareExtras(
            $businessId,
            $requestedExtras,
            $nights,
            $adultsTotal + $childrenTotal
        );

        $roomTotal = round((float) collect($roomLines)->sum('total_price'), 4);
        $extraTotal = round((float) collect($extraLines)
            ->where('financial_classification', 'revenue')
            ->sum('price'), 4);
        $securityDepositRequired = round((float) collect($extraLines)
            ->where('financial_classification', 'refundable_security_deposit')
            ->sum('price'), 4);
        $beforeTax = round($roomTotal + $extraTotal, 4);

        $discount = $this->calculateDiscount(
            $businessId,
            $arrival,
            $roomLines,
            $beforeTax,
            $financialInput
        );

        $taxId = ! empty($financialInput['tax_rate_id'])
            ? (int) $financialInput['tax_rate_id']
            : null;
        $taxRate = 0.0;

        if ($taxId) {
            $tax = TaxRate::where('business_id', $businessId)->find($taxId);
            if (! $tax) {
                throw ValidationException::withMessages([
                    'tax_rate_id' => __('hms::lang.invalid_tax_rate'),
                ]);
            }
            $taxRate = (float) $tax->amount;
        }

        $discountValue = min($beforeTax, $discount['value']);
        $taxable = max(0, $beforeTax - $discountValue);
        if ($ratePlanTaxInclusive && $taxRate > 0) {
            $taxAmount = round($taxable - ($taxable / (1 + ($taxRate / 100))), 4);
            $finalTotal = round($taxable, 4);
        } else {
            $taxAmount = round($taxable * $taxRate / 100, 4);
            $finalTotal = round($taxable + $taxAmount, 4);
        }
        $depositRequired = $ratePlanDepositPercent !== null
            ? round($finalTotal * $ratePlanDepositPercent / 100, 4)
            : 0.0;

        return [
            'hms_property_id' => $propertyId,
            'hms_rate_plan_id' => ! empty($financialInput['hms_rate_plan_id']) ? (int) $financialInput['hms_rate_plan_id'] : null,
            'hms_group_booking_id' => $groupId,
            'room_lines' => $roomLines,
            'extra_lines' => $extraLines,
            'room_total' => $roomTotal,
            'extra_total' => $extraTotal,
            'security_deposit_required' => $securityDepositRequired,
            'total_before_tax' => $beforeTax,
            'discount_type' => $discount['type'],
            'discount_amount' => $discount['amount'],
            'discount_value' => $discountValue,
            'coupon_id' => $discount['coupon_id'],
            'tax_id' => $taxId,
            'tax_amount' => $taxAmount,
            'tax_inclusive' => $ratePlanTaxInclusive,
            'final_total' => $finalTotal,
            'adults' => $adultsTotal,
            'children' => $childrenTotal,
            'nights' => $nights,
            'deposit_required' => round($depositRequired, 4),
        ];
    }

    /**
     * Reuse the authoritative booking, maintenance and group-block inventory
     * check for operational room moves. Departure remains an exclusive bound.
     */
    public function assertRoomAvailableForMove(
        int $businessId,
        int $roomId,
        Carbon $arrival,
        Carbon $departure,
        int $excludeTransactionId,
        ?int $groupBookingId = null
    ): void {
        $this->assertAvailable($businessId, $roomId, $arrival, $departure, $excludeTransactionId, $groupBookingId);
    }

    public function availableRooms(
        int $businessId,
        int $roomTypeId,
        Carbon $arrival,
        Carbon $departure,
        array $excludedRoomIds = [],
        ?int $excludeTransactionId = null,
        ?int $groupBookingId = null
    ): array {
        HmsRoomType::where('business_id', $businessId)->findOrFail($roomTypeId);

        $query = HmsRoom::where('hms_room_type_id', $roomTypeId)
            ->whereHas('type', fn ($type) => $type->where('business_id', $businessId))
            ->whereNotIn('id', array_map('intval', $excludedRoomIds));
        if (Schema::hasColumn('hms_rooms', 'housekeeping_status')) {
            $query->where('housekeeping_status', '!=', 'out_of_order');
        }

        return $query->get()
            ->reject(function (HmsRoom $room) use (
                $businessId,
                $arrival,
                $departure,
                $excludeTransactionId,
                $groupBookingId
            ) {
                return $this->hasConflict(
                    $businessId,
                    (int) $room->id,
                    $arrival,
                    $departure,
                    $excludeTransactionId,
                    $groupBookingId
                );
            })
            ->pluck('room_number', 'id')
            ->toArray();
    }

    private function prepareExtras(
        int $businessId,
        array $requestedExtras,
        int $nights,
        int $guests
    ): array {
        $ids = collect($requestedExtras)
            ->filter(fn ($extra) => isset($extra['id']))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $extras = HmsExtra::where('business_id', $businessId)
            ->where('is_active', true)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        if ($extras->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'extras' => __('hms::lang.invalid_extra_selection'),
            ]);
        }

        return $ids->map(function ($id) use ($extras, $nights, $guests) {
            $extra = $extras->get($id);
            $multiplier = match ($extra->price_per) {
                'per_day' => $nights,
                'per_person' => $guests,
                'per_day_per_person' => $nights * $guests,
                default => 1,
            };

            return [
                'hms_extra_id' => (int) $extra->id,
                'price' => round((float) $extra->price * $multiplier, 4),
                'financial_classification' => $extra->financial_classification === 'refundable_security_deposit'
                    ? 'refundable_security_deposit'
                    : 'revenue',
            ];
        })->all();
    }

    private function calculateDiscount(
        int $businessId,
        Carbon $arrival,
        array $roomLines,
        float $beforeTax,
        array $financialInput
    ): array {
        $couponCode = trim((string) ($financialInput['coupon_code'] ?? ''));

        if ($couponCode !== '') {
            $coupon = HmsCoupon::where('business_id', $businessId)
                ->where('coupon_code', $couponCode)
                ->whereDate('start_date', '<=', $arrival->toDateString())
                ->whereDate('end_date', '>=', $arrival->toDateString())
                ->first();

            if (! $coupon) {
                throw ValidationException::withMessages([
                    'coupon_code' => __('hms::lang.invalid_or_expired_coupon'),
                ]);
            }

            $eligible = (float) collect($roomLines)
                ->where('hms_room_type_id', (int) $coupon->hms_room_type_id)
                ->sum('total_price');

            if ($eligible <= 0) {
                throw ValidationException::withMessages([
                    'coupon_code' => __('hms::lang.coupon_room_type_mismatch'),
                ]);
            }

            $value = strtolower((string) $coupon->discount_type) === 'fixed'
                ? (float) $coupon->discount
                : $eligible * (float) $coupon->discount / 100;

            return [
                'type' => 'fixed',
                'amount' => round(max(0, $value), 4),
                'value' => round(max(0, $value), 4),
                'coupon_id' => (int) $coupon->id,
            ];
        }

        $type = strtolower((string) ($financialInput['discount_type'] ?? ''));
        $amount = (float) ($financialInput['total_discount'] ?? 0);

        if ($amount <= 0 || ! in_array($type, ['fixed', 'percentage'], true)) {
            return ['type' => null, 'amount' => 0, 'value' => 0, 'coupon_id' => null];
        }

        if ($type === 'percentage' && $amount > 100) {
            throw ValidationException::withMessages([
                'discount_amount' => __('hms::lang.percentage_discount_limit'),
            ]);
        }

        $value = $type === 'percentage' ? $beforeTax * $amount / 100 : $amount;

        return [
            'type' => $type,
            'amount' => round($amount, 4),
            'value' => round(max(0, $value), 4),
            'coupon_id' => null,
        ];
    }

    private function assertAvailable(
        int $businessId,
        int $roomId,
        Carbon $arrival,
        Carbon $departure,
        ?int $excludeTransactionId,
        ?int $groupBookingId = null
    ): void {
        if ($this->hasConflict($businessId, $roomId, $arrival, $departure, $excludeTransactionId, $groupBookingId)) {
            throw ValidationException::withMessages([
                'rooms' => __('hms::lang.room_no_longer_available'),
            ]);
        }
    }

    private function hasConflict(
        int $businessId,
        int $roomId,
        Carbon $arrival,
        Carbon $departure,
        ?int $excludeTransactionId,
        ?int $groupBookingId = null
    ): bool {
        $lastNight = $departure->copy()->startOfDay()->subDay()->toDateString();
        $firstNight = $arrival->copy()->startOfDay()->toDateString();

        $unavailable = DB::table('hms_room_unavailables')
            ->where('hms_rooms_id', $roomId)
            ->whereDate('date_from', '<=', $lastNight)
            ->whereDate('date_to', '>=', $firstNight)
            ->exists();

        if ($unavailable) {
            return true;
        }

        $groupBlocked = false;
        if (Schema::hasTable('hms_group_room_blocks') && Schema::hasTable('hms_group_bookings')) {
            $groupBlocked = DB::table('hms_group_room_blocks as block')
                ->join('hms_group_bookings as group_booking', 'group_booking.id', '=', 'block.hms_group_booking_id')
                ->where('block.business_id', $businessId)
                ->where('block.hms_room_id', $roomId)
                ->whereIn('block.status', ['held', 'picked_up'])
                ->when($groupBookingId, fn ($query) => $query->where('block.hms_group_booking_id', '!=', $groupBookingId))
                ->where(function ($query) {
                    $query->whereNull('block.release_at')->orWhere('block.release_at', '>', now());
                })
                ->where('group_booking.arrival_at', '<', $departure->format('Y-m-d H:i:s'))
                ->where('group_booking.departure_at', '>', $arrival->format('Y-m-d H:i:s'))
                ->whereIn('group_booking.status', ['tentative', 'confirmed'])
                ->exists();
        }

        if ($groupBlocked) {
            return true;
        }

        return DB::table('hms_booking_lines as line')
            ->join('transactions as booking', 'booking.id', '=', 'line.transaction_id')
            ->where('line.hms_room_id', $roomId)
            ->where('booking.business_id', $businessId)
            ->where('booking.type', 'hms_booking')
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
            ->exists();
    }
}
