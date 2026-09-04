<?php

namespace Modules\Hms\Entities;

use Illuminate\Database\Eloquent\Model;

class HmsGuestMessageRule extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['requires_marketing_consent' => 'boolean', 'is_active' => 'boolean'];
}
