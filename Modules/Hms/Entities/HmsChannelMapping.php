<?php

namespace Modules\Hms\Entities;

use Illuminate\Database\Eloquent\Model;

class HmsChannelMapping extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['metadata' => 'array'];
}
