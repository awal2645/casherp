<?php

namespace App\Listeners;

use App\Events\SellCreatedOrModified;
use App\Services\RestaurantOrderLifecycleService;

class SyncRestaurantOperations
{
    public function handle(SellCreatedOrModified $event): void
    {
        app(RestaurantOrderLifecycleService::class)->synchronise($event->transaction);
    }
}
