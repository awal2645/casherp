<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Opportunity extends Model
{
    use SoftDeletes;

    protected $table = 'crm_opportunities';
    protected $guarded = ['id'];
    protected $casts = [
        'estimated_value' => 'decimal:4',
        'expected_close_date' => 'date',
        'last_activity_at' => 'datetime',
        'next_activity_at' => 'datetime',
        'won_at' => 'datetime',
        'lost_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $opportunity) {
            $opportunity->uuid = $opportunity->uuid ?: (string) Str::uuid();
        });
    }

    public function pipeline()
    {
        return $this->belongsTo(Pipeline::class, 'crm_pipeline_id');
    }

    public function stage()
    {
        return $this->belongsTo(PipelineStage::class, 'crm_pipeline_stage_id');
    }

    public function contact()
    {
        return $this->belongsTo(\App\Contact::class);
    }

    public function owner()
    {
        return $this->belongsTo(\App\User::class, 'owner_id');
    }

    public function activities()
    {
        return $this->hasMany(Activity::class, 'crm_opportunity_id')->latest('due_at');
    }
}
