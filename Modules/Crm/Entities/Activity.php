<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Activity extends Model
{
    use SoftDeletes;

    protected $table = 'crm_activities';
    protected $guarded = ['id'];
    protected $casts = [
        'due_at' => 'datetime',
        'remind_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $activity) {
            $activity->uuid = $activity->uuid ?: (string) Str::uuid();
        });
    }

    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class, 'crm_opportunity_id');
    }

    public function owner()
    {
        return $this->belongsTo(\App\User::class, 'owner_id');
    }

    public function contact()
    {
        return $this->belongsTo(\App\Contact::class);
    }
}
