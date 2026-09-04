<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PropertyMaintenanceTicket extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'reported_on' => 'date',
        'resolved_on' => 'date',
        'completed_at' => 'datetime',
    ];

    public function unit()
    {
        return $this->belongsTo(PropertyUnit::class, 'property_unit_id');
    }

    public function vendor()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }
}
