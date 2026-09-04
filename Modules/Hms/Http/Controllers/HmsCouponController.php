<?php

namespace Modules\Hms\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Hms\Entities\HmsRoomType;
use Modules\Hms\Entities\HmsCoupon;
use App\Utils\Util;
use Yajra\DataTables\Facades\DataTables;
use App\Utils\ModuleUtil;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;


class HmsCouponController extends Controller
{
    protected $commonUtil;
    protected $moduleUtil;

    public function __construct(
        Util $commonUtil, ModuleUtil $moduleUtil

    ) {
        $this->commonUtil = $commonUtil;
        $this->moduleUtil = $moduleUtil;
    }
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index()
    {

        $business_id = request()->session()->get('user.business_id');
        
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'hms_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if(!auth()->user()->can( 'hms.manage_coupon')){
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $extra = HmsCoupon::where('hms_coupons.business_id', $business_id)
                     ->leftjoin('hms_room_types as type', 'type.id', '=', 'hms_coupons.hms_room_type_id')
                     ->select(['hms_coupons.*', 'type.type as type']);
            return Datatables::of($extra)
                ->editColumn('created_at', '{{@format_datetime($created_at)}}')
                ->addColumn('action', function ($row) {
                    $html = '<a type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline  tw-dw-btn-primary btn-modal-coupon" href="' . action([\Modules\Hms\Http\Controllers\HmsCouponController::class, 'edit'], ['coupon' => $row->id]) . '">'
                        . __('hms::lang.edit_coupon') . '</a>';
                    $html .= '<form method="POST" action="'.route('hms.delete_coupon', ['id' => $row->id]).'" class="tw-inline-block hms-delete-form">'
                        .csrf_field().method_field('DELETE')
                        .'<button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-error delete_coupon_confirmation">'
                        .__('messages.delete').'</button></form>';

                    return $html;
                })
                ->editColumn('start_date', '{{@format_date($start_date)}}')
                ->editColumn('end_date', '{{@format_date($end_date)}}')

                ->rawColumns(['created_at', 'action', 'start_date', 'end_date'])
                ->make(true);
        }

