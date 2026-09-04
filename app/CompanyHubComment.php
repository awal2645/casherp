<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CompanyHubComment extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = ['edited_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (self $comment) {
            $comment->uuid = $comment->uuid ?: (string) Str::uuid();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function replies()
    {
        return $this->hasMany(self::class, 'parent_id')->oldest();
    }
}
