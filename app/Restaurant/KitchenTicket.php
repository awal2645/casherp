<?php

namespace App\Restaurant;

use Illuminate\Database\Eloquent\Model;

class KitchenTicket extends Model
{
    protected $table = 'restaurant_kitchen_tickets';

    protected $guarded = ['id'];

    protected $casts = [
        'fired_at' => 'datetime',
        'accepted_at' => 'datetime',
        'started_at' => 'datetime',
        'ready_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function station()
    {
        return $this->belongsTo(KitchenStation::class, 'station_id');
    }

    public function items()
    {
        return $this->hasMany(KitchenTicketItem::class, 'kitchen_ticket_id');
    }
}
