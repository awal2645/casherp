<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BusinessEmailConfigurationEvent extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
