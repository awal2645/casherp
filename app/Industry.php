<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Industry extends Model
{
    protected $guarded = ['id'];

    public function features()
    {
        return $this->belongsToMany(Feature::class, 'industry_features')
            ->withPivot('enabled_by_default')
            ->withTimestamps();
    }

    public function enabledFeatures()
    {
        return $this->features()
            ->wherePivot('enabled_by_default', true)
            ->where('features.is_active', true);
    }

    public function documentTypes()
    {
        return $this->belongsToMany(DocumentType::class, 'industry_document_types')
            ->withPivot(['enabled_by_default', 'display_name', 'sort_order'])
            ->withTimestamps();
    }

    public function enabledDocumentTypes()
    {
        return $this->documentTypes()
            ->wherePivot('enabled_by_default', true)
            ->where('document_types.is_active', true)
            ->orderBy('industry_document_types.sort_order');
    }
}
