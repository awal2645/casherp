<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;

class StageHistory extends Model
{
    protected $table = 'crm_stage_history';
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['moved_at' => 'datetime'];
}
