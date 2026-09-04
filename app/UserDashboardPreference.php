<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserDashboardPreference extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'hidden_sections' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function defaultLocation()
    {
        return $this->belongsTo(BusinessLocation::class, 'default_location_id');
    }
}
