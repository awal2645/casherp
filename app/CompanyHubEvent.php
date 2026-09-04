<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CompanyHubEvent extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'audience_location_ids' => 'array',
        'audience_department_ids' => 'array',
        'audience_role_ids' => 'array',
        'audience_user_ids' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $event) {
            $event->uuid = $event->uuid ?: (string) Str::uuid();
        });
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
