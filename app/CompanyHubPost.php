<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CompanyHubPost extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'audience_location_ids' => 'array',
        'audience_department_ids' => 'array',
        'audience_role_ids' => 'array',
        'audience_user_ids' => 'array',
        'comments_enabled' => 'boolean',
        'acknowledgement_required' => 'boolean',
        'is_pinned' => 'boolean',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
        'edited_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $post) {
            $post->uuid = $post->uuid ?: (string) Str::uuid();
        });
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function channel()
    {
        return $this->belongsTo(CompanyHubChannel::class, 'company_hub_channel_id');
    }

    public function comments()
    {
        return $this->hasMany(CompanyHubComment::class)->whereNull('parent_id')->oldest();
    }

    public function acknowledgements()
    {
        return $this->hasMany(CompanyHubPostAcknowledgement::class);
    }
}
