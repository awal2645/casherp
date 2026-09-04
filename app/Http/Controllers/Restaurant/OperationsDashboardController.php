<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Restaurant\BookingDetail;
use App\Restaurant\KitchenTicket;
use App\Restaurant\OrderFulfilment;
use App\Restaurant\RegisterReconciliation;
use App\Restaurant\WaiterRequest;
use App\Services\RestaurantContextService;
use Illuminate\Http\Request;

class OperationsDashboardController extends Controller
{
    public function __invoke(Request $request, RestaurantContextService $context)
    {
        $this->authorizeAny(['restaurant.dashboard.view', 'sell.view']);
        $businessId = $context->businessId();
        $locationIds = $context->permittedLocationIds($businessId);
        $selectedLocation = $request->integer('location_id') ?: null;
        if ($selectedLocation) {
            $context->assertLocation($businessId, $selectedLocation, $request->user());
            $locationIds = [$selectedLocation];
        }

        $scope = fn ($query) => $query->where('business_id', $businessId)
            ->when($locationIds !== null, fn ($q) => $q->whereIn('location_id', $locationIds));

        $metrics = [
            'active_kitchen_tickets' => $scope(KitchenTicket::query())->whereIn('status', ['queued', 'accepted', 'preparing', 'ready'])->count(),
            'overdue_kitchen_tickets' => $scope(KitchenTicket::query())->whereIn('status', ['queued', 'accepted', 'preparing'])->where('fired_at', '<=', now()->subMinutes(15))->count(),
            'active_orders' => $scope(OrderFulfilment::query())->whereNotIn('status', ['completed', 'cancelled'])->count(),
            'today_reservations' => $scope(BookingDetail::query())
                ->join('bookings', 'bookings.id', '=', 'restaurant_booking_details.booking_id')
                ->whereDate('bookings.booking_start', today())
                ->whereNotIn('restaurant_booking_details.status', ['cancelled', 'no_show'])->count(),
            'open_waiter_requests' => $scope(WaiterRequest::query())->whereIn('status', ['open', 'acknowledged'])->count(),
            'registers_pending_approval' => $scope(RegisterReconciliation::query())->where('status', 'pending_approval')->count(),
        ];

        $locations = \App\BusinessLocation::forDropdown($businessId);

        return view('restaurant.operations.index', compact('metrics', 'locations', 'selectedLocation'));
    }

    private function authorizeAny(array $permissions): void
    {
        if (! collect($permissions)->contains(fn ($permission) => auth()->user()->can($permission))) {
            abort(403, 'Unauthorized action.');
        }
    }
}
