<?php

namespace Modules\Superadmin\Http\Controllers;

use App\Http\Requests\SaveSubscriptionPackageRequest;
use App\Services\SubscriptionPricingService;
use App\Business;
use App\System;
use App\Utils\BusinessUtil;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Modules\Superadmin\Entities\Package;
use Modules\Superadmin\Entities\Subscription;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class PackagesController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $businessUtil;

    protected $moduleUtil;

    protected SubscriptionPricingService $pricing;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(BusinessUtil $businessUtil, ModuleUtil $moduleUtil, SubscriptionPricingService $pricing)
    {
        $this->businessUtil = $businessUtil;
        $this->moduleUtil = $moduleUtil;
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

        $packages = Package::orderby('sort_order', 'asc')
                    ->paginate(20);

        //Get all module permissions and convert them into name => label
        $permissions = $this->moduleUtil->getModuleData('superadmin_package');
        $permission_formatted = [];
        foreach ($permissions as $permission) {
            foreach ($permission as $details) {
                $permission_formatted[$details['name']] = $details['label'];
            }
        }

        return view('superadmin::packages.index')
            ->with(compact('packages', 'permission_formatted'));
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

        $intervals = ['days' => __('lang_v1.days'), 'months' => __('lang_v1.months'), 'years' => __('lang_v1.years')];
        $currency = System::getCurrency();
        $businesses = Business::get()->pluck('name', 'id');

        $permissions = $this->moduleUtil->getModuleData('superadmin_package');

        return view('superadmin::packages.create')
            ->with(compact('intervals', 'currency', 'permissions', 'businesses'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(SaveSubscriptionPackageRequest $request)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        $currency = System::getCurrency();
        $normalizedPrice = $this->businessUtil->num_uf($request->input('price'), $currency);
        if (! is_numeric($normalizedPrice) || $normalizedPrice < 0) {
            throw ValidationException::withMessages(['price' => 'Enter a valid non-negative package price.']);
        }

        try {
            DB::beginTransaction();
            $input = $request->only(['name', 'description', 'max_businesses', 'location_count', 'user_count', 'product_count', 'invoice_count', 'data_import_enabled', 'monthly_import_rows', 'max_import_rows_per_file', 'max_import_file_size_mb', 'concurrent_imports', 'import_rollback_days', 'interval', 'interval_count', 'trial_days', 'price', 'sort_order', 'is_active', 'mark_package_as_popular', 'custom_permissions', 'is_private', 'is_one_time', 'enable_custom_link', 'custom_link',
                'custom_link_text', 'businesses' ]);
            $input['price'] = $normalizedPrice;
            $input['is_active'] = empty($input['is_active']) ? 0 : 1;
            $input['mark_package_as_popular'] = empty($input['mark_package_as_popular']) ? 0 : 1;
            $input['created_by'] = $request->session()->get('user.id');

            $input['is_private'] = empty($input['is_private']) ? 0 : 1;
            $input['is_one_time'] = empty($input['is_one_time']) ? 0 : 1;
            $input['enable_custom_link'] = empty($input['enable_custom_link']) ? 0 : 1;
            $input['data_import_enabled'] = $request->boolean('data_import_enabled');

            $input['custom_link'] = empty($input['enable_custom_link']) ? '' : $input['custom_link'];
            $input['custom_link_text'] = empty($input['enable_custom_link']) ? '' : $input['custom_link_text'];

            $input['businesses'] = empty($input['businesses']) ? null : array_map('intval', $input['businesses']);

            $package = new Package;
            $package->fill($input);
            $package->save();
            DB::commit();

            $output = ['success' => 1, 'msg' => __('lang_v1.success')];
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => $e instanceof ValidationException
                    ? collect($e->errors())->flatten()->first()
                    : __('messages.something_went_wrong'),
            ];
        }

        return redirect()
            ->action([\Modules\Superadmin\Http\Controllers\PackagesController::class, 'index'])
            ->with('status', $output);
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

        $packages = Package::findOrFail($id);

        $intervals = ['days' => __('lang_v1.days'), 'months' => __('lang_v1.months'), 'years' => __('lang_v1.years')];

        $permissions = $this->moduleUtil->getModuleData('superadmin_package', true);
        $businesses = Business::get()->pluck('name', 'id');

        return view('superadmin::packages.edit')
               ->with(compact('packages', 'intervals', 'permissions', 'businesses'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function update(SaveSubscriptionPackageRequest $request, $id)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        $currency = System::getCurrency();
        $normalizedPrice = $this->businessUtil->num_uf($request->input('price'), $currency);
        if (! is_numeric($normalizedPrice) || $normalizedPrice < 0) {
            throw ValidationException::withMessages(['price' => 'Enter a valid non-negative package price.']);
        }

        try {
            DB::beginTransaction();
            $packages_details = $request->only(['name', 'id', 'description', 'max_businesses', 'location_count', 'user_count', 'product_count', 'invoice_count', 'data_import_enabled', 'monthly_import_rows', 'max_import_rows_per_file', 'max_import_file_size_mb', 'concurrent_imports', 'import_rollback_days', 'interval', 'interval_count', 'trial_days', 'price', 'sort_order', 'is_active', 'mark_package_as_popular', 'custom_permissions', 'is_private', 'is_one_time', 'enable_custom_link', 'custom_link', 'custom_link_text', 'businesses']);

            $packages_details['is_active'] = empty($packages_details['is_active']) ? 0 : 1;
            $packages_details['mark_package_as_popular'] = empty($packages_details['mark_package_as_popular']) ? 0 : 1;
            $packages_details['price'] = $normalizedPrice;
            $packages_details['custom_permissions'] = empty($packages_details['custom_permissions']) ? null : $packages_details['custom_permissions'];

            $packages_details['is_private'] = empty($packages_details['is_private']) ? 0 : 1;
            $packages_details['is_one_time'] = empty($packages_details['is_one_time']) ? 0 : 1;
            $packages_details['enable_custom_link'] = empty($packages_details['enable_custom_link']) ? 0 : 1;
            $packages_details['data_import_enabled'] = $request->boolean('data_import_enabled');
            $packages_details['custom_link'] = empty($packages_details['enable_custom_link']) ? '' : $packages_details['custom_link'];
            $packages_details['custom_link_text'] = empty($packages_details['enable_custom_link']) ? '' : $packages_details['custom_link_text'];

            $packages_details['businesses'] = empty($packages_details['businesses']) ? null : array_map('intval', $packages_details['businesses']);

            $package = Package::findOrFail($id);
            $package->fill($packages_details);
            $package->save();

            if (! empty($request->input('update_subscriptions'))) {
                $activeBusinessIds = Subscription::where('package_id', $package->id)
                    ->whereNull('covered_by_subscription_id')
                    ->where('status', 'approved')
                    ->whereDate('end_date', '>=', now())
                    ->distinct()
                    ->pluck('business_id');
                foreach ($activeBusinessIds as $activeBusinessId) {
                    $this->pricing->assertResourceCompatibility($package, (int) $activeBusinessId, true);
                }

                $package_details = [
                    'max_businesses' => $package->max_businesses,
                    'location_count' => $package->location_count,
                    'user_count' => $package->user_count,
                    'product_count' => $package->product_count,
                    'invoice_count' => $package->invoice_count,
                    'data_import_enabled' => (bool) $package->data_import_enabled,
                    'monthly_import_rows' => (int) $package->monthly_import_rows,
                    'max_import_rows_per_file' => (int) $package->max_import_rows_per_file,
                    'max_import_file_size_mb' => (int) $package->max_import_file_size_mb,
                    'concurrent_imports' => (int) $package->concurrent_imports,
                    'import_rollback_days' => (int) $package->import_rollback_days,
                    'name' => $package->name,
                    'price' => (float) $package->price,
                    'interval' => $package->interval,
                    'interval_count' => (int) $package->interval_count,
                    'trial_days' => (int) $package->trial_days,
                    'is_one_time' => (bool) $package->is_one_time,
                    'currency_code' => strtoupper(System::getCurrency()->code),
                ];
                if (! empty($package->custom_permissions)) {
                    foreach ($package->custom_permissions as $name => $value) {
                        $package_details[$name] = $value;
                    }
                }

                //Update subscription package details
                $subscriptions = Subscription::where('package_id', $package->id)
                                            ->whereDate('end_date', '>=', \Carbon::now())
                                            ->update(['package_details' => json_encode($package_details)]);
            }

            DB::commit();

            $output = ['success' => 1, 'msg' => __('lang_v1.success')];
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => $e instanceof ValidationException
                    ? collect($e->errors())->flatten()->first()
                    : __('messages.something_went_wrong'),
            ];
        }

        return redirect()
            ->action([\Modules\Superadmin\Http\Controllers\PackagesController::class, 'index'])
            ->with('status', $output);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return Response
     */
    public function destroy($id)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            Package::where('id', $id)
                ->delete();

            $output = ['success' => 1, 'msg' => __('lang_v1.success')];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect()
            ->action([\Modules\Superadmin\Http\Controllers\PackagesController::class, 'index'])
            ->with('status', $output);
    }
}
