<?php

namespace Modules\Hms\Entities;

use Illuminate\Database\Eloquent\Model;

class HmsGuestProfile extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'date_of_birth' => 'date',
        'preferences' => 'array',
        'marketing_consent' => 'boolean',
        'consent_recorded_at' => 'datetime',
        'do_not_contact' => 'boolean',
        'retention_until' => 'date',
        'anonymized_at' => 'datetime',
    ];

    public function contact()
    {
        return $this->belongsTo(\App\Contact::class, 'contact_id');
    }
}
