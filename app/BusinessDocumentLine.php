<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BusinessDocumentLine extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['metadata' => 'array'];

    public function document()
    {
        return $this->belongsTo(BusinessDocument::class, 'business_document_id');
    }
}
