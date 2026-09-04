<?php

namespace App\Services;

use App\Transaction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class RestaurantOrderLifecycleService
{
    public function synchronise(Transaction $transaction): void
    {
        if (! Schema::hasTable('restaurant_order_fulfilments') || ! Schema::hasTable('restaurant_kitchen_tickets')) {
            return;
        }
        if (! app(FeatureAccessService::class)->enabled('restaurant_operations', (int) $transaction->business_id)) {
            return;
        }

        try {
            app(KitchenRoutingService::class)->synchronise($transaction->fresh());
            app(RecipeConsumptionService::class)->synchronise($transaction->fresh());
        } catch (\Throwable $exception) {
            // The sale was already committed by UltimatePOS. Keep that sale intact,
            // surface the operational-sync failure to logs, and allow safe replay.
            Log::error('Restaurant order synchronisation requires attention.', [
                'business_id' => $transaction->business_id,
                'transaction_id' => $transaction->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
