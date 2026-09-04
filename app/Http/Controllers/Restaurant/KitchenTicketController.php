<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Restaurant\KitchenTicket;
use App\Services\KitchenRoutingService;
use App\Services\RestaurantContextService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KitchenTicketController extends Controller
{
    public function index(Request $request, RestaurantContextService $context)
    {
        $this->authorizeView();
        $businessId = $context->businessId();
        $locationIds = $context->permittedLocationIds($businessId);
        $tickets = KitchenTicket::where('business_id', $businessId)
            ->when($locationIds !== null, fn ($q) => $q->whereIn('location_id', $locationIds))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->with(['station', 'items'])
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'normal', 'low')")
            ->orderBy('fired_at')
            ->paginate(50);

        return view('restaurant.operations.kitchen_board', compact('tickets'));
    }

    public function transition(Request $request, int $ticket, RestaurantContextService $context, KitchenRoutingService $routing)
    {
        if (! auth()->user()->can('restaurant.kitchen.manage')) {
            abort(403, 'Unauthorized action.');
        }
        $businessId = $context->businessId();
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(config('restaurant_operations.kitchen_statuses', [])))],
            'cancellation_reason' => ['nullable', 'required_if:status,cancelled', 'string', 'max:2000'],
        ]);
        $scoped = KitchenTicket::where('business_id', $businessId)->whereKey($ticket)->firstOrFail();
        $context->assertLocation($businessId, (int) $scoped->location_id, $request->user());
        if ($data['status'] === 'cancelled') {
            $scoped->cancellation_reason = $data['cancellation_reason'];
            $scoped->save();
        }
        $routing->transition($businessId, $ticket, $data['status'], (int) $request->user()->id);

        return back()->with('status', ['success' => 1, 'msg' => 'Kitchen ticket updated.']);
    }

    private function authorizeView(): void
    {
        if (! auth()->user()->can('restaurant.kitchen.view') && ! auth()->user()->can('sell.view')) {
            abort(403, 'Unauthorized action.');
        }
    }
}