        return view('hms::coupons.index');
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create()
    {    
        $business_id = request()->session()->get('user.business_id');
        
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'hms_module'))) {
            abort(403, 'Unauthorized action.');
        }
        if(!auth()->user()->can( 'hms.manage_coupon')){
            abort(403, 'Unauthorized action.');
        }

        $types = HmsRoomType::where('business_id', $business_id)->pluck('type', 'id')->toArray();

        $discount_type = [
            'fixed' => "Fixed",
            'percentage' => "Percentage",
        ];

        return view('hms::coupons.create', compact('types', 'discount_type'));
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'hms_module'))) {
            abort(403, 'Unauthorized action.');
        }
        if(!auth()->user()->can( 'hms.manage_coupon')){
            abort(403, 'Unauthorized action.');
        }

        try{
            $input = $this->validatedInput($request, (int) $business_id);
            $input['business_id'] = $business_id;
            $input['start_date'] = $this->commonUtil->uf_date($input['start_date']);
            $input['end_date'] = $this->commonUtil->uf_date($input['end_date']);
            $this->assertDateRange($input['start_date'], $input['end_date']);
            HmsCoupon::create($input);

            $output = [
                'success' => 1,
                'msg' => __('lang_v1.success'),
            ];

            return redirect()
                ->action([\Modules\Hms\Http\Controllers\HmsCouponController::class, 'index'])
                ->with('status', $output);

        } catch (ValidationException $e) {
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

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        return view('hms::show');
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        $business_id = request()->session()->get('user.business_id');
        
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'hms_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if(!auth()->user()->can( 'hms.manage_coupon')){
            abort(403, 'Unauthorized action.');
        }

        $coupon = HmsCoupon::where('business_id', $business_id)->findOrFail($id);

        $business_id = request()->session()->get('user.business_id');
        $types = HmsRoomType::where('business_id', $business_id)->pluck('type', 'id')->toArray();

        $discount_type = [
            'fixed' => "Fixed",
            'percentage' => "Percentage",
        ];

        return view('hms::coupons.edit', compact('coupon', 'discount_type', 'types'));
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
        
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'hms_module'))) {
            abort(403, 'Unauthorized action.');
        }
        if(!auth()->user()->can( 'hms.manage_coupon')){
            abort(403, 'Unauthorized action.');
        }

        try{

            $input = $this->validatedInput($request, (int) $business_id, (int) $id);
            $input['start_date'] = $this->commonUtil->uf_date($input['start_date']);
            $input['end_date'] = $this->commonUtil->uf_date($input['end_date']);
            $this->assertDateRange($input['start_date'], $input['end_date']);
            $coupon = HmsCoupon::where('business_id', $business_id)->findOrFail($id);
            $coupon->update($input);

            $output = [
                'success' => 1,
                'msg' => __('lang_v1.success'),
            ];

            return redirect()
                ->action([\Modules\Hms\Http\Controllers\HmsCouponController::class, 'index'])
                ->with('status', $output);

        } catch (ValidationException $e) {
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

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        $business_id = request()->session()->get('user.business_id');
        
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'hms_module'))) {
            abort(403, 'Unauthorized action.');
        }
        if(!auth()->user()->can( 'hms.manage_coupon')){
            abort(403, 'Unauthorized action.');
        }
        
        
        try {

            HmsCoupon::where('business_id', $business_id)->findOrFail($id)->delete();

            $output = ['success' => 1, 'msg' => __('lang_v1.success')];
            return redirect()
                ->action([\Modules\Hms\Http\Controllers\HmsCouponController::class, 'index'])
                ->with('status', $output);
        } catch (\Exception $e) {
            \Log::emergency('File:' . $e->getFile() . 'Line:' . $e->getLine() . 'Message:' . $e->getMessage());

            $output = [
                'success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];

            return back()->with('status', $output)->withInput();
        }
    }

    public function get_coupon_discount(Request $request){ 

        $business_id = (int) request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin')
            || auth()->user()->can('hms.add_booking')
            || auth()->user()->can('hms.edit_booking'))) {
            abort(403, 'Unauthorized action.');
        }
        $request->validate([
            'coupon_code' => 'required|string|max:191',
            'booking_date' => 'required|string|max:50',
        ]);

        $booking_date = $this->commonUtil->uf_date($request->booking_date);

        $coupon = HmsCoupon::where('business_id', $business_id)
            ->where('coupon_code', $request->coupon_code)
            ->whereDate('start_date', '<=', $booking_date)
            ->whereDate('end_date', '>=', $booking_date)
            ->first();

        if($coupon){
            $data = [
                'status' => 1,
                'coupon' => [
                    'id' => $coupon->id,
                    'hms_room_type_id' => $coupon->hms_room_type_id,
                    'discount' => $coupon->discount,
                    'discount_type' => strtolower($coupon->discount_type),
                ],
                'msg' => __('lang_v1.success'),
            ];
            return $data;
        }else{
            $data = ['status'=>0, 'msg' => __('messages.something_went_wrong')];
            return $data;
        }
    }

    private function validatedInput(Request $request, int $businessId, ?int $couponId = null): array
    {
        $validated = $request->validate([
            'hms_room_type_id' => [
                'required',
                'integer',
                Rule::exists('hms_room_types', 'id')->where('business_id', $businessId),
            ],
            'start_date' => 'required|string|max:50',
            'end_date' => 'required|string|max:50',
            'coupon_code' => [
                'required',
                'string',
                'max:191',
                Rule::unique('hms_coupons', 'coupon_code')
                    ->where('business_id', $businessId)
                    ->ignore($couponId),
            ],
            'discount' => 'required|numeric|min:0',
            'discount_type' => ['required', Rule::in(['fixed', 'percentage'])],
        ]);

        if ($validated['discount_type'] === 'percentage' && (float) $validated['discount'] > 100) {
            throw ValidationException::withMessages([
                'discount' => __('hms::lang.percentage_discount_limit'),
            ]);
        }

        return $validated;
    }

    private function assertDateRange(string $startDate, string $endDate): void
    {
        if ($endDate < $startDate) {
            throw ValidationException::withMessages([
                'end_date' => __('hms::lang.end_date_after_start'),
            ]);
        }
    }
}
     
