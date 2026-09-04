<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProcurementEvent extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['metadata' => 'array'];

    public function document()
    {
        return $this->belongsTo(ProcurementDocument::class, 'procurement_document_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
