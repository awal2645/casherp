<?php

namespace Modules\Hms\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Modules\Hms\Entities\HmsChannelConnection;
use Modules\Hms\Entities\HmsChannelMessage;

class ChannelWebhookService
{
    public function secret(HmsChannelConnection $connection): string
    {
        return Crypt::decryptString($connection->secret_encrypted);
    }

    public function ingest(HmsChannelConnection $connection, string $rawPayload, string $signature, string $timestamp, string $eventType, string $eventId): HmsChannelMessage
    {
        if (! ctype_digit($timestamp) || abs(now()->timestamp - (int) $timestamp) > 300) {
            throw ValidationException::withMessages(['timestamp' => __('hms::lang.channel_timestamp_invalid')]);
        }
        $expected = hash_hmac('sha256', $timestamp . '.' . $rawPayload, $this->secret($connection));
        if (! hash_equals($expected, strtolower($signature))) {
            throw ValidationException::withMessages(['signature' => __('hms::lang.channel_signature_invalid')]);
        }

        try {
            $payload = json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw ValidationException::withMessages(['payload' => __('hms::lang.channel_payload_invalid')]);
        }
        if (! is_array($payload)) {
            throw ValidationException::withMessages(['payload' => __('hms::lang.channel_payload_invalid')]);
        }
        $idempotencyKey = 'channel:' . $connection->id . ':' . $eventId;

        return HmsChannelMessage::firstOrCreate([
            'business_id' => $connection->business_id,
            'idempotency_key' => $idempotencyKey,
        ], [
            'hms_channel_connection_id' => $connection->id,
            'direction' => 'inbound',
            'event_type' => $eventType,
            'external_event_id' => $eventId,
            'payload' => $payload,
            'signature_valid' => true,
            'status' => 'received',
        ]);
    }
}
