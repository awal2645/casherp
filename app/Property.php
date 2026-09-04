<?php
namespace App;

use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['amenities' => 'array', 'is_active' => 'boolean'];

    public function units()
    {
        return $this->hasMany(PropertyUnit::class);
    }

    public function businessLocation()
    {
        return $this->belongsTo(BusinessLocation::class, 'business_location_id');
    }

    public function viewingRequests()
    {
        return $this->hasMany(PropertyViewingRequest::class);
    }

    public function accessGrants()
    {
        return $this->hasMany(PropertyAccessGrant::class);
    }
}
