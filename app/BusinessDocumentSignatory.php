<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BusinessDocumentSignatory extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['signed_at' => 'datetime'];

    public function document()
    {
        return $this->belongsTo(BusinessDocument::class, 'business_document_id');
    }
}
