<?php

namespace Modules\Hms\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class HmsBookingExtra extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function extra()
    {
        return $this->belongsTo(HmsExtra::class, 'hms_extra_id');
    }
}
