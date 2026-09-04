<?php

namespace Modules\Hms\Http\Controllers;

use App\Account;
use App\Business;
use App\Contact;
use App\CustomerGroup;
use App\NotificationTemplate;
use App\TaxRate;
use App\Transaction;
use App\Media;
use App\User;
use App\Utils\BusinessUtil;
use App\Utils\ContactUtil;
use App\Utils\ModuleUtil;
use App\Utils\NotificationUtil;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use App\Utils\Util;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Hms\Entities\HmsBookingExtra;
use Modules\Hms\Entities\HmsBookingLine;
use Modules\Hms\Entities\HmsExtra;
use Modules\Hms\Entities\HmsGroupBooking;
use Modules\Hms\Entities\HmsGroupRoomBlock;
use Modules\Hms\Entities\HmsProperty;
use Modules\Hms\Entities\HmsRatePlan;
use Modules\Hms\Entities\HmsRoom;
use Modules\Hms\Entities\HmsRoomType;
use Modules\Hms\Entities\HmsRoomTypePricing;
use Modules\Hms\Entities\HmsTransactionClass;
use Modules\Hms\Notifications\CustomerNotification;
use Modules\Hms\Services\BookingIntegrityService;
use Modules\Hms\Services\BookingLifecycleService;
use Modules\Hms\Services\FolioService;
use Modules\Hms\Services\GuestProfileService;
use Modules\Hms\Services\HospitalityDocumentService;
use Modules\Hms\Services\RatePlanService;
use Modules\Hms\Services\RoomRateService;
use Notification;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;

class HmsBookingController extends Controller
{
    protected $commonUtil;
    protected $notificationUtil;
    protected $contactUtil;
    protected $transactionUtil;
    protected $moduleUtil;
    protected $dummyPaymentLine;
    protected $productUtil;
    protected $businessUtil;
    protected $bookingIntegrityService;
    protected $bookingLifecycleService;
    protected $roomRateService;
    protected $folioService;
    protected $guestProfileService;

    public function __construct(
        Util $commonUtil,
        NotificationUtil $notificationUtil,
        ContactUtil $contactUtil,
        TransactionUtil $transactionUtil,
        ModuleUtil $moduleUtil,
        ProductUtil $productUtil,
        BusinessUtil $businessUtil,
        BookingIntegrityService $bookingIntegrityService,
        BookingLifecycleService $bookingLifecycleService,
        RoomRateService $roomRateService,
        FolioService $folioService,
        GuestProfileService $guestProfileService,

    ) {
        $this->commonUtil = $commonUtil;
        $this->notificationUtil = $notificationUtil;
        $this->contactUtil = $contactUtil;
        $this->transactionUtil = $transactionUtil;
        $this->moduleUtil = $moduleUtil;
        $this->productUtil = $productUtil;
        $this->businessUtil = $businessUtil;
        $this->bookingIntegrityService = $bookingIntegrityService;
        $this->bookingLifecycleService = $bookingLifecycleService;
        $this->roomRateService = $roomRateService;
        $this->folioService = $folioService;
        $this->guestProfileService = $guestProfileService;

        $this->dummyPaymentLine = ['method' => 'cash', 'amount' => 0, 'note' => '', 'card_transaction_number' => '', 'card_number' => '', 'card_type' => '', 'card_holder_name' => '', 'card_month' => '', 'card_year' => '', 'card_security' => '', 'cheque_number' => '', 'bank_account_number' => '',
            'is_return' => 0, 'transaction_no' => ''];
    }
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(Request $request)
    {

        $business_id = request()->session()->get('user.business_id');

        if (!(auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'hms_module'))) {
            abort(403, 'Unauthorized action.');
        }
        $this->authorizeBookingViewer();

