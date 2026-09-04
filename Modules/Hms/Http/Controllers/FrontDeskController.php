<?php

namespace Modules\Hms\Http\Controllers;

use App\Utils\ModuleUtil;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Hms\Entities\HmsProperty;
use Modules\Hms\Entities\HmsRoom;
use Modules\Hms\Entities\HmsTransactionClass;
use Modules\Hms\Services\BookingLifecycleService;

class FrontDeskController extends Controller
{
    public function __construct(protected ModuleUtil $moduleUtil)
    {
    }

    public function index(Request $request)
    {
        $businessId = (int) $request->session()->get('user.business_id');
        $this->authorizeFrontDesk($businessId, false);

        $properties = HmsProperty::where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id');
        $propertyId = $request->filled('property_id') ? (int) $request->input('property_id') : null;
        if ($propertyId) {
            HmsProperty::where('business_id', $businessId)->where('is_active', true)->findOrFail($propertyId);
        }

        $start = now()->startOfDay();
        $end = now()->endOfDay();

        $arrivals = $this->bookingQuery($businessId, $propertyId)
            ->where(function ($query) {
                $query->where('hms_booking_status', 'reserved')
                    ->orWhere(function ($tentative) {
                        $tentative->where('hms_booking_status', 'tentative')
                            ->where(function ($hold) {
                                $hold->whereNull('hms_hold_expires_at')
                                    ->orWhere('hms_hold_expires_at', '>', now());
                            });
                    });
            })
            ->whereBetween('hms_booking_arrival_date_time', [$start, $end])
            ->whereNull('check_in')
            ->orderBy('hms_booking_arrival_date_time')
            ->get();

        $inHouse = $this->bookingQuery($businessId, $propertyId)
            ->where('hms_booking_status', 'checked_in')
            ->whereNull('check_out')
            ->orderBy('hms_booking_departure_date_time')
            ->get();

        $departures = $this->bookingQuery($businessId, $propertyId)
            ->where('hms_booking_status', 'checked_in')
            ->whereBetween('hms_booking_departure_date_time', [$start, $end])
            ->whereNull('check_out')
            ->orderBy('hms_booking_departure_date_time')
            ->get();

        $noShowCandidates = $this->bookingQuery($businessId, $propertyId)
            ->where(function ($query) {
                $query->where('hms_booking_status', 'reserved')
                    ->orWhere(function ($tentative) {
                        $tentative->where('hms_booking_status', 'tentative')
                            ->where(function ($hold) {
                                $hold->whereNull('hms_hold_expires_at')
                                    ->orWhere('hms_hold_expires_at', '>', now());
                            });
                    });
            })
            ->where('hms_booking_arrival_date_time', '<', now())
            ->whereNull('check_in')
            ->orderBy('hms_booking_arrival_date_time')
            ->limit(50)
            ->get();

        $roomCounts = HmsRoom::leftJoin(
            'hms_room_types as room_type',
            'room_type.id',
            '=',
            'hms_rooms.hms_room_type_id'
        )
            ->where('room_type.business_id', $businessId)
            ->when($propertyId, fn ($query) => $query->where('hms_rooms.hms_property_id', $propertyId))
            ->selectRaw('COUNT(hms_rooms.id) as total')
            ->selectRaw("SUM(CASE WHEN hms_rooms.housekeeping_status = 'ready' THEN 1 ELSE 0 END) as ready")
            ->selectRaw("SUM(CASE WHEN hms_rooms.housekeeping_status = 'dirty' THEN 1 ELSE 0 END) as dirty")
            ->selectRaw("SUM(CASE WHEN hms_rooms.housekeeping_status = 'cleaning' THEN 1 ELSE 0 END) as cleaning")
            ->selectRaw("SUM(CASE WHEN hms_rooms.housekeeping_status = 'out_of_order' THEN 1 ELSE 0 END) as out_of_order")
            ->first();

        $sellableRooms = max(0, (int) ($roomCounts->total ?? 0) - (int) ($roomCounts->out_of_order ?? 0));

        $occupiedRooms = DB::table('hms_booking_lines as line')
            ->join('transactions as booking', 'booking.id', '=', 'line.transaction_id')
            ->where('booking.business_id', $businessId)
            ->where('booking.type', 'hms_booking')
            ->when($propertyId, fn ($query) => $query->where('booking.hms_property_id', $propertyId))
            ->where('booking.hms_booking_status', 'checked_in')
            ->whereNull('booking.check_out')
            ->distinct()
            ->count('line.hms_room_id');

        $holdsExpiring = $this->bookingQuery($businessId, $propertyId)
            ->where('hms_booking_status', 'tentative')
            ->whereBetween('hms_hold_expires_at', [now(), now()->addDay()])
            ->count();

        $overdueDepartures = $this->bookingQuery($businessId, $propertyId)
            ->where('hms_booking_status', 'checked_in')
            ->where('hms_booking_departure_date_time', '<', now())
            ->whereNull('check_out')
            ->count();

        $performance = $this->rollingPerformance($businessId, $sellableRooms, $propertyId);

        $stats = [
            'arrivals' => $arrivals->count(),
            'departures' => $departures->count(),
            'in_house' => $inHouse->count(),
            'occupied_rooms' => $occupiedRooms,
            'total_rooms' => $sellableRooms,
            'out_of_order_rooms' => (int) ($roomCounts->out_of_order ?? 0),
            'ready_rooms' => (int) ($roomCounts->ready ?? 0),
            'dirty_rooms' => (int) ($roomCounts->dirty ?? 0),
            'cleaning_rooms' => (int) ($roomCounts->cleaning ?? 0),
            'holds_expiring' => $holdsExpiring,
            'overdue_departures' => $overdueDepartures,
            'occupancy' => $sellableRooms > 0
                ? round($occupiedRooms * 100 / $sellableRooms, 1)
                : 0,
        ] + $performance;

        return view('hms::front_desk.index', compact(
            'arrivals',
            'inHouse',
            'departures',
            'noShowCandidates',
            'stats',
            'properties',
            'propertyId'
        ));
    }

