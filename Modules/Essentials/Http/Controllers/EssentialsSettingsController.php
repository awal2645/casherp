<?php

namespace Modules\Essentials\Http\Controllers;

use App\Business;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use App\System;
use Modules\Essentials\Http\Controllers\Concerns\AuthorizesHrmRequests;

class EssentialsSettingsController extends Controller
{
    use AuthorizesHrmRequests;

    /**
     * All Utils instance.
     */
    protected $moduleUtil;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function edit()
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, []);

        $settings = request()->session()->get('business.essentials_settings');
        $settings = ! empty($settings) ? json_decode($settings, true) : [];
        $module_version = System::getProperty('essentials_version');
        return view('essentials::settings.add')->with(compact('settings', 'module_version'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function update(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, []);

        try {
            $input = $request->validate([
                'leave_ref_no_prefix' => ['nullable', 'string', 'max:30'],
                'leave_instructions' => ['nullable', 'string', 'max:5000'],
                'payroll_ref_no_prefix' => ['nullable', 'string', 'max:30'],
                'essentials_todos_prefix' => ['nullable', 'string', 'max:30'],
                'grace_before_checkin' => ['nullable', 'integer', 'min:0', 'max:1440'],
                'grace_after_checkin' => ['nullable', 'integer', 'min:0', 'max:1440'],
                'grace_before_checkout' => ['nullable', 'integer', 'min:0', 'max:1440'],
                'grace_after_checkout' => ['nullable', 'integer', 'min:0', 'max:1440'],
                'is_location_required' => ['nullable', 'boolean'],
                'calculate_sales_target_commission_without_tax' => ['nullable', 'boolean'],
            ]);
            $input['is_location_required'] = ! empty($input['is_location_required']) ? 1 : 0;
            $input['calculate_sales_target_commission_without_tax'] = ! empty($input['calculate_sales_target_commission_without_tax']) ? 1 : 0;

            $business = Business::findOrFail($business_id);
            $business->essentials_settings = json_encode($input);
            $business->save();

            $request->session()->put('business', $business);

            $output = ['success' => 1,
                'msg' => trans('lang_v1.updated_succesfully'),
            ];
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => trans('messages.something_went_wrong'),
            ];
        }

        return redirect()->back()->with(['status' => $output]);
    }
}
