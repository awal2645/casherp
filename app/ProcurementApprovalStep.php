<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProcurementApprovalStep extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['acted_at' => 'datetime'];

    public function document()
    {
        return $this->belongsTo(ProcurementDocument::class, 'procurement_document_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'acted_by');
    }
}
