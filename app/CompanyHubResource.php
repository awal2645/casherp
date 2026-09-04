<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CompanyHubResource extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'audience_location_ids' => 'array',
        'audience_department_ids' => 'array',
        'audience_role_ids' => 'array',
        'audience_user_ids' => 'array',
        'effective_date' => 'date',
        'review_date' => 'date',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $resource) {
            $resource->uuid = $resource->uuid ?: (string) Str::uuid();
        });
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function previousVersion()
    {
        return $this->belongsTo(self::class, 'previous_version_id');
    }
}
