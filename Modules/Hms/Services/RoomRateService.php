<?php

namespace Modules\Hms\Services;

use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Hms\Entities\HmsRoomTypePricing;

class RoomRateService
{
    /**
     * Return the server-authoritative rate for every occupied night.
     */
    public function quote(
        int $roomTypeId,
        Carbon $arrival,
        Carbon $departure,
        int $adults,
        int $children
    ): array {
        $start = $arrival->copy()->startOfDay();
        $end = $departure->copy()->startOfDay();

        if ($end->lessThanOrEqualTo($start)) {
            throw ValidationException::withMessages([
                'departure_date' => __('hms::lang.departure_after_arrival'),
            ]);
        }

        $occupancyPricing = HmsRoomTypePricing::where('hms_room_type_id', $roomTypeId)
            ->where('adults', $adults)
            ->where('childrens', $children)
            ->first();

        $defaultPricing = HmsRoomTypePricing::where('hms_room_type_id', $roomTypeId)
            ->whereNull('adults')
            ->whereNull('childrens')
            ->first();

        if (! $occupancyPricing && ! $defaultPricing) {
            throw ValidationException::withMessages([
                'rooms' => __('hms::lang.room_rate_missing'),
            ]);
        }

        $nightly = [];
        $cursor = $start->copy();

        while ($cursor->lessThan($end)) {
            $column = 'price_'.strtolower($cursor->format('l'));
            $amount = $this->resolveAmount($occupancyPricing, $defaultPricing, $column);

            if ($amount === null || ! is_numeric($amount) || (float) $amount < 0) {
                throw ValidationException::withMessages([
                    'rooms' => __('hms::lang.room_rate_missing_for_date', [
                        'date' => $cursor->toDateString(),
                    ]),
                ]);
            }

            $nightly[] = [
                'date' => $cursor->toDateString(),
                'amount' => round((float) $amount, 4),
            ];
            $cursor->addDay();
        }

        $total = round((float) collect($nightly)->sum('amount'), 4);
        $nights = count($nightly);

        return [
            'nights' => $nights,
            'nightly' => $nightly,
            'average_rate' => $nights > 0 ? round($total / $nights, 4) : 0,
            'total' => $total,
        ];
    }

    private function resolveAmount($occupancyPricing, $defaultPricing, string $column)
    {
        if ($occupancyPricing && $occupancyPricing->{$column} !== null) {
            return $occupancyPricing->{$column};
        }

        if ($occupancyPricing && $occupancyPricing->default_price_per_night !== null) {
            return $occupancyPricing->default_price_per_night;
        }

        if ($defaultPricing && $defaultPricing->{$column} !== null) {
            return $defaultPricing->{$column};
        }

        return $defaultPricing ? $defaultPricing->default_price_per_night : null;
    }
}
