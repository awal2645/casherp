<?php

namespace App\Restaurant;

use Illuminate\Database\Eloquent\Model;

class KitchenTicketItem extends Model
{
    protected $table = 'restaurant_kitchen_ticket_items';

    protected $guarded = ['id'];

    protected $casts = ['modifier_snapshot' => 'array', 'quantity' => 'decimal:4'];

    public function ticket()
    {
        return $this->belongsTo(KitchenTicket::class, 'kitchen_ticket_id');
    }
}
