<?php

namespace App\Services;

use App\Restaurant\OrderEvent;
use App\Restaurant\OrderFulfilment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use App\Restaurant\RestaurantOperationSetting;

class RestaurantFulfilmentService
{
    public function transition(int $businessId, int $fulfilmentId, string $toStatus, int $actorId, array $payload = []): OrderFulfilment
    {
        return DB::transaction(function () use ($businessId, $fulfilmentId, $toStatus, $actorId, $payload) {
            $fulfilment = OrderFulfilment::where('business_id', $businessId)->whereKey($fulfilmentId)->lockForUpdate()->firstOrFail();
            $allowed = (array) config('restaurant_operations.fulfilment_transitions.'.$fulfilment->status, []);
            if (! in_array($toStatus, $allowed, true)) {
                throw ValidationException::withMessages(['status' => "Order cannot move from {$fulfilment->status} to {$toStatus}."]);
            }

            $channel = $payload['service_channel'] ?? $fulfilment->service_channel;
            if (! array_key_exists($channel, config('restaurant_operations.service_channels', []))) {
                throw ValidationException::withMessages(['service_channel' => 'Select a supported service channel.']);
            }
            $settings = RestaurantOperationSetting::where('business_id', $businessId)->where('is_active', true)->first();
            if ($settings && ! in_array($channel, (array) $settings->enabled_channels, true)) {
                throw ValidationException::withMessages(['service_channel' => 'This service channel is disabled in the active company settings.']);
            }
            $sourceType = $payload['source_type'] ?? $fulfilment->source_type;
            $sourceId = $payload['source_id'] ?? $fulfilment->source_id;
            if ($sourceType && ! in_array($sourceType, ['hms_booking', 'hms_event'], true)) {
                throw ValidationException::withMessages(['source_type' => 'Select a supported hotel or event source.']);
            }
            if ($channel === 'room_service' && $sourceType && $sourceType !== 'hms_booking') {
                throw ValidationException::withMessages(['source_type' => 'Room service must be linked to an active hotel stay.']);
            }
            if ($channel === 'banqueting' && $sourceType && $sourceType !== 'hms_event') {
                throw ValidationException::withMessages(['source_type' => 'Banqueting must be linked to an active event booking.']);
            }
            if ($channel === 'room_service' && empty($payload['room_reference']) && empty($fulfilment->room_reference) && ! ($sourceType === 'hms_booking' && $sourceId)) {
                throw ValidationException::withMessages(['room_reference' => 'Select the hotel stay or enter a room reference for room service.']);
            }

            $from = $fulfilment->status;
            $fulfilment->fill(array_filter([
                'service_channel' => $channel,
                'room_reference' => $payload['room_reference'] ?? null,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'promised_at' => $payload['promised_at'] ?? null,
                'instructions' => $payload['instructions'] ?? null,
                'delivery_address' => $payload['delivery_address'] ?? null,
            ], fn ($value) => $value !== null));
            if (! in_array($channel, ['room_service', 'banqueting'], true)) {
                $fulfilment->source_type = null;
                $fulfilment->source_id = null;
                $fulfilment->room_reference = null;
            }
            $fulfilment->status = $toStatus;
            $timestamp = [
                'confirmed' => 'confirmed_at',
                'ready' => 'ready_at',
                'dispatched' => 'dispatched_at',
                'completed' => 'completed_at',
                'delivered' => 'completed_at',
                'served' => 'completed_at',
            ][$toStatus] ?? null;
            if ($timestamp) {
                $fulfilment->{$timestamp} = now();
            }
            $fulfilment->save();

            OrderEvent::create([
                'business_id' => $businessId,
                'transaction_id' => $fulfilment->transaction_id,
                'event_type' => 'fulfilment_status_changed',
                'from_status' => $from,
                'to_status' => $toStatus,
                'payload' => $payload,
                'actor_id' => $actorId,
                'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            ]);

            if (in_array($toStatus, ['served', 'delivered', 'completed'], true)
                && in_array($channel, ['room_service', 'banqueting'], true)
                && Schema::hasTable('hms_restaurant_postings')
                && class_exists(\Modules\Hms\Services\RestaurantFolioPostingService::class)) {
                app(\Modules\Hms\Services\RestaurantFolioPostingService::class)->post($fulfilment, $actorId);
            }

            return $fulfilment->fresh();
        });
    }
}
