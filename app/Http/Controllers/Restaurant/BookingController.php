<?php

namespace App\Http\Controllers\Restaurant;

use App\BusinessLocation;
use App\Contact;
use App\CustomerGroup;
use App\Restaurant\Booking;
use App\Restaurant\BookingDetail;
use App\Restaurant\ResTable;
use App\Services\ReservationAvailabilityService;
use App\User;
use App\Utils\RestaurantUtil;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class BookingController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $commonUtil;

    protected $restUtil;

    public function __construct(Util $commonUtil, RestaurantUtil $restUtil)
    {
        $this->commonUtil = $commonUtil;
        $this->restUtil = $restUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (! auth()->user()->can('restaurant.reservations.view') && ! auth()->user()->can('restaurant.reservations.manage') && ! auth()->user()->can('crud_all_bookings') && ! auth()->user()->can('crud_own_bookings')) {
            abort(403, 'Unauthorized action.');
        }
        $business_id = request()->session()->get('user.business_id');

        $user_id = request()->has('user_id') ? request()->user_id : null;
        if (! auth()->user()->can('restaurant.reservations.manage') && ! auth()->user()->hasPermissionTo('crud_all_bookings') && ! $this->restUtil->is_admin(auth()->user(), $business_id)) {
            $user_id = request()->session()->get('user.id');
        }
        if (request()->ajax()) {
            $filters = [
                'start_date' => request()->start,
                'end_date' => request()->end,
                'user_id' => $user_id,
                'location_id' => ! empty(request()->location_id) ? request()->location_id : null,
                'business_id' => $business_id,
            ];

            $events = $this->restUtil->getBookingsForCalendar($filters);

            return $events;
        }

        $business_locations = BusinessLocation::forDropdown($business_id);

        $customers = Contact::customersDropdown($business_id, false);

        $correspondents = User::forDropdown($business_id, false);

        $types = Contact::getContactTypes();
        $customer_groups = CustomerGroup::forDropdown($business_id);

        return view('restaurant.booking.index', compact('business_locations', 'customers', 'correspondents', 'types', 'customer_groups'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (! auth()->user()->can('restaurant.reservations.manage') && ! auth()->user()->can('crud_all_bookings') && ! auth()->user()->can('crud_own_bookings')) {
            abort(403, 'Unauthorized action.');
        }
        try {
            if ($request->ajax()) {
                $business_id = request()->session()->get('user.business_id');
                $user_id = request()->session()->get('user.id');
                $input = $request->validate([
                    'location_id' => ['required', 'integer'],
                    'contact_id' => ['required', 'integer'],
                    'res_waiter_id' => ['nullable', 'integer'],
                    'res_table_id' => ['nullable', 'integer'],
                    'correspondent' => ['nullable', 'integer'],
                    'booking_start' => ['required', 'string'],
                    'booking_end' => ['required', 'string'],
                    'booking_note' => ['nullable', 'string', 'max:4000'],
                    'party_size' => ['nullable', 'integer', 'min:1', 'max:10000'],
                    'reservation_status' => ['nullable', Rule::in(array_keys(config('restaurant_operations.reservation_statuses', [])))],
                    'reservation_source' => ['nullable', Rule::in(['staff', 'phone', 'walk_in', 'website', 'partner', 'qr'])],
                    'expected_spend' => ['nullable', 'numeric', 'min:0'],
                    'payment_deposit_amount' => ['nullable', 'numeric', 'min:0'],
                    'send_notification' => ['nullable', 'boolean'],
                ]);
                $booking_start = $this->commonUtil->uf_date($input['booking_start'], true);
                $booking_end = $this->commonUtil->uf_date($input['booking_end'], true);
                $location = BusinessLocation::where('business_id', $business_id)->whereKey($input['location_id'])->firstOrFail();
                $permitted = $request->user()->permitted_locations($business_id);
                if ($permitted !== 'all' && ! in_array((int) $location->id, array_map('intval', (array) $permitted), true)) {
                    abort(403, 'Unauthorized location.');
                }
                Contact::where('business_id', $business_id)->whereKey($input['contact_id'])->firstOrFail();
                if (! empty($input['res_table_id'])) {
                    ResTable::where('business_id', $business_id)->where('location_id', $input['location_id'])->whereKey($input['res_table_id'])->firstOrFail();
                }
                foreach (['res_waiter_id', 'correspondent'] as $userField) {
                    if (! empty($input[$userField])) {
                        User::where('business_id', $business_id)->whereKey($input[$userField])->firstOrFail();
                    }
                }

                app(ReservationAvailabilityService::class)->assertAvailable(
                    (int) $business_id,
                    (int) $input['location_id'],
                    ! empty($input['res_table_id']) ? (int) $input['res_table_id'] : null,
                    \Carbon\Carbon::parse($booking_start),
                    \Carbon\Carbon::parse($booking_end)
                );

                $booking = DB::transaction(function () use ($input, $business_id, $user_id, $booking_start, $booking_end) {
                    $input['business_id'] = $business_id;
                    $input['created_by'] = $user_id;
                    $input['booking_start'] = $booking_start;
                    $input['booking_end'] = $booking_end;
                    $input['booking_status'] = 'booked';
                    $input['booking_note'] = $input['booking_note'] ?? null;
                    $booking = Booking::createBooking($input);
                    BookingDetail::create([
                        'business_id' => $business_id,
                        'location_id' => $input['location_id'],
                        'booking_id' => $booking->id,
                        'public_reference' => (string) Str::uuid(),
                        'party_size' => $input['party_size'] ?? 1,
                        'source' => $input['reservation_source'] ?? 'staff',
                        'status' => $input['reservation_status'] ?? 'confirmed',
                        'expected_spend' => $input['expected_spend'] ?? 0,
                        'payment_deposit_amount' => $input['payment_deposit_amount'] ?? 0,
                        'special_requests' => $input['booking_note'] ?? null,
                    ]);

                    return $booking;
                });

                $output = ['success' => 1, 'msg' => trans('lang_v1.added_success')];
                if (! empty($input['send_notification'])) {
                    $output['send_notification'] = 1;
                    $output['notification_url'] = action([\App\Http\Controllers\NotificationController::class, 'getTemplate'], ['transaction_id' => $booking->id, 'template_for' => 'new_booking']);
                }
            } else {
                exit(__('messages.something_went_wrong'));
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Display the specified resource.
     *
     * @param  \int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if (! auth()->user()->can('restaurant.reservations.view') && ! auth()->user()->can('restaurant.reservations.manage') && ! auth()->user()->can('crud_all_bookings') && ! auth()->user()->can('crud_own_bookings')) {
            abort(403, 'Unauthorized action.');
        }
        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $bookingQuery = Booking::where('business_id', $business_id);
            if (! auth()->user()->can('restaurant.reservations.manage') && ! auth()->user()->can('crud_all_bookings') && ! $this->restUtil->is_admin(auth()->user(), $business_id)) {
                $bookingQuery->where(function ($query) {
                    $query->where('created_by', request()->user()->id)
                        ->orWhere('correspondent_id', request()->user()->id)
                        ->orWhere('waiter_id', request()->user()->id);
                });
            }
            $booking = $bookingQuery
                                ->where('id', $id)
                                ->with(['table', 'customer', 'correspondent', 'waiter', 'location', 'detail'])
                                ->first();
            if (! empty($booking)) {
                $booking_start = $this->commonUtil->format_date($booking->booking_start, true);
                $booking_end = $this->commonUtil->format_date($booking->booking_end, true);

                $booking_statuses = [
                    'waiting' => __('lang_v1.waiting'),
                    'booked' => __('restaurant.booked'),
                    'completed' => __('restaurant.completed'),
                    'cancelled' => __('restaurant.cancelled'),
                ];

                return view('restaurant.booking.show', compact('booking', 'booking_start', 'booking_end', 'booking_statuses'));
            }
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Booking  $booking
     * @return \Illuminate\Http\Response
     */
    public function edit(Booking $booking)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Booking  $booking
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (! auth()->user()->can('restaurant.reservations.manage') && ! auth()->user()->can('crud_all_bookings') && ! auth()->user()->can('crud_own_bookings')) {
            abort(403, 'Unauthorized action.');
        }
        try {
            $business_id = $request->session()->get('user.business_id');
            $allowedStatuses = ['waiting', 'booked', 'completed', 'cancelled'];
            $request->validate(['booking_status' => ['required', Rule::in($allowedStatuses)]]);
            $bookingQuery = Booking::where('business_id', $business_id);
            if (! auth()->user()->can('restaurant.reservations.manage') && ! auth()->user()->can('crud_all_bookings') && ! $this->restUtil->is_admin(auth()->user(), $business_id)) {
                $bookingQuery->where(function ($query) use ($request) {
                    $query->where('created_by', $request->user()->id)
                        ->orWhere('correspondent_id', $request->user()->id)
                        ->orWhere('waiter_id', $request->user()->id);
                });
            }
            $booking = $bookingQuery->find($id);
            if (! empty($booking)) {
                $booking->booking_status = $request->booking_status;
                $booking->save();
                $detailStatus = ['waiting' => 'waitlisted', 'booked' => 'confirmed', 'completed' => 'completed', 'cancelled' => 'cancelled'][$request->booking_status];
                $detail = BookingDetail::where('business_id', $business_id)->where('booking_id', $booking->id)->first();
                if ($detail) {
                    $detail->status = $detailStatus;
                    if ($detailStatus === 'completed') {
                        $detail->completed_at = now();
                    }
                    $detail->save();
                }
            } else {
                abort(404);
            }

            $output = ['success' => 1,
                'msg' => trans('lang_v1.updated_success'),
            ];
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Booking  $booking
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (! auth()->user()->can('restaurant.reservations.manage') && ! auth()->user()->can('crud_all_bookings') && ! auth()->user()->can('crud_own_bookings')) {
            abort(403, 'Unauthorized action.');
        }
        try {
            $business_id = request()->session()->get('user.business_id');
            $query = Booking::where('business_id', $business_id)->where('id', $id);
            if (! auth()->user()->can('restaurant.reservations.manage') && ! auth()->user()->can('crud_all_bookings') && ! $this->restUtil->is_admin(auth()->user(), $business_id)) {
                $query->where('created_by', request()->user()->id);
            }
            $booking = $query->firstOrFail();
            BookingDetail::where('business_id', $business_id)->where('booking_id', $booking->id)->delete();
            $booking->delete();
            $output = ['success' => 1,
                'msg' => trans('lang_v1.deleted_success'),
            ];
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Retrieves todays bookings
     *
     * @param  \App\Booking  $booking
     * @return \Illuminate\Http\Response
     */
    public function getTodaysBookings()
    {
        if (! auth()->user()->can('restaurant.reservations.view') && ! auth()->user()->can('restaurant.reservations.manage') && ! auth()->user()->can('crud_all_bookings') && ! auth()->user()->can('crud_own_bookings')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $user_id = request()->session()->get('user.id');
            $today = \Carbon::now()->format('Y-m-d');
            $query = Booking::where('business_id', $business_id)
                        ->where('booking_status', 'booked')
                        ->whereDate('booking_start', $today)
                        ->with(['table', 'customer', 'correspondent', 'waiter', 'location']);

            if (! empty(request()->location_id)) {
                $query->where('location_id', request()->location_id);
            }

            if (! auth()->user()->can('restaurant.reservations.manage') && ! auth()->user()->hasPermissionTo('crud_all_bookings') && ! $this->commonUtil->is_admin(auth()->user(), $business_id)) {
                $query->where(function ($query) use ($user_id) {
                    $query->where('created_by', $user_id)
                        ->orWhere('correspondent_id', $user_id)
                        ->orWhere('waiter_id', $user_id);
                });

                //$query->where('created_by', $user_id);
            }

            return Datatables::of($query)
                ->editColumn('table', function ($row) {
                    return ! empty($row->table->name) ? $row->table->name : '--';
                })
                ->editColumn('customer', function ($row) {
                    return ! empty($row->customer->name) ? $row->customer->name : '--';
                })
                ->editColumn('correspondent', function ($row) {
                    return ! empty($row->correspondent->user_full_name) ? $row->correspondent->user_full_name : '--';
                })
                ->editColumn('waiter', function ($row) {
                    return ! empty($row->waiter->user_full_name) ? $row->waiter->user_full_name : '--';
                })
                ->editColumn('location', function ($row) {
                    return ! empty($row->location->name) ? $row->location->name : '--';
                })
                ->editColumn('booking_start', function ($row) {
                    return $this->commonUtil->format_date($row->booking_start, true);
                })
                ->editColumn('booking_end', function ($row) {
                    return $this->commonUtil->format_date($row->booking_end, true);
                })
               ->removeColumn('id')
                ->make(true);
        }
    }
}