        if (request()->ajax()) {
            $payment_types = $this->transactionUtil->payment_types(null, true, $business_id);

            $booking = HmsTransactionClass::where('transactions.business_id', $business_id)
                ->with(['payment_lines', 'media'])
                ->leftjoin('contacts as c', 'transactions.contact_id', '=', 'c.id')
                ->leftjoin('users as u', 'transactions.created_by', '=', 'u.id')
                ->where('transactions.type', 'hms_booking')
                ->select('transactions.*', 'c.name as c_name', DB::raw("CONCAT(COALESCE(u.surname, ''),' ',COALESCE(u.first_name, ''),' ',COALESCE(u.last_name,'')) as user_name"), DB::raw('(SELECT SUM(IF(TP.is_return = 1,-1*TP.amount,TP.amount)) FROM transaction_payments AS TP WHERE
                        TP.transaction_id=transactions.id) as total_paid'));

            // filter with contact
            if ($request->customer_id) {
                $booking = $booking->where('c.id', $request->customer_id);
            }
            // filter with status
            if ($request->status) {
                $booking = $booking->where('transactions.status', $request->status);
            }

            // filter with user
            if ($request->user_id) {
                $booking = $booking->where('u.id', $request->user_id);
            }

            // filtter with status
            if (!empty(request()->input('payment_status')) && request()->input('payment_status') != 'overdue') {
                $booking->where('transactions.payment_status', request()->input('payment_status'));
            } elseif (request()->input('payment_status') == 'overdue') {
                $booking->whereIn('transactions.payment_status', ['due', 'partial'])
                    ->whereNotNull('transactions.pay_term_number')
                    ->whereNotNull('transactions.pay_term_type')
                    ->whereRaw("IF(transactions.pay_term_type='days', DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number DAY) < CURDATE(), DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number MONTH) < CURDATE())");
            }

            // filter with date range (using arrival date)
            if (!empty(request()->input('start_date')) && !empty(request()->input('end_date'))) {
                $start_date = request()->input('start_date');
                $end_date = request()->input('end_date');
                $booking->whereBetween('transactions.hms_booking_arrival_date_time', [$start_date, $end_date]);
            }

            return Datatables::of($booking)

                ->editColumn('created_at', '{{@format_datetime($created_at)}}')

                ->addColumn('action', function ($row) {
                    $html = '';
                    $operationalStatus = $this->bookingLifecycleService->status($row);
                    if (auth()->user()->can('hms.edit_booking')
                        && in_array($operationalStatus, ['tentative', 'reserved'], true)) {
                        $html = '<a type="button" class="tw-dw-btn tw-dw-btn-primary tw-dw-btn-outline tw-dw-btn-xs btn-modal-extra " href="' . action([\Modules\Hms\Http\Controllers\HmsBookingController::class, 'edit'], ['booking' => $row->id]) . '">'
                        . __('hms::lang.edit_booking') . '</a>';
                    }

                    if (auth()->user()->can('superadmin')
                        || auth()->user()->can('hms.manage_front_desk')
                        || auth()->user()->can('hms.edit_booking')) {
                        if ($operationalStatus === 'reserved') {
                            $html .= '<a type="button" class="tw-dw-btn tw-dw-btn-info tw-dw-btn-outline tw-dw-btn-xs btn-modal-checkIn" href="' . action([\Modules\Hms\Http\Controllers\HmsBookingController::class, 'get_check_in_out'], ['id' => $row->id]) . '" style="margin:4px">'
                            . __('hms::lang.check_in') . '</a>';
                        } elseif ($operationalStatus === 'checked_in') {
                            $html .= '<a type="button" class="tw-dw-btn tw-dw-btn-error tw-dw-btn-outline tw-dw-btn-xs btn-modal-checkIn" href="' . action([\Modules\Hms\Http\Controllers\HmsBookingController::class, 'get_check_in_out'], ['id' => $row->id]) . '" style="margin:4px">'
                            . __('hms::lang.check_out') . '</a>';
                        }
                    }
                    $html .= '<a type="button" class="tw-dw-btn tw-dw-btn-success tw-dw-btn-outline tw-dw-btn-xs btn-modal-extra" href="' . action([\Modules\Hms\Http\Controllers\HmsBookingController::class, 'show'], ['booking' => $row->id]) . '" style="margin:4px">'
                    . __('hms::lang.view') . '</a>';
                    return $html;
                })
                ->editColumn(
                    'payment_status',
                    function ($row) {
                        $payment_status = Transaction::getPaymentStatus($row);

                        return (string) view('sell.partials.payment_status', ['payment_status' => $payment_status, 'id' => $row->id]);
                    }
                )
                ->editColumn('stay', '{{@format_datetime($hms_booking_arrival_date_time)}} - {{ @format_datetime($hms_booking_departure_date_time) }}')

                ->editColumn('status', function ($row) {
                    $status = $this->bookingLifecycleService->status($row);
                    $class = match ($status) {
                        'reserved', 'checked_in' => 'bg-green',
                        'tentative' => 'bg-yellow',
                        'checked_out' => 'bg-aqua',
                        default => 'bg-red',
                    };

                    return '<h6 class="badge '.$class.'">'.__('hms::lang.status_'.$status).'</h6>';
                })
                ->addColumn('payment_methods', function ($row) use ($payment_types) {
                    $methods = array_unique($row->payment_lines->pluck('method')->toArray());
                    $count = count($methods);
                    $payment_method = '';
                    if ($count == 1) {
                        $payment_method = $payment_types[$methods[0]] ?? '';
                    } elseif ($count > 1) {
                        $payment_method = __('lang_v1.checkout_multi_pay');
                    }

                    $html = !empty($payment_method) ? '<span class="payment-method" data-orig-value="' . $payment_method . '" data-status-name="' . $payment_method . '">' . $payment_method . '</span>' : '';

                    return $html;
                })
                ->editColumn(
                    'final_total',
                    '<span class="final-total" data-orig-value="{{$final_total}}">@format_currency($final_total)</span>'
                )
                ->editColumn(
                    'total_paid',
                    '<span class="total-paid" data-orig-value="{{$total_paid}}">@format_currency($total_paid)</span>'
                )
                ->addColumn('total_remaining', function ($row) {
                    $total_remaining = $row->final_total - $row->total_paid;
                    $total_remaining_html = '<span class="payment_due" data-orig-value="' . $total_remaining . '">' . $this->transactionUtil->num_f($total_remaining, true) . '</span>';

                    return $total_remaining_html;
                })
                ->rawColumns(['created_at', 'action', 'stay', 'status', 'payment_status', 'payment_methods', 'final_total', 'total_paid', 'total_remaining'])
                ->make(true);
        }

        $customers = Contact::customersDropdown($business_id, false);
        $users = User::forDropdown($business_id, false, false, false);
        $status = [
            'pending' => __('hms::lang.pending'),
            'confirmed' => __('hms::lang.confirmed'),
            'cancelled' => __('hms::lang.cancelled'),
        ];
        return view('hms::bookings.index', compact('customers', 'users', 'status'));
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create()
    {
        $business_id = request()->session()->get('user.business_id');

        if (!(auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'hms_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if (!auth()->user()->can('hms.add_booking')) {
            abort(403, 'Unauthorized action.');
        }

        $status = [
            'pending' => __('hms::lang.pending'),
            'confirmed' => __('hms::lang.confirmed'),
            'cancelled' => __('hms::lang.cancelled'),
        ];

        $extras = HmsExtra::where('business_id', $business_id)->get();

        $walk_in_customer = $this->contactUtil->getWalkInCustomer($business_id);

        $types = [];
        if (auth()->user()->can('supplier.create')) {
            $types['supplier'] = __('report.supplier');
        }
        if (auth()->user()->can('customer.create')) {
            $types['customer'] = __('report.customer');
        }
        if (auth()->user()->can('supplier.create') && auth()->user()->can('customer.create')) {
            $types['both'] = __('lang_v1.both_supplier_customer');
        }

        $customer_groups = CustomerGroup::forDropdown($business_id);

        $payment_line = $this->dummyPaymentLine;

        $change_return = $this->dummyPaymentLine;

        $payment_types = $this->productUtil->payment_types(null, true, $business_id);

        $business_details = $this->businessUtil->getDetails($business_id);

        $pos_settings = empty($business_details->pos_settings) ? $this->businessUtil->defaultPosSettings() : json_decode($business_details->pos_settings, true);

        $accounts = [];
        if ($this->moduleUtil->isModuleEnabled('account')) {
            $accounts = Account::forDropdown($business_id, true, false, true);
        }

        $busines = Business::findOrFail($business_id);

        // Get tax rates for the business
        $taxes = TaxRate::forBusinessDropdown($business_id, true, true);

        $properties = HmsProperty::where('business_id', $business_id)->where('is_active', true)->pluck('name', 'id');
        $ratePlans = HmsRatePlan::where('business_id', $business_id)->where('is_active', true)->pluck('name', 'id');
        $groupBookings = HmsGroupBooking::where('business_id', $business_id)
            ->whereIn('status', ['tentative', 'confirmed'])->pluck('name', 'id');

        return view('hms::bookings.create', compact('status', 'extras', 'walk_in_customer', 'types', 'customer_groups', 'payment_line', 'payment_types', 'pos_settings', 'change_return', 'accounts', 'busines', 'taxes', 'properties', 'ratePlans', 'groupBookings'));
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');

        if (!(auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'hms_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if (!auth()->user()->can('hms.add_booking')) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $this->validateBookingRequest($request, (int) $business_id);

        DB::beginTransaction();
        try {
            $arrival_date_time = $this->commonUtil->uf_date($request->arrival_date) . ' ' . $this->commonUtil->uf_time($request->arrival_time);
            $departure_date_time = $this->commonUtil->uf_date($request->departure_date) . ' ' . $this->commonUtil->uf_time($request->departure_time);

            $arrival = Carbon::parse($arrival_date_time);
            $departure = Carbon::parse($departure_date_time);

            $calculation = $this->bookingIntegrityService->prepare(
                (int) $business_id,
                $arrival,
                $departure,
                $validated['rooms'],
                $request->input('extras', []),
                $request->only([
                    'tax_rate_id',
                    'coupon_code',
                    'discount_type',
                    'total_discount',
                    'hms_property_id',
                    'hms_rate_plan_id',
                    'hms_group_booking_id',
                    'contact_id',
                ])
            );

            $busines = Business::findOrFail($business_id);

            $prefix = json_decode($busines->hms_settings)->prefix ?? null;

            $ref_no = null;

            $ref_count = $this->commonUtil->setAndGetReferenceCount("hms_booking", $business_id);
            //Generate reference number
            $ref_no = $this->commonUtil->generateReferenceNumber('hms_booking', $ref_count, $business_id, $prefix);

            // store in transsaction discount_amount
            $transaction = new HmsTransactionClass();
            $transaction->business_id = $business_id;
            $transaction->type = 'hms_booking';
            $transaction->status = $request->status;
            $transaction->contact_id = $request->contact_id;
            $transaction->created_by = auth()->user()->id;
            $transaction->ref_no = $ref_no;
            // These values are recalculated on the server. Hidden browser totals
            // are previews only and are never trusted for persistence.
            $transaction->total_before_tax = $calculation['total_before_tax'];
            $transaction->final_total = $calculation['final_total'];
            $transaction->tax_id = $calculation['tax_id'];
            $transaction->tax_amount = $calculation['tax_amount'];
            $transaction->discount_amount = $calculation['discount_amount'];
            $transaction->hms_coupon_id = $calculation['coupon_id'];
            $transaction->discount_type = $calculation['discount_type'];

            $transaction->hms_booking_arrival_date_time = $arrival_date_time;
            $transaction->hms_booking_departure_date_time = $departure_date_time;
            $transaction->hms_property_id = $calculation['hms_property_id'];
            $transaction->hms_rate_plan_id = $calculation['hms_rate_plan_id'];
            $transaction->hms_group_booking_id = $calculation['hms_group_booking_id'];
            $property = HmsProperty::where('business_id', $business_id)->findOrFail($calculation['hms_property_id']);
            $transaction->location_id = $property->location_id;
            $guestProfile = $this->guestProfileService->sync(
                (int) $business_id,
                (int) $validated['contact_id'],
                $this->guestProfileInput($request)
            );
            $transaction->hms_guest_profile_id = $guestProfile->id;
            $transaction->hms_booking_source = $validated['hms_booking_source'] ?? 'direct';
            $transaction->hms_external_reference = $validated['hms_external_reference'] ?? null;
            $transaction->hms_hold_expires_at = $request->status === 'pending'
                ? (! empty($validated['hms_hold_expires_at'])
                    ? Carbon::parse($validated['hms_hold_expires_at'])->format('Y-m-d H:i:s')
                    : null)
                : null;
            $transaction->hms_special_requests = $validated['hms_special_requests'] ?? null;
            $transaction->hms_cancellation_reason = $validated['hms_cancellation_reason'] ?? null;
            
            // Add new trip fields
            $transaction->hms_reason_for_trip = $request->hms_reason_for_trip;
            $transaction->hms_means_of_transport = $request->hms_means_of_transport;
            $transaction->hms_vehicle_registration_number = $request->hms_vehicle_registration_number;
            $transaction->hms_place_of_origin = $request->hms_place_of_origin;
            $transaction->hms_final_destination = $request->hms_final_destination;
            
            $transaction->save();


            Media::uploadMedia($transaction->business_id, $transaction, $request, 'id_proof_1', false, 'id_proof_1');
            Media::uploadMedia($transaction->business_id, $transaction, $request, 'id_proof_2', false, 'id_proof_2');
            Media::uploadMedia($transaction->business_id, $transaction, $request, 'id_proof_3', false, 'id_proof_3');

            $room_lines = array_map(
                fn ($line) => new HmsBookingLine($line),
                $calculation['room_lines']
            );
            $transaction->hms_booking_lines()->saveMany($room_lines);
            $this->syncGroupRoomPickup($transaction, $calculation['room_lines']);

            $extra_lines = array_map(
                fn ($line) => new HmsBookingExtra($line),
                $calculation['extra_lines']
            );
            $transaction->hms_booking_extras()->saveMany($extra_lines);

            $this->bookingLifecycleService->initialize(
                $transaction,
                $validated['status'],
                (int) auth()->id()
            );

            //Add change return
            $input = $request->except('_token');
            //Add change return
            $change_return = $this->dummyPaymentLine;
            if (!empty($input['payment']['change_return'])) {
                $change_return = $input['payment']['change_return'];
                unset($input['payment']['change_return']);
            }

            $change_return['amount'] = $input['change_return'] ?? 0;
            $change_return['is_return'] = 1;

            $input['payment'][] = $change_return;

            if (!empty($input['payment'])) {
                $this->transactionUtil->createOrUpdatePaymentLines($transaction, $this->sanitizePaymentLines($input['payment']));
            }

            $this->transactionUtil->updatePaymentStatus($transaction->id, $transaction->final_total);
            $transaction->refresh();
            $folio = $this->folioService->syncBooking($transaction, (int) auth()->id());
            app(\App\Services\SecurityDepositService::class)->forHmsFolio(
                $folio,
                (float) ($calculation['security_deposit_required'] ?? 0),
                (int) auth()->id()
            );
            if ($calculation['deposit_required'] > 0) {
                $this->folioService->scheduleDeposit(
                    $folio,
                    (float) $calculation['deposit_required'],
                    Carbon::parse($arrival_date_time)->subDays(1)->toDateString(),
                    (int) auth()->id(),
                    __('hms::lang.rate_plan_deposit')
                );
            }

            // send notification to customer
            $template = NotificationTemplate::where('template_for', 'hms_new_booking')->where('business_id', $business_id)->first();

            if ($template && $template->auto_send) {

                $data = [
                    'email_body' => $template->email_body,
                    'subject' => $template->subject,
                ];

                $customer = Contact::where('business_id', $business_id)
                    ->findOrFail($transaction->contact_id);

                $tag_replaced_data = $this->notificationUtil->replaceHmsBookingTags(
                    $data,
                    $transaction,
                    $calculation['adults'],
                    $calculation['children'],
                    $customer
                );

                $orig_data = [
                    'business_id' => $business_id,
                    'email_body' => $tag_replaced_data['email_body'],
                    'subject' => $tag_replaced_data['subject'],
                    'cc' => $template->cc,
                    'bcc' => $template->cc,
                ];

                if (! empty($customer->email)) {
                    Notification::route('mail', $customer->email)->notify(new CustomerNotification($orig_data));
                }
            }

            DB::commit();

            $output = [
                'success' => 1,
                'msg' => __('lang_v1.success'),
            ];

            return redirect()->action(
                [\Modules\Hms\Http\Controllers\HmsBookingController::class, 'index'])
                ->with('status', $output);

        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:' . $e->getFile() . 'Line:' . $e->getLine() . 'Message:' . $e->getMessage());

            $output = [
                'success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];

            return back()->with('status', $output)->withInput();
        }
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        $this->authorizeBookingViewer();
        $business_id = request()->session()->get('user.business_id');

        $transaction = HmsTransactionClass::where('transactions.business_id', $business_id)
            ->with(['contact', 'media', 'tax', 'hms_booking_events.actor', 'hms_property', 'hms_rate_plan', 'hms_group_booking', 'hms_guest_profile', 'hms_folios.entries'])
            ->leftJoin('hms_booking_lines as hbl', 'transactions.id', '=', 'hbl.transaction_id')
            ->leftJoin('hms_booking_extras as hbe', 'transactions.id', '=', 'hbe.transaction_id')
            ->leftJoin('hms_coupons as coupons', 'transactions.hms_coupon_id', '=', 'coupons.id')
            ->where('transactions.type', 'hms_booking')
            ->select(
                'transactions.*',
                DB::raw('(SELECT SUM(total_price) FROM hms_booking_lines WHERE transaction_id = transactions.id) as room_price'),
                DB::raw('(SELECT SUM(price) FROM hms_booking_extras WHERE transaction_id = transactions.id) as extra_price'),
                'coupons.coupon_code'
            )
            ->groupBy('transactions.id') // Group by transaction ID
            ->findOrFail($id);

        // Calculate number of nights
        $no_of_nights = $this->countDaysBetweenDates($transaction->hms_booking_arrival_date_time, $transaction->hms_booking_departure_date_time);

        $extras_id = HmsBookingExtra::where('transaction_id', $id)->pluck('hms_extra_id')->toArray();

        $booking_rooms = HmsBookingLine::where('transaction_id', $id)
            ->leftjoin('hms_rooms as room', 'room.id', '=', 'hms_booking_lines.hms_room_id')
            ->leftjoin('hms_room_types as type', 'type.id', '=', 'hms_booking_lines.hms_room_type_id')
            ->get();

        $extras = HmsExtra::where('business_id', $business_id)->get();

        $busines = Business::findOrFail($business_id);

        return view('hms::bookings.show', compact('extras', 'transaction', 'extras_id', 'booking_rooms', 'busines', 'no_of_nights'));

    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {

        $business_id = request()->session()->get('user.business_id');

        if (!(auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'hms_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if (!auth()->user()->can('hms.edit_booking')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $transaction = HmsTransactionClass::with('media')->where('transactions.business_id', $business_id)
            ->leftjoin('hms_coupons as coupon', 'transactions.hms_coupon_id', '=', 'coupon.id')
            ->select(['transactions.*', 'coupon.coupon_code'])
            ->findOrFail($id);
        $status = [
            'pending' => __('hms::lang.pending'),
            'confirmed' => __('hms::lang.confirmed'),
            'cancelled' => __('hms::lang.cancelled'),
        ];

        $customer_due = $this->transactionUtil->getContactDue($transaction->contact_id, $transaction->business_id);

        $types = [];
        if (auth()->user()->can('supplier.create')) {
            $types['supplier'] = __('report.supplier');
        }
        if (auth()->user()->can('customer.create')) {
            $types['customer'] = __('report.customer');
        }
        if (auth()->user()->can('supplier.create') && auth()->user()->can('customer.create')) {
            $types['both'] = __('lang_v1.both_supplier_customer');
        }

        $customer_groups = CustomerGroup::forDropdown($business_id);

        $customer_due = $customer_due != 0 ? $this->transactionUtil->num_f($customer_due, true) : '';

        $extras_id = HmsBookingExtra::where('transaction_id', $id)->pluck('hms_extra_id')->toArray();

        $booking_rooms = HmsBookingLine::where('transaction_id', $id)
            ->leftjoin('hms_rooms as room', 'room.id', '=', 'hms_booking_lines.hms_room_id')
            ->leftjoin('hms_room_types as type', 'type.id', '=', 'hms_booking_lines.hms_room_type_id')
            ->get();

        $business_id = request()->session()->get('user.business_id');

        $extras = HmsExtra::where('business_id', $business_id)->get();

        $payment_types = $this->productUtil->payment_types(null, true, $business_id);

        $business_details = $this->businessUtil->getDetails($business_id);

        $pos_settings = empty($business_details->pos_settings) ? $this->businessUtil->defaultPosSettings() : json_decode($business_details->pos_settings, true);

        $payment_lines = $this->transactionUtil->getPaymentDetails($id);
        //If no payment lines found then add dummy payment line.
        if (empty($payment_lines)) {
            $payment_lines[] = $this->dummyPaymentLine;
        }

        $change_return = $this->dummyPaymentLine;

        $accounts = [];
        if ($this->moduleUtil->isModuleEnabled('account')) {
            $accounts = Account::forDropdown($business_id, true, false, true);
        }

        $busines = Business::findOrFail($business_id);

        // Get tax rates for the business
        $taxes = TaxRate::forBusinessDropdown($business_id, true, true);

        $properties = HmsProperty::where('business_id', $business_id)->where('is_active', true)->pluck('name', 'id');
        $ratePlans = HmsRatePlan::where('business_id', $business_id)->where('is_active', true)->pluck('name', 'id');
        $groupBookings = HmsGroupBooking::where('business_id', $business_id)
            ->whereIn('status', ['tentative', 'confirmed'])->pluck('name', 'id');

        return view('hms::bookings.edit', compact('status', 'extras', 'transaction', 'extras_id', 'booking_rooms', 'types', 'customer_groups', 'customer_due', 'payment_types', 'pos_settings', 'payment_lines', 'change_return', 'accounts', 'busines', 'taxes', 'properties', 'ratePlans', 'groupBookings'));
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(Request $request, $id)
    {
        $business_id = request()->session()->get('user.business_id');

        if (!(auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'hms_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if (!auth()->user()->can('hms.edit_booking')) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $this->validateBookingRequest($request, (int) $business_id, (int) $id);

        DB::beginTransaction();
        try {
            $arrival_date_time = $this->commonUtil->uf_date($request->arrival_date) . ' ' . $this->commonUtil->uf_time($request->arrival_time);
            $departure_date_time = $this->commonUtil->uf_date($request->departure_date) . ' ' . $this->commonUtil->uf_time($request->departure_time);

            $transaction = HmsTransactionClass::where('business_id', $business_id)
                ->where('type', 'hms_booking')
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentOperationalStatus = $this->bookingLifecycleService->status($transaction);
            if (in_array($currentOperationalStatus, ['checked_in', 'checked_out', 'cancelled', 'no_show'], true)) {
                throw ValidationException::withMessages([
                    'status' => __('hms::lang.operational_booking_edit_locked'),
                ]);
            }

            $calculation = $this->bookingIntegrityService->prepare(
                (int) $business_id,
                Carbon::parse($arrival_date_time),
                Carbon::parse($departure_date_time),
                $validated['rooms'],
                $request->input('extras', []),
                $request->only([
                    'tax_rate_id',
                    'coupon_code',
                    'discount_type',
                    'total_discount',
                    'hms_property_id',
                    'hms_rate_plan_id',
                    'hms_group_booking_id',
                    'contact_id',
                ]),
                (int) $transaction->id
            );

            $transaction->status = $validated['status'];
            $transaction->contact_id = $request->contact_id;
            $transaction->total_before_tax = $calculation['total_before_tax'];
            $transaction->final_total = $calculation['final_total'];
            $transaction->tax_id = $calculation['tax_id'];
            $transaction->tax_amount = $calculation['tax_amount'];
            $transaction->discount_amount = $calculation['discount_amount'];
            $transaction->hms_coupon_id = $calculation['coupon_id'];
            $transaction->discount_type = $calculation['discount_type'];

            $transaction->hms_booking_arrival_date_time = $arrival_date_time;
            $transaction->hms_booking_departure_date_time = $departure_date_time;
            $transaction->hms_property_id = $calculation['hms_property_id'];
            $transaction->hms_rate_plan_id = $calculation['hms_rate_plan_id'];
            $transaction->hms_group_booking_id = $calculation['hms_group_booking_id'];
            $property = HmsProperty::where('business_id', $business_id)->findOrFail($calculation['hms_property_id']);
            $transaction->location_id = $property->location_id;
            $guestProfile = $this->guestProfileService->sync(
                (int) $business_id,
                (int) $validated['contact_id'],
                $this->guestProfileInput($request)
            );
            $transaction->hms_guest_profile_id = $guestProfile->id;
            $transaction->hms_booking_source = $validated['hms_booking_source'] ?? 'direct';
            $transaction->hms_external_reference = $validated['hms_external_reference'] ?? null;
            $transaction->hms_hold_expires_at = $validated['status'] === 'pending'
                ? (! empty($validated['hms_hold_expires_at'])
                    ? Carbon::parse($validated['hms_hold_expires_at'])->format('Y-m-d H:i:s')
                    : null)
                : null;
            $transaction->hms_special_requests = $validated['hms_special_requests'] ?? null;
            $transaction->hms_cancellation_reason = $validated['hms_cancellation_reason'] ?? null;
            
            // Add new trip fields
            $transaction->hms_reason_for_trip = $request->hms_reason_for_trip;
            $transaction->hms_means_of_transport = $request->hms_means_of_transport;
            $transaction->hms_vehicle_registration_number = $request->hms_vehicle_registration_number;
            $transaction->hms_place_of_origin = $request->hms_place_of_origin;
            $transaction->hms_final_destination = $request->hms_final_destination;
            
            $transaction->update();

            Media::uploadMedia($transaction->business_id, $transaction, $request, 'id_proof_1', false, 'id_proof_1');
            Media::uploadMedia($transaction->business_id, $transaction, $request, 'id_proof_2', false, 'id_proof_2');
            Media::uploadMedia($transaction->business_id, $transaction, $request, 'id_proof_3', false, 'id_proof_3');

            HmsBookingLine::where('transaction_id', $transaction->id)->delete();
            $room_lines = array_map(
                fn ($line) => new HmsBookingLine($line),
                $calculation['room_lines']
            );
            $transaction->hms_booking_lines()->saveMany($room_lines);
            $this->syncGroupRoomPickup($transaction, $calculation['room_lines']);

            HmsBookingExtra::where('transaction_id', $transaction->id)->delete();
            $extra_lines = array_map(
                fn ($line) => new HmsBookingExtra($line),
                $calculation['extra_lines']
            );
            $transaction->hms_booking_extras()->saveMany($extra_lines);

            $this->bookingLifecycleService->synchronizeReservationStatus(
                $transaction,
                $validated['status'],
                (int) auth()->id(),
                $validated['hms_cancellation_reason'] ?? null
            );

            $this->bookingLifecycleService->record(
                $transaction,
                'booking_updated',
                $transaction->hms_booking_status,
                $transaction->hms_booking_status,
                (int) auth()->id(),
                null,
                ['server_recalculated_total' => $calculation['final_total']]
            );

            //Add change return
            $input = $request->except('_token');
            $change_return = $this->dummyPaymentLine;
            if (!empty($input['payment']['change_return'])) {
                $change_return = $input['payment']['change_return'];
                unset($input['payment']['change_return']);
            }

            //Add change return
            $change_return['amount'] = $input['change_return'] ?? 0;
            $change_return['is_return'] = 1;
            if (!empty($input['change_return_id'])) {
                $change_return['payment_id'] = $input['change_return_id'];
            }
            $input['payment'][] = $change_return;

            if (!empty($input['payment'])) {
                $this->transactionUtil->createOrUpdatePaymentLines($transaction, $this->sanitizePaymentLines($input['payment']));
            }

            $this->transactionUtil->updatePaymentStatus($transaction->id, $transaction->final_total);
            $transaction->refresh();
            $folio = $this->folioService->syncBooking($transaction, (int) auth()->id());
            app(\App\Services\SecurityDepositService::class)->forHmsFolio(
                $folio,
                (float) ($calculation['security_deposit_required'] ?? 0),
                (int) auth()->id()
            );

            DB::commit();

            $output = [
                'success' => 1,
                'msg' => __('lang_v1.success'),
            ];

            return redirect()->action(
                [\Modules\Hms\Http\Controllers\HmsBookingController::class, 'index'])->with('status', $output);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:' . $e->getFile() . 'Line:' . $e->getLine() . 'Message:' . $e->getMessage());

            $output = [
                'success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];

            return back()->with('status', $output)->withInput();
        }

    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        //
    }

    // this function return modal for add room during booking
    public function booking_room_add()
    {

        $business_id = request()->session()->get('user.business_id');
        $this->authorizeBookingEditor();

        $types = HmsRoomType::where('business_id', $business_id)->whereRaw('EXISTS (SELECT 1 FROM hms_room_type_pricings WHERE hms_room_type_id = hms_room_types.id)')->pluck('type', 'id')->toArray();

        return view('hms::bookings.add_room', compact('types'));
    }

    // this function return modal for edit singal room during booking
    public function booking_room_edit(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeBookingEditor();

        $request->validate([
            'type_id' => ['required', 'integer', Rule::exists('hms_room_types', 'id')->where('business_id', $business_id)],
            'room_id' => 'required|integer',
            'no_of_adult' => 'required|integer|min:1',
            'no_of_child' => 'required|integer|min:0',
            'room_ids' => 'nullable|array',
            'room_ids.*' => 'integer',
        ]);

        $no_of_child = $request->input('no_of_child');
        $no_of_adult = $request->input('no_of_adult');
        $room_id = $request->input('room_id');
        $type_id = $request->input('type_id');

        $type = HmsRoomType::where('business_id', $business_id)->findOrFail($type_id);

        $types = HmsRoomType::where('business_id', $business_id)->whereRaw('EXISTS (SELECT 1 FROM hms_room_type_pricings WHERE hms_room_type_id = hms_room_types.id)')->pluck('type', 'id')->toArray();

        $room = HmsRoom::where('hms_room_type_id', $type->id)
            ->whereHas('type', fn ($query) => $query->where('business_id', $business_id))
            ->findOrFail($request->input('room_id'));

        $existing_rooms = [];

        if (!empty($request->input('room_ids'))) {
            $existing_rooms = $request->input('room_ids');
            $existing_rooms = array_diff($existing_rooms, [$room_id]);
        }

        $rooms = HmsRoom::where('hms_room_type_id', $type_id)
            ->whereHas('type', fn ($query) => $query->where('business_id', $business_id))
            ->whereNotIn('id', $existing_rooms)
            ->pluck('room_number', 'id')
            ->toArray();

        return view('hms::bookings.edit_room', compact('types', 'type', 'rooms', 'room_id', 'no_of_child', 'no_of_adult'));

    }

    // this function return room according to type
    public function get_room_type_by(Request $request)
    {
        $business_id = (int) request()->session()->get('user.business_id');
        $this->authorizeBookingEditor();
        $request->validate([
            'type_id' => ['required', 'integer', Rule::exists('hms_room_types', 'id')->where('business_id', $business_id)],
            'arrival_date' => 'required|string|max:50',
            'arrival_time' => 'required|string|max:50',
            'departure_date' => 'required|string|max:50',
            'departure_time' => 'required|string|max:50',
            'room_ids' => 'nullable|array',
            'room_ids.*' => 'integer',
            't_id' => [
                'nullable', 'integer',
                Rule::exists('transactions', 'id')->where(fn ($query) => $query
                    ->where('business_id', $business_id)
                    ->where('type', 'hms_booking')),
            ],
            'hms_group_booking_id' => [
                'nullable', 'integer',
                Rule::exists('hms_group_bookings', 'id')->where('business_id', $business_id),
            ],
        ]);

        $type_id = $request->input('type_id');

        $arrival_date_time = $this->commonUtil->uf_date($request->arrival_date) . ' ' . $this->commonUtil->uf_time($request->arrival_time);

        $departure_date_time = $this->commonUtil->uf_date($request->departure_date) . ' ' . $this->commonUtil->uf_time($request->departure_time);

        $type = HmsRoomType::where('business_id', $business_id)->findOrFail($type_id);
        $existing_rooms = [];

        if (!empty($request->input('room_ids'))) {
            $existing_rooms = $request->input('room_ids');
        }

        $t_id = null;

        if (!empty($request->input('t_id'))) {
            $t_id = $request->input('t_id');
        }

        $rooms = $this->bookingIntegrityService->availableRooms(
            $business_id,
            (int) $type_id,
            Carbon::parse($arrival_date_time),
            Carbon::parse($departure_date_time),
            $existing_rooms,
            $t_id ? (int) $t_id : null,
            $request->filled('hms_group_booking_id') ? (int) $request->input('hms_group_booking_id') : null
        );

        return view('hms::bookings.room_type_by', compact('rooms', 'type'));
    }

    // this function view after select room during booking with calculation
    public function get_room_detail(Request $request, RatePlanService $ratePlanService)
    {
        $business_id = (int) request()->session()->get('user.business_id');
        $this->authorizeBookingEditor();
        $request->validate([
            'type_id' => ['required', 'integer', Rule::exists('hms_room_types', 'id')->where('business_id', $business_id)],
            'room_id' => 'required|integer',
            'no_of_adult' => 'required|integer|min:1',
            'no_of_child' => 'required|integer|min:0',
            'arrival_date' => 'required|string|max:50',
            'departure_date' => 'required|string|max:50',
            'hms_rate_plan_id' => [
                'nullable', 'integer',
                Rule::exists('hms_rate_plans', 'id')->where('business_id', $business_id),
            ],
            'contact_id' => [
                'nullable', 'integer',
                Rule::exists('contacts', 'id')->where('business_id', $business_id),
            ],
            't_id' => [
                'nullable', 'integer',
                Rule::exists('transactions', 'id')->where(fn ($query) => $query
                    ->where('business_id', $business_id)
                    ->where('type', 'hms_booking')),
            ],
        ]);

        $currentIndex = $request->input('current_index');
        $type = HmsRoomType::where('business_id', $business_id)
            ->findOrFail($request->input('type_id'));
        $room = HmsRoom::where('hms_room_type_id', $type->id)
            ->whereHas('type', fn ($query) => $query->where('business_id', $business_id))
            ->findOrFail($request->input('room_id'));
        $no_of_child = $request->input('no_of_child');
        $no_of_adult = $request->input('no_of_adult');
        $is_edit = true;

        if ($request->input('is_edit')) {
            $is_edit = false;
        }

        $arrival_date = $this->commonUtil->uf_date($request->input('arrival_date'));
        $departure_date = $this->commonUtil->uf_date($request->input('departure_date'));
        $start = Carbon::parse($arrival_date);
        $end = Carbon::parse($departure_date);
        $quote = $this->roomRateService->quote(
            (int) $type->id,
            $start,
            $end,
            (int) $no_of_adult,
            (int) $no_of_child
        );
        if ($request->filled('hms_rate_plan_id')) {
            $quote = $ratePlanService->apply(
                $business_id,
                (int) $type->hms_property_id,
                (int) $request->input('hms_rate_plan_id'),
                (int) $type->id,
                $start,
                $end,
                (int) $no_of_adult,
                (int) $no_of_child,
                $quote,
                $request->filled('contact_id') ? (int) $request->input('contact_id') : null,
                $request->filled('t_id') ? (int) $request->input('t_id') : null
            );
        }

        $data = [
            'no_of_child' => $no_of_child,
            'no_of_adult' => $no_of_adult,
            'total_price' => $quote['total'],
            'price' => $quote['average_rate'],
        ];

        return view('hms::bookings.room_detail', compact('type', 'room', 'data', 'currentIndex', 'is_edit'));
    }

    // return price according to start day from pricing table
    public function get_price($type_id, $arrival_date, $no_of_adult, $no_of_child)
    {
        $arrival = Carbon::createFromFormat('Y-m-d', $arrival_date)->startOfDay();

        return $this->roomRateService->quote(
            (int) $type_id,
            $arrival,
            $arrival->copy()->addDay(),
            (int) $no_of_adult,
            (int) $no_of_child
        )['average_rate'];
    }

    // return price according to day if null return default price
    public function day_wise_or_default_price($type_id, $price_day)
    {
        $pricing = HmsRoomTypePricing::whereNull('adults')->whereNull('childrens')->where('hms_room_type_id', $type_id)->first();

        if ($pricing && !is_null($pricing->$price_day)) {
            return $pricing->$price_day;
        }
        return $pricing ? $pricing->default_price_per_night : null;
    }

    // display list of booking in calender view

    public function calendar(Request $request)
    {
        $this->authorizeBookingViewer();

        $business_id = request()->session()->get('user.business_id');

        $types = HmsRoomType::where('business_id', $business_id)->pluck('type', 'id')->toArray();

        $rooms = HmsRoom::leftjoin('hms_room_types as type', 'type.id', '=', 'hms_rooms.hms_room_type_id')
            ->where('type.business_id', $business_id)
            ->select('hms_rooms.*', 'type.type', 'type.id as type_id');

        if ($request->type_id) {
            $rooms = $rooms->where('type.id', $request->type_id);
        }

        $rooms = $rooms->get();

        $start_date = now();

        // return $start_date;

        if ($request->day_next) {
            $start_date = now()->startOfWeek()->addDays($request->day_next);
        }

        if ($request->week_next) {
            $start_date = $start_date->addWeeks($request->week_next);
        }

        if ($request->date) {
            $start_date = Carbon::parse($request->date);
        }

        $date_html = '';
        $html = '';
        $class = '';
        $header_date = $start_date->copy();

        for ($i = 0; $i <= 6; $i++) {

            $header_date = $start_date->copy();

            if ($request->day_next) {
                // Clone the $header_date object to avoid modifying it
                $current_date = $header_date->clone();

                // Add $i days to the current date
                $current_date->addDays($i);

                if ($current_date->format('Y-m-d') == now()->format('Y-m-d')) {
                    $class = 'bg-success';
                }
                // Generate the HTML for the table header
                $date_html .= '<th style="width: 100px;" class="text-center ' . $class . '">
                                ' . $current_date->format('d') . ' <br>
                                ' . $current_date->format("l") . '
                                </th>';
            } else {

                if ($header_date->startOfWeek()->addDays($i)->format('Y-m-d') == now()->format('Y-m-d')) {
                    $class = 'bg-success';
                }

                $date_html .= '<th style="width: 100px;" class="text-center ' . $class . '">
                    ' . $header_date->startOfWeek()->addDays($i)->format('d') . ' <br>
                    ' . $header_date->startOfWeek()->addDays($i)->format("l") . '
                    </th>';
            }
            $class = '';
        }

        foreach ($rooms as $room) {
            $html .= '<tr><th class="text-center">' . $room->room_number . ' <br> <small>' . $room->type . '</small/></th>';

            $refNos = [];
            for ($j = 0; $j <= 6; $j++) {
                $row_date = $start_date->copy();
                $days = $j;

                if ($request->day_next) {
                    $date = $row_date->addDays($days)->format('Y-m-d');
                } else {
                    $date = $row_date->startOfWeek()->addDays($days)->format('Y-m-d');
                }

                $last_date = $row_date->addDays(6-$j)->format('Y-m-d');
                
                $s_date = $row_date->clone()->subDays(6)->format('Y-m-d');


                $bookings = $this->is_booking($date, $room->id);
                
                if ($bookings->count() > 0) {
                    $margin = 0;
                    $html .= '<td>';
                    foreach ($bookings as $key => $is_booking) {
                        $d_date = \Carbon\Carbon::parse($is_booking->hms_booking_departure_date_time)->format('Y-m-d');
                        // Skip bookings that end after the last date of the week
                        if (\Carbon\Carbon::parse($is_booking->hms_booking_departure_date_time)->format('Y-m-d') > $last_date) {
                            $d_date = $last_date; 
                        }

                        $a_date = \Carbon\Carbon::parse($is_booking->hms_booking_arrival_date_time)->format('Y-m-d');

                        // Skip bookings that end after the last date of the week
                        if (\Carbon\Carbon::parse($is_booking->hms_booking_arrival_date_time)->format('Y-m-d') < $s_date) {
                            $a_date = $s_date; 
                        }
                        
                        $size = $this->countDaysBetweenDates($a_date, $d_date);

                        $size = $size == 0 ? 1 : $size + 1;

                        $size = $size * 100;
                        
                        if (in_array($is_booking->ref_no, $refNos)) {
                            $margin = $margin + 20;
                            $html .= '<div class="hotel-reservation-outer tooltip-demo" style="display: none; >';
                        } else {
                            $html .= '<div class="hotel-reservation-outer tooltip-demo" style="margin-top: ' . $margin . '%;"">';
                            $margin = 0;
                        }
                        $html .=    '<a href="' . action([\Modules\Hms\Http\Controllers\HmsBookingController::class, 'index']) . '" class="hotel-reservation" data-toggle="popover" data-trigger="hover" data-content="'.($is_booking->email ? $is_booking->email . '<br>' : '') . 'Phone: '.$is_booking->mobile.'<br/>Adults: '.$is_booking->adults.', Children: '.$is_booking->childrens.'<br/>ID: '.$is_booking->ref_no.'" data-html="true" data-placement="bottom">
                                <div class="hotel-reservation-inner bg-confirmed" style="width: '.$size.'%;"' . $is_booking->ref_no . '"><strong>' . $is_booking->name . '</strong></div>
                            </a>
                            </div>';
                        
                        $refNos[] = $is_booking->ref_no;
                    }
                $html .= '</td>';
                } else {
                    $html .= '<td class="text-center add_booking">
                        <div class="add_booking_div"><a title="Add Booking" href="' . action([\Modules\Hms\Http\Controllers\HmsBookingController::class, 'create']) . '?booking_date=' . $date . '"><i class="fa fa-fw fa-plus"></i></a></div>
                    </td>';
                }
            }
            $html .= '</tr>';

        }
        return view('hms::bookings.calender', compact('types', 'rooms', 'start_date', 'html', 'date_html'));
    }

    public function is_booking($date, $id)
    {
        $bookings = HmsBookingLine::leftjoin('transactions', 'transactions.id', '=', 'hms_booking_lines.transaction_id')
            ->where('hms_booking_lines.hms_room_id', $id)
            ->whereDate('transactions.hms_booking_arrival_date_time', '<=', $date)
            ->whereDate('transactions.hms_booking_departure_date_time', '>=', $date)
            ->where('transactions.status', 'confirmed')
            ->leftJoin('contacts AS c', 'transactions.contact_id', '=', 'c.id')
            ->get();
        return $bookings;
    }

    public function countDaysBetweenDates($arrivalDate, $departureDate)
    {
        // Parse the input strings as Carbon DateTime objects
        $arrivalDateTime = \Carbon\Carbon::parse($arrivalDate);
        $departureDateTime = \Carbon\Carbon::parse($departureDate);

        return max(1, $arrivalDateTime->copy()->startOfDay()->diffInDays(
            $departureDateTime->copy()->startOfDay()
        ));
    }

    public function print(Request $request, HospitalityDocumentService $documents, $id)
    {
        $this->authorizeBookingViewer();
        $businessId = (int) $request->session()->get('user.business_id');
        $kind = $request->query('document', 'invoice');
        abort_unless(in_array($kind, ['quotation', 'invoice'], true), 422, 'Select an accommodation quotation or invoice.');
        $business = Business::findOrFail($businessId);
        $transaction = HmsTransactionClass::where('business_id', $businessId)
            ->where('type', 'hms_booking')
            ->with(['contact', 'tax', 'payment_lines', 'hms_booking_lines.room', 'hms_booking_extras.extra'])
            ->findOrFail($id);
        $documentContext = $documents->stayContext($transaction, $kind);
        $payments = $documents->accommodationPayments($transaction->payment_lines);
        $transaction->setRelation('payment_lines', $payments);
        $transaction->room_price = round((float) $transaction->hms_booking_lines->sum('total_price'), 4);
        $transaction->extra_price = round((float) $transaction->hms_booking_extras
            ->filter(fn ($line) => ($line->financial_classification ?? 'revenue') === 'revenue')
            ->sum('price'), 4);
        $transaction->total_paid = round((float) $payments->sum(function ($payment) {
            return $payment->is_return ? -(float) $payment->amount : (float) $payment->amount;
        }), 4);

        $booking_rooms = HmsBookingLine::where('transaction_id', $id)
            ->leftjoin('hms_rooms as room', 'room.id', '=', 'hms_booking_lines.hms_room_id')
            ->leftjoin('hms_room_types as type', 'type.id', '=', 'hms_booking_lines.hms_room_type_id')
            ->get();

        $bookingExtras = HmsBookingExtra::where('transaction_id', $id)->with('extra')->get();
        $revenueExtras = $bookingExtras->filter(
            fn ($line) => ($line->financial_classification ?? 'revenue') === 'revenue'
        );
        $securityDeposit = \App\SecurityDeposit::forBusiness($businessId)
            ->where('context_type', 'hms_booking')->where('context_id', $transaction->id)->with('entries')->first();

        $payment_types = $this->transactionUtil->payment_types(null, true, $businessId);

        $html = view('hms::bookings.accommodation_a4', compact(
            'business', 'transaction', 'booking_rooms', 'revenueExtras',
            'securityDeposit', 'payment_types', 'documentContext'
        ))->render();
        $tempDir = storage_path('app/mpdf');
        \Illuminate\Support\Facades\File::ensureDirectoryExists($tempDir);
        $mpdf = new \Mpdf\Mpdf(['tempDir' => $tempDir,
            'mode' => 'utf-8',
            'format' => 'A4',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'autoVietnamese' => true,
            'autoArabic' => true,
            'margin_top' => 10,
            'margin_right' => 10,
            'margin_bottom' => 14,
            'margin_left' => 10,
        ]);
        $mpdf->useSubstitutions = true;
        $mpdf->SetTitle($documentContext['title'].' | '.$transaction->ref_no);
        $mpdf->WriteHTML($html);
        $filename = \Illuminate\Support\Str::slug($documentContext['title'].'-'.$transaction->ref_no).'.pdf';

        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    /**
     * Print 80mm receipt for HMS booking
     */
    public function printReceipt($id, HospitalityDocumentService $documents)
    {
        $this->authorizeBookingViewer();
        if (request()->ajax()) {
            try {
                $output = ['success' => 0, 'msg' => trans('messages.something_went_wrong')];

                $business_id = request()->session()->get('user.business_id');

                $business = Business::findOrFail($business_id);

                $transaction = Transaction::where('transactions.business_id', $business_id)
                    ->with(['contact', 'tax', 'payment_lines'])
                    ->leftJoin('hms_booking_lines as hbl', 'transactions.id', '=', 'hbl.transaction_id')
                    ->leftJoin('hms_booking_extras as hbe', 'transactions.id', '=', 'hbe.transaction_id')
                    ->leftJoin('hms_coupons as coupons', 'transactions.hms_coupon_id', '=', 'coupons.id')
                    ->where('transactions.type', 'hms_booking')
                    ->select(
                        'transactions.*',
                        DB::raw('(SELECT SUM(total_price) FROM hms_booking_lines WHERE transaction_id = transactions.id) as room_price'),
                        DB::raw('(SELECT SUM(price) FROM hms_booking_extras WHERE transaction_id = transactions.id) as extra_price'),
                        'coupons.coupon_code', DB::raw('(SELECT SUM(IF(TP.is_return = 1,-1*TP.amount,TP.amount)) FROM transaction_payments AS TP WHERE
                    TP.transaction_id=transactions.id) as total_paid')
                    )
                    ->groupBy('transactions.id') // Group by transaction ID
                    ->findOrFail($id);

                $documentContext = $documents->stayContext(
                    HmsTransactionClass::where('business_id', $business_id)
                        ->where('type', 'hms_booking')
                        ->findOrFail($id),
                    'receipt'
                );
                $payments = $documents->accommodationPayments($transaction->payment_lines);
                $transaction->setRelation('payment_lines', $payments);
                $bookingExtraLines = HmsBookingExtra::where('transaction_id', $id)->with('extra')->get();
                $revenueBookingExtras = $bookingExtraLines->filter(
                    fn ($line) => ($line->financial_classification ?? 'revenue') === 'revenue'
                );
                $transaction->extra_price = round((float) $revenueBookingExtras->sum('price'), 4);
                $transaction->total_paid = round((float) $payments->sum(function ($payment) {
                    return $payment->is_return ? -(float) $payment->amount : (float) $payment->amount;
                }), 4);
                $securityDeposit = \App\SecurityDeposit::forBusiness((int) $business_id)
                    ->where('context_type', 'hms_booking')->where('context_id', $transaction->id)->with('entries')->first();

                $booking_rooms = HmsBookingLine::where('transaction_id', $id)
                    ->leftjoin('hms_rooms as room', 'room.id', '=', 'hms_booking_lines.hms_room_id')
                    ->leftjoin('hms_room_types as type', 'type.id', '=', 'hms_booking_lines.hms_room_type_id')
                    ->get();

                $extras_id = $revenueBookingExtras->pluck('hms_extra_id')->toArray();
                $extras = HmsExtra::where('business_id', $business_id)->whereIn('id', $extras_id)->get();

                // Get payment types for display
                $payment_types = $this->transactionUtil->payment_types(null, true, $business_id);

                // Calculate number of nights
                $no_of_nights = $this->countDaysBetweenDates($transaction->hms_booking_arrival_date_time, $transaction->hms_booking_departure_date_time);

                // Generate HTML content
                $html_content = view('hms::bookings.receipt_80mm', compact('business', 'transaction', 'booking_rooms', 'extras_id', 'extras', 'no_of_nights', 'payment_types', 'documentContext', 'securityDeposit'))->render();

                $receipt = [
                    'is_enabled' => true,
                    'html_content' => $html_content,
                    'print_title' => $documentContext['title'] . ' | ' . $transaction->ref_no
                ];

                $output = ['success' => 1, 'receipt' => $receipt];

            } catch (\Exception $e) {
                \Log::emergency('File:' . $e->getFile() . 'Line:' . $e->getLine() . 'Message:' . $e->getMessage());

                $output = ['success' => 0, 'msg' => trans('messages.something_went_wrong')];
            }

            return response()->json($output);
        } 
    }

    public function get_check_in_out($id)
    {

        $business_id = request()->session()->get('user.business_id');

        if (!(auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'hms_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if (! (auth()->user()->can('superadmin')
            || auth()->user()->can('hms.manage_front_desk')
            || auth()->user()->can('hms.edit_booking'))) {
            abort(403, 'Unauthorized action.');
        }

        $transaction = HmsTransactionClass::where('transactions.business_id', $business_id)
            ->with(['contact'])
            ->leftJoin('hms_booking_lines as hbl', 'transactions.id', '=', 'hbl.transaction_id')
            ->leftJoin('hms_booking_extras as hbe', 'transactions.id', '=', 'hbe.transaction_id')
            ->leftJoin('hms_coupons as coupons', 'transactions.hms_coupon_id', '=', 'coupons.id')
            ->where('transactions.type', 'hms_booking')
            ->select(
                'transactions.*',
                DB::raw('(SELECT SUM(total_price) FROM hms_booking_lines WHERE transaction_id = transactions.id) as room_price'),
                DB::raw('(SELECT SUM(price) FROM hms_booking_extras WHERE transaction_id = transactions.id) as extra_price'),
                'coupons.coupon_code', DB::raw('(SELECT SUM(IF(TP.is_return = 1,-1*TP.amount,TP.amount)) FROM transaction_payments AS TP WHERE
                            TP.transaction_id=transactions.id) as total_paid')
            )
            ->groupBy('transactions.id') // Group by transaction ID
            ->findOrFail($id);

        $extras_id = HmsBookingExtra::where('transaction_id', $id)->pluck('hms_extra_id')->toArray();

        $booking_rooms = HmsBookingLine::where('transaction_id', $id)
            ->leftjoin('hms_rooms as room', 'room.id', '=', 'hms_booking_lines.hms_room_id')
            ->leftjoin('hms_room_types as type', 'type.id', '=', 'hms_booking_lines.hms_room_type_id')
            ->get();

        $extras = HmsExtra::where('business_id', $business_id)->get();

        return view('hms::bookings.check_in_out', compact('extras', 'transaction', 'extras_id', 'booking_rooms'));

    }

    public function post_check_in_out(
        Request $request,
        $id
    )
    {
        $business_id = request()->session()->get('user.business_id');

        if (!(auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'hms_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if (! (auth()->user()->can('superadmin')
            || auth()->user()->can('hms.manage_front_desk')
            || auth()->user()->can('hms.edit_booking'))) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'in_out_date_time' => 'required|string|max:100',
        ]);

        try {
            $in_out_date_time = $this->commonUtil->uf_date(
                $request->in_out_date_time,
                true
            );

            $transaction = HmsTransactionClass::where('business_id', $business_id)
                ->where('type', 'hms_booking')
                ->findOrFail($id);

            if ($this->bookingLifecycleService->status($transaction) === 'reserved') {
                $this->bookingLifecycleService->checkIn(
                    (int) $business_id,
                    (int) $id,
                    Carbon::parse($in_out_date_time),
                    (int) auth()->id()
                );
            } else {
                $this->bookingLifecycleService->checkOut(
                    (int) $business_id,
                    (int) $id,
                    Carbon::parse($in_out_date_time),
                    (int) auth()->id()
                );
            }

            $output = [
                'success' => 1,
                'msg' => __('lang_v1.success'),
            ];

            return redirect()
                ->action([\Modules\Hms\Http\Controllers\HmsBookingController::class, 'index'])
                ->with('status', $output);

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            \Log::emergency('File:' . $e->getFile() . 'Line:' . $e->getLine() . 'Message:' . $e->getMessage());

            $output = [
                'success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];

            return back()->with('status', $output)->withInput();
        }
    }

    private function validateBookingRequest(
        Request $request,
        int $businessId,
        ?int $bookingId = null
    ): array
    {
        return $request->validate([
            'status' => ['required', Rule::in(['pending', 'confirmed', 'cancelled'])],
            'contact_id' => [
                'required',
                'integer',
                Rule::exists('contacts', 'id')->where('business_id', $businessId),
            ],
            'arrival_date' => 'required|string|max:50',
            'arrival_time' => 'required|string|max:50',
            'departure_date' => 'required|string|max:50',
            'departure_time' => 'required|string|max:50',
            'rooms' => 'required|array|min:1',
            'rooms.*.room_id' => 'required|integer',
            'rooms.*.type_id' => 'required|integer',
            'rooms.*.no_of_adult' => 'required|integer|min:1',
            'rooms.*.no_of_child' => 'required|integer|min:0',
            'extras' => 'nullable|array',
            'extras.*.id' => 'nullable|integer',
            'tax_rate_id' => 'nullable|integer',
            'coupon_code' => 'nullable|string|max:191',
            'discount_type' => ['nullable', Rule::in(['fixed', 'percentage'])],
            'total_discount' => 'nullable|numeric|min:0',
            'hms_property_id' => ['required', 'integer', Rule::exists('hms_properties', 'id')->where('business_id', $businessId)],
            'hms_rate_plan_id' => ['nullable', 'integer', Rule::exists('hms_rate_plans', 'id')->where('business_id', $businessId)],
            'hms_group_booking_id' => ['nullable', 'integer', Rule::exists('hms_group_bookings', 'id')->where('business_id', $businessId)],
            'hms_booking_source' => [
                'nullable',
                Rule::in([
                    'direct',
                    'walk_in',
                    'website',
                    'phone',
                    'email',
                    'ota',
                    'corporate',
                    'travel_agent',
                    'other',
                ]),
            ],
            'hms_external_reference' => [
                'nullable',
                'string',
                'max:191',
                Rule::unique('transactions', 'hms_external_reference')
                    ->where(fn ($query) => $query
                        ->where('business_id', $businessId)
                        ->where('type', 'hms_booking'))
                    ->ignore($bookingId),
            ],
            'hms_hold_expires_at' => 'nullable|date|after:now',
            'hms_special_requests' => 'nullable|string|max:5000',
            'hms_cancellation_reason' => 'nullable|required_if:status,cancelled|string|min:3|max:2000',
            'hms_reason_for_trip' => 'nullable|string|max:5000',
            'hms_means_of_transport' => 'nullable|string|max:50',
            'hms_vehicle_registration_number' => 'nullable|string|max:100',
            'hms_place_of_origin' => 'nullable|string|max:191',
            'hms_final_destination' => 'nullable|string|max:191',
            'preferred_language' => 'nullable|string|max:12',
            'nationality' => 'nullable|string|max:80',
            'date_of_birth' => 'nullable|date|before:today',
            'identity_document_type' => 'nullable|string|max:40',
            'identity_document_last_four' => 'nullable|string|max:8',
            'vip_level' => ['nullable', Rule::in(['standard', 'silver', 'gold', 'platinum'])],
            'preferences' => 'nullable|array',
            'preferences.*' => 'string|max:100',
            'accessibility_needs' => 'nullable|string|max:3000',
            'marketing_consent' => 'nullable|boolean',
            'do_not_contact' => 'nullable|boolean',
            'id_proof_1.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'id_proof_2.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'id_proof_3.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);
    }

    private function guestProfileInput(Request $request): array
    {
        return [
            'preferred_language' => $request->input('preferred_language'),
            'nationality' => $request->input('nationality'),
            'date_of_birth' => $request->input('date_of_birth'),
            'identity_document_type' => $request->input('identity_document_type'),
            'identity_document_last_four' => $request->input('identity_document_last_four'),
            'vip_level' => $request->input('vip_level'),
            'preferences' => array_values(array_filter((array) $request->input('preferences', []))),
            'accessibility_needs' => $request->input('accessibility_needs'),
            'marketing_consent' => $request->boolean('marketing_consent'),
            'do_not_contact' => $request->boolean('do_not_contact'),
            'consent_source' => 'reservation',
        ];
    }

    private function syncGroupRoomPickup(HmsTransactionClass $booking, array $roomLines): void
    {
        $previousBlocks = HmsGroupRoomBlock::where('business_id', $booking->business_id)
            ->where('transaction_id', $booking->id)
            ->get();
        foreach ($previousBlocks as $block) {
            $block->update([
                'transaction_id' => null,
                'status' => $block->release_at && $block->release_at->isPast() ? 'released' : 'held',
            ]);
        }

        if (! $booking->hms_group_booking_id) {
            return;
        }

        $roomIds = collect($roomLines)->pluck('hms_room_id')->map(fn ($id) => (int) $id)->unique();
        HmsGroupRoomBlock::where('business_id', $booking->business_id)
            ->where('hms_group_booking_id', $booking->hms_group_booking_id)
            ->whereIn('hms_room_id', $roomIds)
            ->whereIn('status', ['held', 'picked_up'])
            ->where(function ($query) {
                $query->whereNull('release_at')->orWhere('release_at', '>', now());
            })
            ->update(['transaction_id' => $booking->id, 'status' => 'picked_up']);
    }

    private function sanitizePaymentLines(array $lines): array
    {
        return array_map(function ($line) {
            // HMS never persists card verification codes or a full primary
            // account number. Gateways/terminals should supply only their
            // token or transaction reference in card_transaction_number.
            $line['card_security'] = null;
            $digits = preg_replace('/\D+/', '', (string) ($line['card_number'] ?? ''));
            $line['card_number'] = $digits === '' ? null : '****' . substr($digits, -4);
            $line['card_month'] = null;
            $line['card_year'] = null;
            $line['payment_purpose'] = 'payment_deposit';

            return $line;
        }, $lines);
    }

    private function authorizeBookingEditor(): void
    {
        if (! (auth()->user()->can('superadmin')
            || auth()->user()->can('hms.add_booking')
            || auth()->user()->can('hms.edit_booking'))) {
            abort(403, 'Unauthorized action.');
        }
    }

    private function authorizeBookingViewer(): void
    {
        $businessId = (int) request()->session()->get('user.business_id');

        if (! auth()->user()->can('superadmin')
            && (! $businessId || ! $this->moduleUtil->hasThePermissionInSubscription($businessId, 'hms_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if (! (auth()->user()->can('superadmin')
            || auth()->user()->can('hms.view_bookings')
            || auth()->user()->can('hms.add_booking')
            || auth()->user()->can('hms.edit_booking')
            || auth()->user()->can('hms.delete_booking')
            || auth()->user()->can('hms.front_desk')
            || auth()->user()->can('hms.manage_front_desk'))) {
            abort(403, 'Unauthorized action.');
        }
    }

    /**
     * Deletes a media file from storage and database.
     *
     * @param  int  $media_id
     * @return json
     */
    public function deleteMedia($media_id)
    {
        if (! (auth()->user()->can('superadmin') || auth()->user()->can('hms.edit_booking'))) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $business_id = request()->session()->get('user.business_id');

                $media = Media::where('business_id', $business_id)
                    ->whereIn('model_type', [HmsTransactionClass::class, Transaction::class])
                    ->findOrFail($media_id);

                HmsTransactionClass::where('business_id', $business_id)
                    ->where('type', 'hms_booking')
                    ->findOrFail($media->model_id);

                Media::deleteMedia($business_id, $media_id);

                $output = ['success' => true,
                    'msg' => __('lang_v1.file_deleted_successfully'),
                ];
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }
}
