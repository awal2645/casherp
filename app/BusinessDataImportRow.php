<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BusinessDataImportRow extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'raw_values' => 'array',
        'normalized_values' => 'array',
        'validation_errors' => 'array',
        'previous_values' => 'array',
    ];

    public function dataImport()
    {
        return $this->belongsTo(BusinessDataImport::class, 'business_data_import_id');
    }
}
