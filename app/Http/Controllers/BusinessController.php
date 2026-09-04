<?php

namespace App\Http\Controllers;

use App\Business;
use App\Currency;
use App\Industry;
use App\RegistrationIntent;
use App\Http\Requests\RegisterBusinessRequest;
use App\Services\IndustryFeatureProvisioningService;
use App\Services\IndustryOnboardingService;
use App\Services\GoogleSocialIdentityService;
use App\System;
use App\TaxRate;
use App\Unit;
use App\User;
use App\Utils\BusinessUtil;
use App\Utils\ModuleUtil;
use App\Utils\RestaurantUtil;
use Carbon\Carbon;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Validator;

class BusinessController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | BusinessController
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new business/business as well as their
    | validation and creation.
    |
    */

    /**
     * All Utils instance.
     */
    protected $businessUtil;

    protected $restaurantUtil;

    protected $moduleUtil;

    protected $industryFeatureProvisioningService;

    protected $industryOnboardingService;

    protected $googleIdentity;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(
        BusinessUtil $businessUtil,
        RestaurantUtil $restaurantUtil,
        ModuleUtil $moduleUtil,
        IndustryFeatureProvisioningService $industryFeatureProvisioningService,
        IndustryOnboardingService $industryOnboardingService,
        GoogleSocialIdentityService $googleIdentity
    )
    {
        $this->businessUtil = $businessUtil;
        $this->moduleUtil = $moduleUtil;
        $this->industryFeatureProvisioningService = $industryFeatureProvisioningService;
        $this->industryOnboardingService = $industryOnboardingService;
        $this->googleIdentity = $googleIdentity;

        $this->theme_colors = [
            'primary' => 'Blue',
            'indigo'  => 'Indigo',
            'violet'  => 'Violet',
            'purple'  => 'Purple',
            'teal'    => 'Teal',
            'emerald' => 'Emerald',
            'green'   => 'Green',
            'sky'     => 'Sky',
            'pink'    => 'Pink',
            'rose'    => 'Rose',
            'red'     => 'Red',
            'orange'  => 'Orange',
            'yellow'  => 'Yellow',
            'slate'   => 'Slate',
        ];

    }

    /**
     * Shows registration form
     *
     * @return \Illuminate\Http\Response
     */
    public function getRegister(Request $request)
    {
        if (! config('constants.allow_registration')) {
            return redirect('/');
        }

        $currencies = $this->businessUtil->allCurrencies();

        $timezone_list = $this->businessUtil->allTimeZones();

        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[$i] = __('business.months.'.$i);
        }

        $accounting_methods = $this->businessUtil->allAccountingMethods();
        $package_id = request()->input('package');
        $selected_package = null;
        if ($this->moduleUtil->isSuperadminInstalled()) {
            $publicPackagesExist = \Modules\Superadmin\Entities\Package::active()
                ->publiclyAvailable()
                ->exists();

            if (empty($package_id) && $publicPackagesExist) {
                return redirect()->route('pricing')->with('status', [
                    'success' => 0,
                    'msg' => 'Select a package before creating your company.',
                ]);
            }

            if (! empty($package_id)) {
                $selected_package = \Modules\Superadmin\Entities\Package::active()
                    ->publiclyAvailable()
                    ->find($package_id);
                if (empty($selected_package)) {
                    return redirect()->route('pricing')->with('status', [
                        'success' => 0,
                        'msg' => 'The selected package is unavailable. Please choose another package.',
                    ]);
                }
                $package_id = $selected_package->id;
            }
        }

        $industryModels = $this->industryOnboardingService->selectableIndustries();
        $industries = $industryModels->pluck('name', 'id');
        $industryProfiles = $this->industryOnboardingService->profilePayload($industryModels);
        $requestedIndustry = $industryModels->firstWhere('code', (string) $request->query('industry', ''));
        $pendingGoogle = $this->googleIdentity->pending($request);
        $registrationIntent = Schema::hasTable('registration_intents') && $request->session()->has('registration_intent_id')
            ? RegistrationIntent::whereKey($request->session()->get('registration_intent_id'))->whereNull('completed_at')->first()
            : null;
        if (! $requestedIndustry && $registrationIntent) {
            $requestedIndustry = $industryModels->firstWhere('code', $registrationIntent->industry_code);
        }
        $registrationPrefill = [
            'name' => Str::limit(trim((string) ($request->query('business_name') ?: $registrationIntent?->business_name)), 255, ''),
            'industry_id' => $requestedIndustry?->id,
            'country' => Str::limit(trim((string) ($request->query('country') ?: $registrationIntent?->country)), 100, ''),
            'first_name' => $pendingGoogle['first_name'] ?? null,
            'last_name' => $pendingGoogle['last_name'] ?? null,
            'email' => $pendingGoogle['email'] ?? $registrationIntent?->email,
            'username' => $pendingGoogle ? $this->availableUsername($pendingGoogle['email']) : null,
            'social_provider' => $pendingGoogle['provider'] ?? null,
        ];

        $system_settings = System::getProperties(['superadmin_enable_register_tc', 'superadmin_register_tc'], true);

        return view('business.register', compact(
            'currencies',
            'timezone_list',
            'months',
            'accounting_methods',
            'package_id',
            'industries',
            'industryProfiles',
            'registrationPrefill',
            'selected_package',
            'system_settings'
        ));
    }

    /**
     * Handles the registration of a new business and it's owner
     *
     * @return \Illuminate\Http\Response
     */
    public function postRegister(RegisterBusinessRequest $request)
    {
        if (! config('constants.allow_registration')) {
            return redirect('/');
        }

        $data = $request->validated();
        $pendingGoogle = $this->googleIdentity->pending($request);
        $industry = Industry::whereKey($data['industry_id'])
            ->where('is_active', true)
            ->firstOrFail();
        $answers = $this->industryOnboardingService->sanitizeAnswers(
            $industry,
            $data['onboarding'] ?? []
        );

        try {
            DB::beginTransaction();

            //Create owner.
            $owner_details = collect($data)->only([
                'surname', 'first_name', 'last_name', 'username', 'email', 'password', 'language',
            ])->all();

            if ($pendingGoogle) {
                $owner_details['email'] = $pendingGoogle['email'];
                $owner_details['password'] = Str::password(48);
            }

            $owner_details['language'] = empty($owner_details['language']) ? config('app.locale') : $owner_details['language'];

            $user = User::create_user($owner_details);
            if ($pendingGoogle) {
                $this->googleIdentity->attach($user, $pendingGoogle);
            }

            $business_details = collect($data)->only(['name', 'start_date', 'currency_id', 'time_zone',
                'fy_start_month', 'accounting_method', 'tax_label_1', 'tax_number_1',
                'tax_label_2', 'tax_number_2', 'industry_id'])->all();

            $business_location = collect($data)->only(['name', 'country', 'state', 'city', 'zip_code', 'landmark',
                'website', 'mobile', 'alternate_number', ])->all();

            //Create the business
            $business_details['owner_id'] = $user->id;
            if (! empty($business_details['start_date'])) {
                $business_details['start_date'] = Carbon::createFromFormat(config('constants.default_date_format'), $business_details['start_date'])->toDateString();
            }

            //upload logo
            $logo_name = $this->businessUtil->uploadFile($request, 'business_logo', 'business_logos', 'image');
            if (! empty($logo_name)) {
                $business_details['logo'] = $logo_name;
            }

            // The selected industry is the source of the first feature profile.
            $business_details['enabled_modules'] = $this->industryFeatureProvisioningService->defaultCoreModules($industry, $answers);
            $business_details['onboarding_settings'] = [
                'version' => 2,
                'source' => 'registration',
                'industry_code' => $industry->code,
                'answers' => $answers,
            ];

            $business = $this->businessUtil->createNewBusiness($business_details);
            $this->industryFeatureProvisioningService->provision($business, $industry);

            //Update user with business id
            $user->business_id = $business->id;
            $user->save();

            $this->businessUtil->newBusinessDefaultResources($business->id, $user->id);
            $new_location = $this->businessUtil->addLocation($business->id, $business_location);

            //create new permission with the new location
            Permission::firstOrCreate(['name' => 'location.'.$new_location->id]);

            if (Schema::hasTable('registration_intents') && $request->session()->has('registration_intent_id')) {
                RegistrationIntent::whereKey($request->session()->get('registration_intent_id'))
                    ->whereNull('completed_at')
                    ->update([
                        'last_step' => 'completed',
                        'last_activity_at' => now(),
                        'next_reminder_at' => null,
                        'completed_at' => now(),
                        'completed_user_id' => $user->id,
                        'completed_business_id' => $business->id,
                        'updated_at' => now(),
                    ]);
            }
            DB::commit();
            $request->session()->forget('registration_intent_id');
            if ($pendingGoogle) {
                $this->googleIdentity->clear($request);
            }
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];

            return back()->with('status', $output)->withInput();
        }

        // A post-commit addon notification failure must not turn a successful
        // registration into an apparent failure that encourages a duplicate retry.
        if (config('app.env') !== 'demo') {
            try {
                $this->moduleUtil->getModuleData('after_business_created', ['business' => $business]);
            } catch (\Throwable $exception) {
                \Log::error('Business registered but an addon onboarding hook failed.', [
                    'business_id' => $business->id,
                    'exception' => $exception,
                ]);
            }
        }

        $package_id = $data['package_id'] ?? null;
        if ($this->moduleUtil->isSuperadminInstalled()
            && ! empty($package_id)
            && config('app.env') !== 'demo') {
            Auth::login($user);

            return redirect()->route('register-pay', ['package_id' => $package_id]);
        }

        if ($pendingGoogle) {
            Auth::login($user);
            $request->session()->regenerate();

            return redirect('/home')->with('status', [
                'success' => 1,
                'msg' => __('business.business_created_succesfully'),
            ]);
        }

        return redirect('login')->with('status', [
            'success' => 1,
            'msg' => __('business.business_created_succesfully'),
        ]);
    }

    private function availableUsername(string $email): string
    {
        $localPart = Str::before($email, '@');
        $base = preg_replace('/[^A-Za-z0-9._-]/', '_', $localPart) ?: 'google_user';
        $base = Str::limit(trim($base, '._-'), 42, '');
        if (strlen($base) < 4) {
            $base = 'user_'.$base;
        }

        $candidate = $base;
        $suffix = 1;
        while (User::where('username', $candidate)->exists()) {
            $candidate = Str::limit($base, 42, '').'_'.$suffix++;
        }

        return $candidate;
    }

    /**
     * Handles the validation username
     *
     * @return \Illuminate\Http\Response
     */
    public function postCheckUsername(Request $request)
    {
        $username = trim((string) $request->input('username'));

        if (! empty($request->input('username_ext'))) {
            $username .= trim((string) $request->input('username_ext'));
        }

        if (! preg_match('/^[A-Za-z0-9._-]{4,50}$/', $username)) {
            return response()->json(false);
        }

        return response()->json(! User::where('username', $username)->exists());
    }

    /**
     * Shows business settings form
     *
     * @return \Illuminate\Http\Response
     */
    public function getBusinessSettings()
    {
        if (! auth()->user()->can('business_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        $timezones = DateTimeZone::listIdentifiers(DateTimeZone::ALL);
        $timezone_list = [];
        foreach ($timezones as $timezone) {
            $timezone_list[$timezone] = $timezone;
        }

        $business_id = request()->session()->get('user.business_id');
        $business = Business::where('id', $business_id)->first();

        $currencies = $this->businessUtil->allCurrencies();
        $tax_details = TaxRate::forBusinessDropdown($business_id);
        $tax_rates = $tax_details['tax_rates'];

        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[$i] = __('business.months.'.$i);
        }

        $accounting_methods = [
            'fifo' => __('business.fifo'),
            'lifo' => __('business.lifo'),
        ];
        $commission_agent_dropdown = [
            '' => __('lang_v1.disable'),
            'logged_in_user' => __('lang_v1.logged_in_user'),
            'user' => __('lang_v1.select_from_users_list'),
            'cmsn_agnt' => __('lang_v1.select_from_commisssion_agents_list'),
        ];

        $units_dropdown = Unit::forDropdown($business_id, true);

        $date_formats = Business::date_formats();

        $shortcuts = json_decode($business->keyboard_shortcuts, true);

        $pos_settings = empty($business->pos_settings) ? $this->businessUtil->defaultPosSettings() : json_decode($business->pos_settings, true);

        $sms_settings = empty($business->sms_settings) ? $this->businessUtil->defaultSmsSettings() : $business->sms_settings;

        $modules = $this->moduleUtil->availableModules();

        $theme_colors = $this->theme_colors;

        $custom_labels = ! empty($business->custom_labels) ? json_decode($business->custom_labels, true) : [];

        $common_settings = ! empty($business->common_settings) ? $business->common_settings : [];

        $weighing_scale_setting = ! empty($business->weighing_scale_setting) ? $business->weighing_scale_setting : [];

        $payment_types = $this->moduleUtil->payment_types(null, false, $business_id);

        return view('business.settings', compact('business', 'currencies', 'tax_rates', 'timezone_list', 'months', 'accounting_methods', 'commission_agent_dropdown', 'units_dropdown', 'date_formats', 'shortcuts', 'pos_settings', 'modules', 'theme_colors', 'sms_settings', 'custom_labels', 'common_settings', 'weighing_scale_setting', 'payment_types'));
    }

    /**
     * Updates business settings
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function postBusinessSettings(Request $request)
    {
        if (! auth()->user()->can('business_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $notAllowed = $this->businessUtil->notAllowedInDemo();
            if (! empty($notAllowed)) {
                return $notAllowed;
            }

            $business_details = $request->only(['name', 'start_date', 'currency_id', 'tax_label_1', 'tax_number_1', 'tax_label_2', 'tax_number_2', 'default_profit_percent', 'default_sales_tax', 'default_sales_discount', 'sell_price_tax', 'sku_prefix', 'time_zone', 'fy_start_month', 'accounting_method', 'transaction_edit_days', 'sales_cmsn_agnt', 'item_addition_method', 'currency_symbol_placement', 'on_product_expiry',
                'stop_selling_before', 'default_unit', 'expiry_type', 'date_format',
                'time_format', 'ref_no_prefixes', 'theme_color',
                'sms_settings', 'rp_name', 'amount_for_unit_rp',
                'min_order_total_for_rp', 'max_rp_per_order',
                'redeem_amount_per_unit_rp', 'min_order_total_for_redeem',
                'min_redeem_point', 'max_redeem_point', 'rp_expiry_period',
                'rp_expiry_type', 'custom_labels', 'weighing_scale_setting',
                'code_label_1', 'code_1', 'code_label_2', 'code_2', 'currency_precision', 'quantity_precision', ]);

            if (! empty($request->input('enable_rp')) && $request->input('enable_rp') == 1) {
                $business_details['enable_rp'] = 1;
            } else {
                $business_details['enable_rp'] = 0;
            }

            $business_details['amount_for_unit_rp'] = ! empty($business_details['amount_for_unit_rp']) ? $this->businessUtil->num_uf($business_details['amount_for_unit_rp']) : 1;
            $business_details['min_order_total_for_rp'] = ! empty($business_details['min_order_total_for_rp']) ? $this->businessUtil->num_uf($business_details['min_order_total_for_rp']) : 1;
            $business_details['redeem_amount_per_unit_rp'] = ! empty($business_details['redeem_amount_per_unit_rp']) ? $this->businessUtil->num_uf($business_details['redeem_amount_per_unit_rp']) : 1;
            $business_details['min_order_total_for_redeem'] = ! empty($business_details['min_order_total_for_redeem']) ? $this->businessUtil->num_uf($business_details['min_order_total_for_redeem']) : 1;

            $business_details['default_profit_percent'] = ! empty($business_details['default_profit_percent']) ? $this->businessUtil->num_uf($business_details['default_profit_percent']) : 0;

            $business_details['default_sales_discount'] = ! empty($business_details['default_sales_discount']) ? $this->businessUtil->num_uf($business_details['default_sales_discount']) : 0;

            if (! empty($business_details['start_date'])) {
                $business_details['start_date'] = $this->businessUtil->uf_date($business_details['start_date']);
            }

            if (! empty($request->input('enable_tooltip')) && $request->input('enable_tooltip') == 1) {
                $business_details['enable_tooltip'] = 1;
            } else {
                $business_details['enable_tooltip'] = 0;
            }

            $business_details['enable_product_expiry'] = ! empty($request->input('enable_product_expiry')) && $request->input('enable_product_expiry') == 1 ? 1 : 0;
            if ($business_details['on_product_expiry'] == 'keep_selling') {
                $business_details['stop_selling_before'] = null;
            }

            $business_details['stock_expiry_alert_days'] = ! empty($request->input('stock_expiry_alert_days')) ? $request->input('stock_expiry_alert_days') : 30;

            //Check for Purchase currency
            if (! empty($request->input('purchase_in_diff_currency')) && $request->input('purchase_in_diff_currency') == 1) {
                $business_details['purchase_in_diff_currency'] = 1;
                $business_details['purchase_currency_id'] = $request->input('purchase_currency_id');
                $business_details['p_exchange_rate'] = $request->input('p_exchange_rate');
            } else {
                $business_details['purchase_in_diff_currency'] = 0;
                $business_details['purchase_currency_id'] = null;
                $business_details['p_exchange_rate'] = 1;
            }

            //upload logo
            $logo_name = $this->businessUtil->uploadFile($request, 'business_logo', 'business_logos', 'image');
            if (! empty($logo_name)) {
                $business_details['logo'] = $logo_name;
            }

            $checkboxes = ['enable_editing_product_from_purchase',
                'enable_inline_tax',
                'enable_brand', 'enable_category', 'enable_sub_category', 'enable_price_tax', 'enable_purchase_status',
                'enable_lot_number', 'enable_racks', 'enable_row', 'enable_position', 'enable_sub_units', ];
            foreach ($checkboxes as $value) {
                $business_details[$value] = ! empty($request->input($value)) && $request->input($value) == 1 ? 1 : 0;
            }

            $business_id = request()->session()->get('user.business_id');
            $business = Business::where('id', $business_id)->first();

            //Update business settings
            if (! empty($business_details['logo'])) {
                $business->logo = $business_details['logo'];
            } else {
                unset($business_details['logo']);
            }

            //System settings
            $shortcuts = $request->input('shortcuts');
            $business_details['keyboard_shortcuts'] = json_encode($shortcuts);

           // Get existing pos_settings
            $pos_settings = $request->input('pos_settings', []);

            $pre_busines_detail = $this->businessUtil->getDetails($business_id);
            $pre_pos_setting = json_decode($pre_busines_detail->pos_settings, true) ?? [];
            for ($i = 1; $i <= 10; $i++) {
                $inputName = "carousel_image_$i"; // Image field names should be like carousel_image_1, carousel_image_2, etc.

                if ($request->hasFile($inputName)) {
                    $image_name = $this->businessUtil->uploadFile($request, $inputName, 'carousel_images', 'image');
                    $pos_settings[$inputName] = $image_name; // Store image URL inside pos_settings
                }else if (isset($pre_pos_setting[$inputName])){
                    $pos_settings[$inputName] = $pre_pos_setting[$inputName] ?? null;
                }
            }
            $default_pos_settings = $this->businessUtil->defaultPosSettings();
            foreach ($default_pos_settings as $key => $value) {
                if (! isset($pos_settings[$key])) {
                    $pos_settings[$key] = $value;
                }
            }
            // Save pos_settings as JSON
            $business_details['pos_settings'] = json_encode($pos_settings);

            $business_details['custom_labels'] = json_encode($business_details['custom_labels']);

            $business_details['common_settings'] = ! empty($request->input('common_settings')) ? $request->input('common_settings') : [];

            //Enabled modules
            $enabled_modules = $request->input('enabled_modules');
            $business_details['enabled_modules'] = ! empty($enabled_modules) ? $enabled_modules : null;
            $business->fill($business_details);
            $business->save();

            //update session data
            $request->session()->put('business', $business);

            //Update Currency details
            $currency = Currency::find($business->currency_id);
            $request->session()->put('currency', [
                'id' => $currency->id,
                'code' => $currency->code,
                'symbol' => $currency->symbol,
                'thousand_separator' => $currency->thousand_separator,
                'decimal_separator' => $currency->decimal_separator,
            ]);

            //update current financial year to session
            $financial_year = $this->businessUtil->getCurrentFinancialYear($business->id);
            $request->session()->put('financial_year', $financial_year);

            $output = ['success' => 1,
                'msg' => __('business.settings_updated_success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect('business/settings')->with('status', $output);
    }

    /**
     * Handles the validation email
     *
     * @return \Illuminate\Http\Response
     */
    public function postCheckEmail(Request $request)
    {
        $email = mb_strtolower(trim((string) $request->input('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(__('validation.email', ['attribute' => __('business.email')]));
        }

        if(config('constants.do_not_allow_disposable_email') && $request->input('is_disposable_email')) {

            $email_validator = Validator::make(['email' => $email], [
                'email' => 'email|indisposable',
            ], [
                'email.indisposable' => __('validation.indisposable'),
            ]);

            if ($email_validator->fails()) {
                return response()->json($email_validator->errors()->first('email'));
            }
        }

        // Second: uniqueness check (existing behavior)
        $query = User::whereRaw('LOWER(email) = ?', [$email]);

        if (auth()->check() && ! empty($request->input('user_id'))) {
            $user_id = $request->input('user_id');
            $query->where('id', '!=', $user_id);
        }

        $exists = $query->exists();
        if (! $exists) {
            return response()->json(true);
        }

        return response()->json($request->boolean('is_disposable_email')
            ? __('validation.unique', ['attribute' => __('business.email')])
            : false);
    }

    public function getEcomSettings()
    {
        try {
            $api_token = request()->header('API-TOKEN');
            $api_settings = $this->moduleUtil->getApiSettings($api_token);

            $settings = Business::where('id', $api_settings->business_id)
                        ->value('ecom_settings');

            $settings_array = ! empty($settings) ? json_decode($settings, true) : [];

            if (! empty($settings_array['slides'])) {
                foreach ($settings_array['slides'] as $key => $value) {
                    $settings_array['slides'][$key]['image_url'] = ! empty($value['image']) ? url('uploads/img/'.$value['image']) : '';
                }
            }
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return $this->respondWentWrong($e);
        }

        return $this->respond($settings_array);
    }

    /**
     * Handles the testing of sms configuration
     *
     * @return \Illuminate\Http\Response
     */
    public function testSmsConfiguration(Request $request)
    {
        try {
            $sms_settings = $request->input();

            $data = [
                'sms_settings' => $sms_settings,
                'mobile_number' => $sms_settings['test_number'],
                'sms_body' => 'This is a test SMS',
            ];
            if (! empty($sms_settings['test_number'])) {
                $response = $this->businessUtil->sendSms($data);
                $parameter_type = isset($sms_settings['data_parameter_type']) ? $sms_settings['data_parameter_type'] : 'form-data';

                if($parameter_type == 'json'){

                    $body = json_decode($response->getBody(), true);
                    // Optional: Check if 'status' or 'success' is true inside JSON (based on API format)
                    return ['success' => true, 'msg' => 'SMS sent successfully', 'data' => $body];
                }
            } else {
                $response = __('lang_v1.test_number_is_required');
            }

            $output = [
                'success' => 1,
                'msg' => $response,
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = [
                'success' => 0,
                'msg' => $e->getMessage(),
            ];
        }

        return $output;
    }
}
