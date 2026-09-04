<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'supports_line_items' => 'boolean',
        'is_financial' => 'boolean',
        'requires_acceptance' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function industries()
    {
        return $this->belongsToMany(Industry::class, 'industry_document_types')
            ->withPivot(['enabled_by_default', 'display_name', 'sort_order'])
            ->withTimestamps();
    }

    public function businessSettings()
    {
        return $this->hasMany(BusinessDocumentSetting::class);
    }
}
