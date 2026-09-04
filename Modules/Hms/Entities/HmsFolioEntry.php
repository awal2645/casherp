<?php

namespace Modules\Hms\Entities;

use Illuminate\Database\Eloquent\Model;

class HmsFolioEntry extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'posted_at' => 'datetime',
        'business_date' => 'date',
        'approved_at' => 'datetime',
        'voided_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function folio()
    {
        return $this->belongsTo(HmsFolio::class, 'hms_folio_id');
    }
}
