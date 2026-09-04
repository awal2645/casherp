<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BusinessDocumentEvent extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function document()
    {
        return $this->belongsTo(BusinessDocument::class, 'business_document_id');
    }
}
