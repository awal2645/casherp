<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BusinessDataImportEvent extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['context' => 'array', 'created_at' => 'datetime'];

    public function dataImport()
    {
        return $this->belongsTo(BusinessDataImport::class, 'business_data_import_id');
    }
}
