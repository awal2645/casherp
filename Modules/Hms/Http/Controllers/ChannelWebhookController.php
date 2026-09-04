<?php

namespace Modules\Hms\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Hms\Entities\HmsChannelConnection;
use Modules\Hms\Services\ChannelWebhookService;
use Modules\Hms\Services\HmsSaasService;

class ChannelWebhookController extends Controller
{
    public function ingest(Request $request, string $publicKey, ChannelWebhookService $service)
    {
        $request->validate([
            'event_type' => 'required|string|max:60',
            'event_id' => 'required|string|max:160',
        ]);
        $connection = HmsChannelConnection::where('public_key', $publicKey)->where('status', 'active')->firstOrFail();
        app(HmsSaasService::class)->assertCapability((int) $connection->business_id, 'hms_channel_manager');
        $message = $service->ingest(
            $connection,
            $request->getContent(),
            (string) $request->header('X-HMS-Signature'),
            (string) $request->header('X-HMS-Timestamp'),
            (string) $request->input('event_type'),
            (string) $request->input('event_id')
        );

        return response()->json(['accepted' => true, 'message_id' => $message->id], 202);
    }
}
