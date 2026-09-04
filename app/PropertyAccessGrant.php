<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PropertyAccessGrant extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'abilities' => 'array',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}
