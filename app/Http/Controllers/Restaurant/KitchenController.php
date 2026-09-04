<?php

namespace App\Http\Controllers\Restaurant;

use App\TransactionSellLine;
use App\Utils\RestaurantUtil;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class KitchenController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $commonUtil;

    protected $restUtil;

    /**
     * Constructor
     *
     * @param  Util  $commonUtil
     * @param  RestaurantUtil  $restUtil
     * @return void
     */
    public function __construct(Util $commonUtil, RestaurantUtil $restUtil)
    {
        $this->commonUtil = $commonUtil;
        $this->restUtil = $restUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $this->authorizeKitchenView();

        $business_id = request()->session()->get('user.business_id');
        
        $orders = $this->restUtil->getAllOrders($business_id, ['line_order_status' => 'received', 'is_kitchen_order' => 1]);

        return view('restaurant.kitchen.index', compact('orders'));
    }

    /**
     * Marks an order as cooked
     *
     * @return json $output
     */
    public function markAsCooked($id)
    {
        $this->authorizeKitchenManage();
        try {
            $business_id = request()->session()->get('user.business_id');
            $sl = TransactionSellLine::leftJoin('transactions as t', 't.id', '=', 'transaction_sell_lines.transaction_id')
                        ->where('t.business_id', $business_id)
                        ->where('transaction_id', $id)
                        ->where(function ($q) {
                            $q->whereNull('res_line_order_status')
                                ->orWhere('res_line_order_status', 'received');
                        })
                        ->update(['res_line_order_status' => 'cooked']);

            $output = $sl > 0
                ? ['success' => 1, 'msg' => trans('restaurant.order_successfully_marked_cooked')]
                : ['success' => 0, 'msg' => trans('messages.something_went_wrong')];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => trans('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Mark one sell line as cooked. This is intentionally separate from the
     * transaction-level action so a line id can never be mistaken for an
     * order id.
     */
    public function markLineAsCooked($id)
    {
        $this->authorizeKitchenManage();
        try {
            $business_id = request()->session()->get('user.business_id');
            $updated = TransactionSellLine::join('transactions as t', 't.id', '=', 'transaction_sell_lines.transaction_id')
                ->where('t.business_id', $business_id)
                ->where('transaction_sell_lines.id', $id)
                ->where(function ($query) {
                    $query->whereNull('transaction_sell_lines.res_line_order_status')
                        ->orWhere('transaction_sell_lines.res_line_order_status', 'received');
                })
                ->update(['transaction_sell_lines.res_line_order_status' => 'cooked']);

            $output = $updated
                ? ['success' => 1, 'msg' => trans('restaurant.order_successfully_marked_cooked')]
                : ['success' => 0, 'msg' => trans('messages.something_went_wrong')];
        } catch (\Throwable $e) {
            \Log::error('Unable to mark restaurant line as cooked.', ['line_id' => $id, 'error' => $e->getMessage()]);
            $output = ['success' => 0, 'msg' => trans('messages.something_went_wrong')];
        }

        return $output;
    }

    /**
     * Retrives fresh orders
     *
     * @return Json $output
     */
    public function refreshOrdersList(Request $request)
    {
        $this->authorizeKitchenView();
        $request->validate(['orders_for' => 'required|in:kitchen,waiter', 'service_staff_id' => 'nullable|integer']);
        $business_id = request()->session()->get('user.business_id');
        $orders_for = $request->orders_for;
        $filter = [];
        $service_staff_id = request()->session()->get('user.id');

        if (! $this->restUtil->is_service_staff($service_staff_id) && ! empty($request->input('service_staff_id'))) {
            $service_staff_id = $request->input('service_staff_id');
        }

        if ($orders_for == 'kitchen') {
            $filter['line_order_status'] = 'received';
            $filter['is_kitchen_order'] = 1;
        } elseif ($orders_for == 'waiter') {
            $filter['waiter_id'] = $service_staff_id;
        }

        $orders = $this->restUtil->getAllOrders($business_id, $filter);

        return view('restaurant.partials.show_orders', compact('orders', 'orders_for'));
    }

    /**
     * Retrives fresh orders
     *
     * @return Json $output
     */
    public function refreshLineOrdersList(Request $request)
    {
        $this->authorizeKitchenView();
        $request->validate(['orders_for' => 'required|in:kitchen,waiter', 'service_staff_id' => 'nullable|integer']);
        $business_id = request()->session()->get('user.business_id');
        $orders_for = $request->orders_for;
        $filter = [];
        $service_staff_id = request()->session()->get('user.id');

        if (! $this->restUtil->is_service_staff($service_staff_id) && ! empty($request->input('service_staff_id'))) {
            $service_staff_id = $request->input('service_staff_id');
        }

        if ($orders_for == 'kitchen') {
            $filter['order_status'] = 'received';
        } elseif ($orders_for == 'waiter') {
            $filter['waiter_id'] = $service_staff_id;
        }

        $line_orders = $this->restUtil->getLineOrders($business_id, $filter);

        return view('restaurant.partials.line_orders', compact('line_orders', 'orders_for'));
    }

    private function authorizeKitchenView(): void
    {
        if (! auth()->user()->can('restaurant.kitchen.view') && ! auth()->user()->can('sell.view')) {
            abort(403, 'Unauthorized action.');
        }
    }

    private function authorizeKitchenManage(): void
    {
        if (! auth()->user()->can('restaurant.kitchen.manage') && ! auth()->user()->can('sell.update')) {
            abort(403, 'Unauthorized action.');
        }
    }
}
