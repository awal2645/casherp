<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PremiumModulePlan extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'module_price' => 'decimal:4',
        'capacity_price' => 'decimal:4',
        'included_allowance' => 'integer',
        'capacity_increment' => 'integer',
        'is_active' => 'boolean',
    ];

    public function feature()
    {
        return $this->belongsTo(Feature::class);
    }

    public function packages()
    {
        return $this->belongsToMany(
            \Modules\Superadmin\Entities\Package::class,
            'package_premium_modules'
        )->withPivot('included_allowance_override')->withTimestamps();
    }

    public function entitlements()
    {
        return $this->hasMany(BusinessModuleEntitlement::class);
    }
}
