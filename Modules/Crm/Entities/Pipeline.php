<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;

class Pipeline extends Model
{
    protected $table = 'crm_pipelines';
    protected $guarded = ['id'];
    protected $casts = ['is_default' => 'boolean', 'is_active' => 'boolean'];

    public function stages()
    {
        return $this->hasMany(PipelineStage::class, 'crm_pipeline_id')->orderBy('position');
    }

    public function opportunities()
    {
        return $this->hasMany(Opportunity::class, 'crm_pipeline_id');
    }
}
