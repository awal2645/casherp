<?php

namespace Modules\Hms\Entities;

use Illuminate\Database\Eloquent\Model;

class HmsDepositSchedule extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['due_date' => 'date'];

    public function folio()
    {
        return $this->belongsTo(HmsFolio::class, 'hms_folio_id');
    }
}
