<?php

namespace Modules\Hms\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Hms\Entities\HmsCashierShift;
use Modules\Hms\Entities\HmsFolio;
use Modules\Hms\Entities\HmsFolioEntry;
use Modules\Hms\Entities\HmsNightAudit;
use Modules\Hms\Entities\HmsProperty;
use Modules\Hms\Entities\HmsRoom;
use Modules\Hms\Entities\HmsTransactionClass;

class NightAuditService
{
    public function preview(int $businessId, int $propertyId): array
    {
        $property = HmsProperty::where('business_id', $businessId)->findOrFail($propertyId);
        $businessDate = $property->business_date->toDateString();
        $bookings = HmsTransactionClass::where('business_id', $businessId)
            ->where('type', 'hms_booking')
            ->where('hms_property_id', $propertyId);

        $arrivalsNotProcessed = (clone $bookings)
            ->whereDate('hms_booking_arrival_date_time', '<=', $businessDate)
            ->whereIn('hms_booking_status', ['tentative', 'reserved'])
            ->count();
        $departuresNotProcessed = (clone $bookings)
            ->whereDate('hms_booking_departure_date_time', '<=', $businessDate)
            ->where('hms_booking_status', 'checked_in')
            ->count();
        $openCashierShifts = HmsCashierShift::where('business_id', $businessId)
            ->where('hms_property_id', $propertyId)->where('status', 'open')->count();
        $foliosWithBalance = HmsFolio::where('business_id', $businessId)
            ->where('hms_property_id', $propertyId)->where('status', 'open')->with('entries')->get()
            ->filter(fn ($folio) => abs($folio->balance) >= 0.0001)->count();

        $roomRevenue = (float) HmsFolioEntry::where('business_id', $businessId)
            ->whereHas('folio', fn ($query) => $query
                ->where('business_id', $businessId)
                ->where('hms_property_id', $propertyId))
            ->whereDate('business_date', $businessDate)
            ->whereIn('status', ['posted', 'approved'])
            ->where('category', 'room_charge')
            ->get()->sum(fn ($entry) => $entry->direction === 'debit' ? (float) $entry->base_amount : -(float) $entry->base_amount);
        $payments = (float) HmsFolioEntry::where('business_id', $businessId)
            ->whereHas('folio', fn ($query) => $query
                ->where('business_id', $businessId)
                ->where('hms_property_id', $propertyId))
            ->whereDate('business_date', $businessDate)
            ->whereIn('status', ['posted', 'approved'])
            ->whereIn('entry_type', ['payment', 'deposit'])
            ->where('direction', 'credit')->sum('base_amount');
        $roomsTotal = HmsRoom::where('hms_property_id', $propertyId)
            ->where('housekeeping_status', '!=', 'out_of_order')
            ->whereHas('type', fn ($query) => $query->where('business_id', $businessId))->count();
        $roomsOccupied = HmsRoom::where('hms_property_id', $propertyId)->where('housekeeping_status', 'occupied')->count();

        return [
            'business_date' => $businessDate,
            'summary' => [
                'room_revenue' => round($roomRevenue, 4),
                'payments' => round($payments, 4),
                'rooms_total' => $roomsTotal,
                'rooms_occupied' => $roomsOccupied,
                'occupancy_percent' => $roomsTotal ? round($roomsOccupied * 100 / $roomsTotal, 2) : 0,
                'arrivals_due' => $arrivalsNotProcessed,
                'departures_due' => $departuresNotProcessed,
                'open_folios_with_balance' => $foliosWithBalance,
            ],
            'exceptions' => [
                'arrivals_not_processed' => $arrivalsNotProcessed,
                'departures_not_processed' => $departuresNotProcessed,
                'open_cashier_shifts' => $openCashierShifts,
                'folios_with_balance' => $foliosWithBalance,
            ],
            'has_blocking_exceptions' => ($arrivalsNotProcessed + $departuresNotProcessed + $openCashierShifts) > 0,
        ];
    }

    public function close(int $businessId, int $propertyId, int $actorId, bool $override = false, ?string $reason = null): HmsNightAudit
    {
        return DB::transaction(function () use ($businessId, $propertyId, $actorId, $override, $reason) {
            $property = HmsProperty::where('business_id', $businessId)->whereKey($propertyId)->lockForUpdate()->firstOrFail();
            $preview = $this->preview($businessId, $propertyId);

            if ($preview['has_blocking_exceptions'] && ! $override) {
                throw ValidationException::withMessages(['audit' => __('hms::lang.night_audit_has_blockers')]);
            }
            if ($override && trim((string) $reason) === '') {
                throw ValidationException::withMessages(['override_reason' => __('hms::lang.override_reason_required')]);
            }

            $audit = HmsNightAudit::firstOrCreate([
                'business_id' => $businessId,
                'hms_property_id' => $propertyId,
                'business_date' => $preview['business_date'],
            ], [
                'status' => 'closed',
                'summary' => $preview['summary'],
                'exceptions' => $preview['exceptions'],
                'override_used' => $override,
                'override_reason' => $reason,
                'closed_by' => $actorId,
                'closed_at' => now(),
            ]);

            if ($audit->wasRecentlyCreated) {
                $property->business_date = $property->business_date->copy()->addDay();
                $property->save();
            }

            return $audit;
        });
    }
}
