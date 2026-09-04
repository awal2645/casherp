<?php

namespace Modules\Superadmin\Http\Controllers;

use App\System;
use App\Utils\ModuleUtil;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\Superadmin\Entities\Package;
use App\PremiumModulePlan;
use Illuminate\Http\Request;

class PricingController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $moduleUtil;

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct(ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $packages = Package::listPackages(true)
            ->filter(fn (Package $package) => empty((array) $package->businesses))
            ->values();
        $packages->load(['premiumModules' => function ($query) {
            $query->where('premium_module_plans.is_active', true)
                ->orderBy('premium_module_plans.sort_order')
                ->orderBy('premium_module_plans.name');
        }]);

        //Get all module permissions and convert them into name => label
        $permissions = $this->moduleUtil->getModuleData('superadmin_package');
        $permission_formatted = [];
        foreach ($permissions as $permission) {
            foreach ($permission as $details) {
                $permission_formatted[$details['name']] = $details['label'];
            }
        }

        $premiumModules = PremiumModulePlan::where('is_active', true)
            ->orderBy('sort_order')
            ->get();
        $systemCurrency = System::getCurrency();
        $industryCode = (string) $request->query('industry', '');
        $allowedIndustries = [
            'general_business',
            'restaurant_food_service',
            'hotel_lodge_guesthouse',
            'hotel_with_restaurant',
            'property_management_rentals',
            'professional_services',
        ];
        $registrationContext = array_filter([
            'business_name' => Str::limit(trim((string) $request->query('business_name', '')), 255, ''),
            'industry' => in_array($industryCode, $allowedIndustries, true) ? $industryCode : null,
            'country' => Str::limit(trim((string) $request->query('country', '')), 100, ''),
        ], static fn ($value) => $value !== null && $value !== '');

        return view('landing.pricing')
            ->with(compact('packages', 'permission_formatted', 'premiumModules', 'systemCurrency', 'registrationContext'));
    }

    public function package_duration_update(Request $request)
    {
        $validated = $request->validate([
            'interval' => ['required', 'in:days,months,years'],
        ]);
        $interval = $validated['interval'];

        $packages = Package::listPackages(true, $interval)
            ->filter(fn (Package $package) => empty((array) $package->businesses))
            ->values();
        $packages->load(['premiumModules' => function ($query) {
            $query->where('premium_module_plans.is_active', true)
                ->orderBy('premium_module_plans.sort_order')
                ->orderBy('premium_module_plans.name');
        }]);

        //Get all module permissions and convert them into name => label
        $permissions = $this->moduleUtil->getModuleData('superadmin_package');
        $permission_formatted = [];
        foreach ($permissions as $permission) {
            foreach ($permission as $details) {
                $permission_formatted[$details['name']] = $details['label'];
            }
        }

        $action_type = 'register';

        return view('superadmin::subscription.partials.packages')
        ->with(compact('packages', 'permission_formatted', 'action_type'));

        
    }
}
