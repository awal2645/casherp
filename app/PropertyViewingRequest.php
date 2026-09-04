<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PropertyViewingRequest extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'requested_start_at' => 'datetime',
        'requested_end_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function unit()
    {
        return $this->belongsTo(PropertyUnit::class, 'property_unit_id');
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function decisionMaker()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function scopeForBusiness($query, int $businessId)
    {
        return $query->where($this->qualifyColumn('business_id'), $businessId);
    }
}
