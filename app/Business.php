<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'business';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id', 'woocommerce_api_settings'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = ['woocommerce_api_settings'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'ref_no_prefixes' => 'array',
        'enabled_modules' => 'array',
        'email_settings' => 'array',
        'sms_settings' => 'array',
        'common_settings' => 'array',
        'weighing_scale_setting' => 'array',
        'onboarding_settings' => 'array',
        'onboarding_completed_at' => 'datetime',
    ];

    /**
     * Returns the date formats
     */
    public static function date_formats()
    {
        return [
            'd-m-Y' => 'dd-mm-yyyy',
            'm-d-Y' => 'mm-dd-yyyy',
            'd/m/Y' => 'dd/mm/yyyy',
            'm/d/Y' => 'mm/dd/yyyy',
        ];
    }

    /**
     * Get the owner details
     */
    public function owner()
    {
        return $this->hasOne(\App\User::class, 'id', 'owner_id');
    }

    /**
     * Get the Business currency.
     */
    public function currency()
    {
        return $this->belongsTo(\App\Currency::class);
    }

    /**
     * The industry profile selected for this company.
     */
    public function industry()
    {
        return $this->belongsTo(\App\Industry::class);
    }

    /**
     * The feature flags assigned to this company.
     */
    public function features()
    {
        return $this->belongsToMany(\App\Feature::class, 'business_features')
            ->withPivot(['is_enabled', 'source'])
            ->withTimestamps();
    }

    public function documents()
    {
        return $this->hasMany(\App\BusinessDocument::class);
    }

    public function documentSettings()
    {
        return $this->hasMany(\App\BusinessDocumentSetting::class);
    }

    public function employmentProfiles()
    {
        return $this->hasMany(\Modules\Essentials\Entities\EmploymentProfile::class);
    }

    public function payrollRuns()
    {
        return $this->hasMany(\Modules\Essentials\Entities\PayrollRun::class);
    }

    /**
     * Get the Business currency.
     */
    public function locations()
    {
        return $this->hasMany(\App\BusinessLocation::class);
    }

    /**
     * Get the Business printers.
     */
    public function printers()
    {
        return $this->hasMany(\App\Printer::class);
    }

    /**
     * Get the Business subscriptions.
     */
    public function subscriptions()
    {
        return $this->hasMany('\Modules\Superadmin\Entities\Subscription');
    }

    public function moduleEntitlements()
    {
        return $this->hasMany(\App\BusinessModuleEntitlement::class);
    }

    public function moduleOrders()
    {
        return $this->hasMany(\App\BusinessModuleOrder::class);
    }

    public function closureRequests()
    {
        return $this->hasMany(\App\BusinessClosureRequest::class);
    }

    public function emailConfigurationEvents()
    {
        return $this->hasMany(\App\BusinessEmailConfigurationEvent::class);
    }

    public function dataImports()
    {
        return $this->hasMany(\App\BusinessDataImport::class);
    }

    /**
     * Creates a new business based on the input provided.
     *
     * @return object
     */
    public static function create_business($details)
    {
        $business = Business::create($details);

        return $business;
    }

    /**
     * Updates a business based on the input provided.
     *
     * @param  int  $business_id
     * @param  array  $details
     * @return object
     */
    public static function update_business($business_id, $details)
    {
        if (! empty($details)) {
            Business::where('id', $business_id)
                ->update($details);
        }
    }

    public function getBusinessAddressAttribute()
    {
        $location = $this->locations->first();
        $address = $location->landmark.', '.$location->city.
        ', '.$location->state.'<br>'.$location->country.', '.$location->zip_code;

        return $address;
    }
}
