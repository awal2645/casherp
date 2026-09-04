<?php

namespace Modules\Hms\Services;

use App\Restaurant\OrderFulfilment;
use App\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Hms\Entities\HmsEventBooking;
use Modules\Hms\Entities\HmsRestaurantPosting;
use Modules\Hms\Entities\HmsTransactionClass;
use Modules\Hms\Entities\HmsProperty;

class RestaurantFolioPostingService
{
    public function __construct(protected FolioService $folios)
    {
    }

    public function post(OrderFulfilment $fulfilment, int $actorId): ?HmsRestaurantPosting
    {
        if (! in_array($fulfilment->service_channel, ['room_service', 'banqueting'], true)) {
            return null;
        }
        if (! in_array($fulfilment->source_type, ['hms_booking', 'hms_event'], true) || ! $fulfilment->source_id) {
            throw ValidationException::withMessages(['source_id' => 'Select the hotel stay or event that will receive this restaurant charge.']);
        }
        app(HmsSaasService::class)->assertCapability((int) $fulfilment->business_id, 'hms_finance_operations');
        if ($fulfilment->source_type === 'hms_event') {
            app(HmsSaasService::class)->assertCapability((int) $fulfilment->business_id, 'hms_event_operations');
        }

        return DB::transaction(function () use ($fulfilment, $actorId) {
            $fulfilment = OrderFulfilment::where('business_id', $fulfilment->business_id)->whereKey($fulfilment->id)->lockForUpdate()->firstOrFail();
            $sale = Transaction::where('business_id', $fulfilment->business_id)->whereKey($fulfilment->transaction_id)->lockForUpdate()->firstOrFail();
            $existing = HmsRestaurantPosting::where('business_id', $fulfilment->business_id)
                ->where('restaurant_transaction_id', $sale->id)->first();
            if ($existing) {
                return $existing;
            }

            if ($fulfilment->source_type === 'hms_booking') {
                $source = HmsTransactionClass::where('business_id', $fulfilment->business_id)
                    ->where('type', 'hms_booking')->whereIn('hms_booking_status', ['reserved', 'checked_in'])
                    ->findOrFail($fulfilment->source_id);
                $sourceLocationId = HmsProperty::where('business_id', $fulfilment->business_id)
                    ->whereKey($source->hms_property_id)->value('location_id');
                $folio = $this->folios->ensureForBooking($source, $actorId);
            } else {
                $source = HmsEventBooking::where('business_id', $fulfilment->business_id)
                    ->whereIn('status', ['tentative', 'confirmed', 'in_progress'])->findOrFail($fulfilment->source_id);
                $sourceLocationId = $source->location_id;
                $folio = $this->folios->ensureForEvent($source, $actorId);
            }
            if ($sourceLocationId && (int) $sourceLocationId !== (int) $sale->location_id) {
                throw ValidationException::withMessages(['source_id' => 'Restaurant charges can only be posted to a stay or event in the same operating location.']);
            }
            $key = 'restaurant-sale:'.$sale->id;
            $entry = $this->folios->post($folio, [
                'entry_type' => 'charge',
                'category' => $fulfilment->service_channel === 'room_service' ? 'room_service' : 'event_catering',
                'direction' => 'debit',
                'amount' => (float) $sale->final_total,
                'description' => 'Restaurant order '.($sale->invoice_no ?: '#'.$sale->id),
                'idempotency_key' => $key,
                'metadata' => ['restaurant_transaction_id' => $sale->id, 'service_channel' => $fulfilment->service_channel],
            ], $actorId);

            return HmsRestaurantPosting::create([
                'business_id' => $fulfilment->business_id,
                'restaurant_transaction_id' => $sale->id,
                'source_type' => $fulfilment->source_type,
                'source_id' => $fulfilment->source_id,
                'hms_folio_id' => $folio->id,
                'hms_folio_entry_id' => $entry->id,
                'amount' => $sale->final_total,
                'status' => 'posted',
                'idempotency_key' => $key,
                'posted_by' => $actorId,
                'posted_at' => now(),
            ]);
        });
    }
}
