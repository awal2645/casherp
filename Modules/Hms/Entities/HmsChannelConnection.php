<?php

namespace Modules\Hms\Entities;

use Illuminate\Database\Eloquent\Model;

class HmsChannelConnection extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['secret_encrypted'];
    protected $casts = ['settings' => 'array', 'last_inventory_sync_at' => 'datetime', 'last_reservation_sync_at' => 'datetime'];

    public function property()
    {
        return $this->belongsTo(HmsProperty::class, 'hms_property_id');
    }

    public function mappings()
    {
        return $this->hasMany(HmsChannelMapping::class, 'hms_channel_connection_id');
    }

    public function messages()
    {
        return $this->hasMany(HmsChannelMessage::class, 'hms_channel_connection_id');
    }
}
