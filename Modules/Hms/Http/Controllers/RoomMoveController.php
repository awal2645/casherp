<?php

namespace Modules\Hms\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Hms\Entities\HmsBookingLine;
use Modules\Hms\Http\Controllers\Concerns\AuthorizesHmsRequests;
use Modules\Hms\Services\HmsContextService;
use Modules\Hms\Services\RoomMoveService;

class RoomMoveController extends Controller
{
    use AuthorizesHmsRequests;

    public function store(Request $request, int $line, HmsContextService $context, RoomMoveService $moves)
    {
        $businessId = $this->authorizeHms('hms.manage_room_moves');
        $bookingLine = HmsBookingLine::with('transaction')->findOrFail($line);
        abort_unless($bookingLine->transaction && (int) $bookingLine->transaction->business_id === $businessId, 404);
        $context->property($businessId, (int) $bookingLine->transaction->hms_property_id);
        $data = $request->validate([
            'to_room_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $moves->move($businessId, $line, (int) $data['to_room_id'], $data['reason'], (int) $request->user()->id, $data['notes'] ?? null);

        return back()->with('status', ['success' => 1, 'msg' => 'Room moved and the audit history recorded. Existing rates were retained.']);
    }
}
