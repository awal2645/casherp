<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
class PropertyUnit extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['available_from' => 'date', 'is_listed' => 'boolean'];

    public function property() { return $this->belongsTo(Property::class); }
    public function leases() { return $this->hasMany(PropertyLease::class); }
    public function viewingRequests() { return $this->hasMany(PropertyViewingRequest::class); }
}
