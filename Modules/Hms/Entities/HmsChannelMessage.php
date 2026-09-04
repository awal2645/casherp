<?php

namespace Modules\Hms\Entities;

use Illuminate\Database\Eloquent\Model;

class HmsChannelMessage extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['payload' => 'array', 'signature_valid' => 'boolean', 'processed_at' => 'datetime'];
}
