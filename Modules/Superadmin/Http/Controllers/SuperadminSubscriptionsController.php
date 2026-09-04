<?php

namespace Modules\Superadmin\Http\Controllers;

use App\Services\SubscriptionPricingService;
use App\Utils\BusinessUtil;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Modules\Superadmin\Entities\Package;
use Modules\Superadmin\Entities\Subscription;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SuperadminSubscriptionsController extends BaseController
{
    protected $businessUtil;

    protected SubscriptionPricingService $pricing;

    /**
     * Constructor
     *
     * @param  BusinessUtil  $businessUtil
     * @return void
     */
    public function __construct(BusinessUtil $businessUtil, SubscriptionPricingService $pricing)
    {
        $this->businessUtil = $businessUtil;
        $this->pricing = $pricing;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }


        if (request()->ajax()) {
            $superadmin_subscription = Subscription::join('business', 'subscriptions.business_id', '=', 'business.id')
                ->join('packages', 'subscriptions.package_id', '=', 'packages.id')
                ->select('business.name as business_name', 'packages.name as package_name', 'subscriptions.status',
                 'subscriptions.created_at', 'subscriptions.start_date', 'subscriptions.trial_end_date', 'subscriptions.end_date', 'subscriptions.coupon_code','subscriptions.original_price', 'subscriptions.package_price', 'subscriptions.paid_via', 'subscriptions.payment_transaction_id', 'subscriptions.id');

            if(!empty(request()->input('status'))) {
                $superadmin_subscription->where('subscriptions.status', request()->input('status'));
            }
            if(!empty(request()->input('package_id'))) {
                $superadmin_subscription->where('packages.id', request()->input('package_id'));
            }

            if (!empty(request()->start_date) && !empty(request()->end_date)) {
                $start = request()->start_date;
                $end =  request()->end_date;
                $superadmin_subscription->whereDate('subscriptions.created_at', '>=', $start)
                    ->whereDate('subscriptions.created_at', '<=', $end);
            }
            
            return DataTables::of($superadmin_subscription)
                        ->addColumn(
                            'action',
                            '<button data-href ="{{action(\'\Modules\Superadmin\Http\Controllers\SuperadminSubscriptionsController@edit\',[$id])}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline  tw-dw-btn-info change_status" data-toggle="modal" data-target="#statusModal">
                            @lang( "superadmin::lang.status")
                            </button> <button data-href ="{{action(\'\Modules\Superadmin\Http\Controllers\SuperadminSubscriptionsController@editSubscription\',["id" => $id])}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline  tw-dw-btn-primary btn-modal tw-m-1" data-container=".view_modal">
                            @lang( "messages.edit")
                            </button>'
                        )
                        ->editColumn('created_at', '{{@format_datetime($created_at)}}')
                        ->editColumn('trial_end_date', '@if(!empty($trial_end_date)){{@format_date($trial_end_date)}} @endif')
                        ->editColumn('start_date', '@if(!empty($start_date)){{@format_date($start_date)}}@endif')
                        ->editColumn('end_date', '@if(!empty($end_date)){{@format_date($end_date)}}@endif')
                        ->editColumn(
                            'status',
                            '@if($status == "approved")
                                <span class="label bg-light-green">{{__(\'superadmin::lang.\'.$status)}}
                                </span>
                            @elseif($status == "waiting")
                                <span class="label bg-aqua">{{__(\'superadmin::lang.\'.$status)}}
                                </span>
                            @else($status == "declined")
                                <span class="label bg-red">{{__(\'superadmin::lang.\'.$status)}}
                                </span>
                            @endif'
                        )
                        ->editColumn(
                            'package_price',
                            '<span class="display_currency" data-currency_symbol="true">
                                {{$package_price}}
                            </span>'
                        )
                        ->editColumn(
                            'original_price',
                            '<span class="display_currency" data-currency_symbol="true">
                                {{$original_price}}
                            </span>'
                        )
                        ->removeColumn('id')
                        ->rawColumns([2, 8, 9, 12])
                        ->make(false);
        }

        $packages = Package::listPackages()->pluck('name', 'id');

        $subscription_statuses = [
            'approved' => __('superadmin::lang.approved'),
            'waiting' => __('superadmin::lang.waiting'),
            'declined' => __('superadmin::lang.declined'),
        ];

        return view('superadmin::superadmin_subscription.index')
                    ->with(compact('packages', 'subscription_statuses'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->input('business_id');
        $packages = Package::active()->orderby('sort_order')->pluck('name', 'id');

        $gateways = $this->_payment_gateways();

        return view('superadmin::superadmin_subscription.add_subscription')
              ->with(compact('packages', 'business_id', 'gateways'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->validate([
                'business_id' => ['required', 'integer', 'exists:business,id'],
                'package_id' => ['required', 'integer', 'exists:packages,id'],
                'paid_via' => ['nullable', 'string', 'max:100'],
                'payment_transaction_id' => ['nullable', 'string', 'max:255'],
            ]);
            $package = Package::active()->findOrFail($input['package_id']);
            $this->pricing->assertCanSubscribe($package, (int) $input['business_id']);
            $user_id = $request->session()->get('user.id');

            DB::transaction(function () use ($package, $input, $user_id) {
                $this->_add_subscription(null, $package->price, $input['business_id'], $package, $input['paid_via'] ?? null, $input['payment_transaction_id'] ?? null, $user_id, true);
            });

            $output = ['success' => 1,
                'msg' => __('lang_v1.success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0, 'msg' => __('messages.something_went_wrong')];
        }

        return back()->with('status', $output);
    }

    /**
     * Show the specified resource.
     *
     * @return Response
     */
    public function show()
    {
        return view('superadmin::show');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function edit($id)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $status = Subscription::package_subscription_status();
            $subscription = Subscription::findOrFail($id);

            return view('superadmin::superadmin_subscription.edit')
                        ->with(compact('subscription', 'status'));
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function update(Request $request, $id)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $input = $request->validate([
                    'status' => ['required', Rule::in(['approved', 'waiting', 'declined'])],
                    'payment_transaction_id' => ['nullable', 'string', 'max:255'],
                ]);

                DB::transaction(function () use ($id, $input) {
                    $subscription = Subscription::lockForUpdate()->findOrFail($id);
                    if ($subscription->status !== 'approved' && empty($subscription->start_date) && $input['status'] === 'approved') {
                        $this->pricing->assertResourceCompatibility($subscription->package, (int) $subscription->business_id);
                        $dates = $this->_get_package_dates($subscription->business_id, $this->packageTermSnapshot($subscription));
                        $subscription->start_date = $dates['start'];
                        $subscription->end_date = $dates['end'];
                        $subscription->trial_end_date = $dates['trial'];
                    }

                    $subscription->status = $input['status'];
                    $subscription->payment_transaction_id = $input['payment_transaction_id'] ?? null;
                    if (Schema::hasColumn('subscriptions', 'payment_dedupe_key')) {
                        $subscription->payment_dedupe_key = $this->paymentDedupeKey($subscription->paid_via, $subscription->payment_transaction_id);
                    }
                    $subscription->save();
                });

                $output = ['success' => true,
                    'msg' => __('superadmin::lang.subcription_updated_success'),
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

    /**
     * Remove the specified resource from storage.
     *
     * @return Response
     */
    public function destroy()
    {
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function editSubscription($id)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $subscription = Subscription::findOrFail($id);

            return view('superadmin::superadmin_subscription.edit_date_modal')
                        ->with(compact('subscription'));
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function updateSubscription(Request $request)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $input = $request->validate([
                    'subscription_id' => ['required', 'integer', 'exists:subscriptions,id'],
                    'start_date' => ['nullable', 'string', 'max:50'],
                    'end_date' => ['nullable', 'string', 'max:50'],
                    'trial_end_date' => ['nullable', 'string', 'max:50'],
                ]);

                $subscription = Subscription::findOrFail($request->input('subscription_id'));

                $startDate = ! empty($input['start_date']) ? $this->businessUtil->uf_date($input['start_date']) : null;
                $endDate = ! empty($input['end_date']) ? $this->businessUtil->uf_date($input['end_date']) : null;
                $trialEndDate = ! empty($input['trial_end_date']) ? $this->businessUtil->uf_date($input['trial_end_date']) : null;
                if ($startDate && $endDate && $endDate < $startDate) {
                    throw ValidationException::withMessages(['end_date' => 'The subscription end date must be on or after its start date.']);
                }
                if ($trialEndDate && (($startDate && $trialEndDate < $startDate) || ($endDate && $trialEndDate > $endDate))) {
                    throw ValidationException::withMessages(['trial_end_date' => 'The trial end date must fall within the subscription period.']);
                }

                $subscription->start_date = $startDate;
                $subscription->end_date = $endDate;
                $subscription->trial_end_date = $trialEndDate;
                $subscription->save();

                $output = ['success' => true,
                    'msg' => __('superadmin::lang.subcription_updated_success'),
                ];
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = ['success' => false,
                    'msg' => $e instanceof ValidationException
                        ? collect($e->errors())->flatten()->first()
                        : __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }
}