    public function checkIn(
        Request $request,
        int $booking,
        BookingLifecycleService $lifecycle
    ) {
        $businessId = (int) $request->session()->get('user.business_id');
        $this->authorizeFrontDesk($businessId, true);
        $validated = $request->validate([
            'occurred_at' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
        ]);

        $lifecycle->checkIn(
            $businessId,
            $booking,
            isset($validated['occurred_at']) ? Carbon::parse($validated['occurred_at']) : now(),
            (int) auth()->id(),
            $validated['notes'] ?? null
        );

        return $this->successResponse();
    }

    public function checkOut(
        Request $request,
        int $booking,
        BookingLifecycleService $lifecycle
    ) {
        $businessId = (int) $request->session()->get('user.business_id');
        $this->authorizeFrontDesk($businessId, true);
        $validated = $request->validate([
            'occurred_at' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
        ]);

        $lifecycle->checkOut(
            $businessId,
            $booking,
            isset($validated['occurred_at']) ? Carbon::parse($validated['occurred_at']) : now(),
            (int) auth()->id(),
            $validated['notes'] ?? null
        );

        return $this->successResponse();
    }

    public function cancel(
        Request $request,
        int $booking,
        BookingLifecycleService $lifecycle
    ) {
        $businessId = (int) $request->session()->get('user.business_id');
        $this->authorizeFrontDesk($businessId, true);
        $validated = $request->validate([
            'reason' => 'required|string|min:3|max:2000',
        ]);

        $lifecycle->cancel($businessId, $booking, $validated['reason'], (int) auth()->id());

        return $this->successResponse();
    }

    public function noShow(
        Request $request,
        int $booking,
        BookingLifecycleService $lifecycle
    ) {
        $businessId = (int) $request->session()->get('user.business_id');
        $this->authorizeFrontDesk($businessId, true);
        $validated = $request->validate([
            'reason' => 'required|string|min:3|max:2000',
        ]);

        $lifecycle->markNoShow($businessId, $booking, $validated['reason'], (int) auth()->id());

        return $this->successResponse();
    }

    private function bookingQuery(int $businessId, ?int $propertyId = null)
    {
        return HmsTransactionClass::where('business_id', $businessId)
            ->where('type', 'hms_booking')
            ->when($propertyId, fn ($query) => $query->where('hms_property_id', $propertyId))
            ->with(['contact', 'hms_property', 'hms_booking_lines.room.type']);
    }

    private function authorizeFrontDesk(int $businessId, bool $manage): void
    {
        if (! (auth()->user()->can('superadmin')
            || $this->moduleUtil->hasThePermissionInSubscription($businessId, 'hms_module'))) {
            abort(403, 'Unauthorized action.');
        }

        $permission = $manage ? 'hms.manage_front_desk' : 'hms.front_desk';
        if (! (auth()->user()->can('superadmin')
            || auth()->user()->can('hms.manage_front_desk')
            || auth()->user()->can($permission))) {
            abort(403, 'Unauthorized action.');
        }
    }

    private function rollingPerformance(int $businessId, int $totalRooms, ?int $propertyId = null): array
    {
        $windowStart = now()->subDays(30)->startOfDay();
        $completed = DB::table('hms_booking_lines as line')
            ->join('transactions as booking', 'booking.id', '=', 'line.transaction_id')
            ->where('booking.business_id', $businessId)
            ->where('booking.type', 'hms_booking')
            ->when($propertyId, fn ($query) => $query->where('booking.hms_property_id', $propertyId))
            ->where('booking.hms_booking_status', 'checked_out')
            ->whereBetween('booking.check_out', [$windowStart, now()])
            ->get([
                'line.total_price',
                'booking.hms_booking_arrival_date_time',
                'booking.hms_booking_departure_date_time',
            ]);

        $roomRevenue = round((float) $completed->sum('total_price'), 4);
        $soldRoomNights = $completed->sum(function ($line) {
            return max(1, Carbon::parse($line->hms_booking_arrival_date_time)
                ->startOfDay()
                ->diffInDays(Carbon::parse($line->hms_booking_departure_date_time)->startOfDay()));
        });

        return [
            'adr_30' => $soldRoomNights > 0 ? round($roomRevenue / $soldRoomNights, 2) : 0,
            'revpar_30' => $totalRooms > 0 ? round($roomRevenue / ($totalRooms * 30), 2) : 0,
        ];
    }

    private function successResponse()
    {
        return back()->with('status', [
            'success' => 1,
            'msg' => __('lang_v1.success'),
        ]);
    }
}
