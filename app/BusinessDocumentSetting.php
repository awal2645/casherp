<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BusinessDocumentSetting extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_enabled' => 'boolean',
        'settings' => 'array',
        'terms_reviewed_at' => 'datetime',
    ];

    public function type()
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
