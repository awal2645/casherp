<?php

namespace Modules\Superadmin\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Package extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'custom_permissions' => 'array',
        'businesses' => 'array',
        'price' => 'decimal:4',
        'max_businesses' => 'integer',
        'location_count' => 'integer',
        'user_count' => 'integer',
        'product_count' => 'integer',
        'invoice_count' => 'integer',
        'data_import_enabled' => 'boolean',
        'monthly_import_rows' => 'integer',
        'max_import_rows_per_file' => 'integer',
        'max_import_file_size_mb' => 'integer',
        'concurrent_imports' => 'integer',
        'import_rollback_days' => 'integer',
        'interval_count' => 'integer',
        'trial_days' => 'integer',
        'is_active' => 'boolean',
        'is_private' => 'boolean',
        'is_one_time' => 'boolean',
    ];

    /**
     * Scope a query to only include active packages.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    /**
     * Returns the list of active pakages
     *
     * @return object
     */
    public static function listPackages($exlude_private = false, $interval = null)
    {
        $packages = Package::active()
                        ->orderby('sort_order');

        if ($exlude_private) {
            $packages->notPrivate();
        }

        if (!empty($interval)) {
            $packages->where('interval', $interval);
        }

        return $packages->get();
    }

    /**
     * Scope a query to exclude private packages.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNotPrivate($query)
    {
        return $query->where('is_private', 0);
    }

    /** Public catalogue plans exclude private and company-targeted offers. */
    public function scopePubliclyAvailable($query)
    {
        return $query->notPrivate()
            ->where(function ($targeting) {
                $targeting->whereNull('businesses')
                    ->orWhere('businesses', '')
                    ->orWhere('businesses', '[]');
            });
    }

    public function premiumModules()
    {
        return $this->belongsToMany(
            \App\PremiumModulePlan::class,
            'package_premium_modules'
        )->withPivot('included_allowance_override')->withTimestamps();
    }
}
