<?php

namespace Modules\Hms\Entities;

use Illuminate\Database\Eloquent\Model;

class HmsOperationalEvent extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['occurred_at' => 'datetime'];
}
