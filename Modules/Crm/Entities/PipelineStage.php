<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;

class PipelineStage extends Model
{
    protected $table = 'crm_pipeline_stages';
    protected $guarded = ['id'];
    protected $casts = ['is_won' => 'boolean', 'is_lost' => 'boolean'];

    public function pipeline()
    {
        return $this->belongsTo(Pipeline::class, 'crm_pipeline_id');
    }
}
