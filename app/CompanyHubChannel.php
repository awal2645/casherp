<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CompanyHubChannel extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'department_ids' => 'array',
        'is_archived' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $channel) {
            $channel->uuid = $channel->uuid ?: (string) Str::uuid();
        });
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'company_hub_channel_members', 'company_hub_channel_id', 'user_id')
            ->withPivot(['business_id', 'role', 'added_by'])->withTimestamps();
    }

    public function posts()
    {
        return $this->hasMany(CompanyHubPost::class);
    }
}
