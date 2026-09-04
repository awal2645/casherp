<?php

namespace App\Services;

use App\Product;
use App\Restaurant\IngredientMovement;
use App\Restaurant\Recipe;
use App\Restaurant\RestaurantOperationSetting;
use App\Transaction;
use App\Variation;
use App\VariationLocationDetails;
use DomainException;
use Illuminate\Support\Facades\DB;

class RecipeConsumptionService
{
    public function synchronise(Transaction $transaction): void
    {
        if ($transaction->type !== 'sell') {
            return;
        }

        if ($transaction->status !== 'final') {
            $this->reverse($transaction);

            return;
        }

        DB::transaction(function () use ($transaction) {
            $transaction->loadMissing('sell_lines');
            foreach ($transaction->sell_lines->whereNull('parent_sell_line_id') as $sellLine) {
                $recipe = Recipe::query()
                    ->where('business_id', $transaction->business_id)
                    ->where('menu_product_id', $sellLine->product_id)
                    ->where('is_active', true)
                    ->where(function ($query) use ($sellLine) {
                        $query->where('menu_variation_id', $sellLine->variation_id)->orWhereNull('menu_variation_id');
                    })
                    ->orderByRaw('menu_variation_id IS NULL')
                    ->with('lines')
                    ->first();
                if (! $recipe) {
                    continue;
                }

                foreach ($recipe->lines as $line) {
                    $key = "restaurant:consumption:{$transaction->id}:{$sellLine->id}:{$line->id}";
                    if (IngredientMovement::where('idempotency_key', $key)->exists()) {
                        continue;
                    }

                    $quantity = round(((float) $sellLine->quantity * (float) $line->quantity) / max((float) $recipe->yield_quantity, 0.0001), 4);
                    $this->applyStockDelta(
                        (int) $transaction->business_id,
                        (int) $transaction->location_id,
                        (int) $line->ingredient_product_id,
                        (int) $line->ingredient_variation_id,
                        -$quantity
                    );

                    IngredientMovement::create([
                        'business_id' => $transaction->business_id,
                        'location_id' => $transaction->location_id,
                        'ingredient_product_id' => $line->ingredient_product_id,
                        'ingredient_variation_id' => $line->ingredient_variation_id,
                        'recipe_id' => $recipe->id,
                        'transaction_id' => $transaction->id,
                        'transaction_sell_line_id' => $sellLine->id,
                        'movement_type' => 'consumption',
                        'quantity' => -$quantity,
                        'unit_cost' => $line->unit_cost_snapshot,
                        'total_cost' => round($quantity * (float) $line->unit_cost_snapshot, 4),
                        'idempotency_key' => $key,
                        'created_by' => $transaction->created_by,
                    ]);
                }
            }
        }, 3);
    }

    public function recordWaste(array $data, int $actorId): IngredientMovement
    {
        return DB::transaction(function () use ($data, $actorId) {
            $key = $data['idempotency_key'] ?? 'restaurant:waste:'.sha1(json_encode($data).':'.$actorId.':'.microtime(true));
            $existing = IngredientMovement::where('business_id', $data['business_id'])->where('idempotency_key', $key)->first();
            if ($existing) {
                return $existing;
            }

            $quantity = abs((float) $data['quantity']);
            $this->applyStockDelta($data['business_id'], $data['location_id'], $data['ingredient_product_id'], $data['ingredient_variation_id'], -$quantity);

            return IngredientMovement::create([
                'business_id' => $data['business_id'],
                'location_id' => $data['location_id'],
                'ingredient_product_id' => $data['ingredient_product_id'],
                'ingredient_variation_id' => $data['ingredient_variation_id'],
                'movement_type' => 'waste',
                'quantity' => -$quantity,
                'unit_cost' => $data['unit_cost'] ?? 0,
                'total_cost' => round($quantity * (float) ($data['unit_cost'] ?? 0), 4),
                'idempotency_key' => $key,
                'reason' => $data['reason'],
                'created_by' => $actorId,
            ]);
        }, 3);
    }

    private function reverse(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $movements = IngredientMovement::where('business_id', $transaction->business_id)
                ->where('transaction_id', $transaction->id)
                ->where('movement_type', 'consumption')
                ->lockForUpdate()
                ->get();
            foreach ($movements as $movement) {
                $key = 'restaurant:reversal:'.$movement->id;
                if (IngredientMovement::where('idempotency_key', $key)->exists()) {
                    continue;
                }
                $quantity = abs((float) $movement->quantity);
                $this->applyStockDelta($movement->business_id, $movement->location_id, $movement->ingredient_product_id, $movement->ingredient_variation_id, $quantity);
                IngredientMovement::create([
                    'business_id' => $movement->business_id,
                    'location_id' => $movement->location_id,
                    'ingredient_product_id' => $movement->ingredient_product_id,
                    'ingredient_variation_id' => $movement->ingredient_variation_id,
                    'recipe_id' => $movement->recipe_id,
                    'transaction_id' => $movement->transaction_id,
                    'transaction_sell_line_id' => $movement->transaction_sell_line_id,
                    'movement_type' => 'reversal',
                    'quantity' => $quantity,
                    'unit_cost' => $movement->unit_cost,
                    'total_cost' => -1 * (float) $movement->total_cost,
                    'idempotency_key' => $key,
                    'reason' => 'Automatic reversal after sale cancellation or reversion.',
                    'created_by' => $transaction->created_by,
                ]);
            }
        }, 3);
    }

    private function applyStockDelta(int $businessId, int $locationId, int $productId, int $variationId, float $delta): void
    {
        $product = Product::where('business_id', $businessId)->whereKey($productId)->firstOrFail();
        $variation = Variation::where('product_id', $productId)->whereKey($variationId)->firstOrFail();
        if (! $product->enable_stock) {
            throw new DomainException("Ingredient {$product->name} must have stock tracking enabled.");
        }

        $stock = VariationLocationDetails::where('location_id', $locationId)
            ->where('product_id', $productId)
            ->where('variation_id', $variationId)
            ->lockForUpdate()
            ->first();
        if (! $stock) {
            $stock = VariationLocationDetails::create([
                'location_id' => $locationId,
                'product_id' => $productId,
                'variation_id' => $variationId,
                'product_variation_id' => $variation->product_variation_id,
                'qty_available' => 0,
            ]);
        }

        $newQuantity = round((float) $stock->qty_available + $delta, 4);
        $settings = RestaurantOperationSetting::where('business_id', $businessId)->first();
        $inventoryPolicy = (array) optional($settings)->inventory_policy;
        $allowNegative = (bool) ($inventoryPolicy['allow_negative_ingredient_stock'] ?? config('restaurant_operations.default_settings.allow_negative_ingredient_stock', false));
        if ($newQuantity < 0 && ! $allowNegative) {
            throw new DomainException("Insufficient ingredient stock for {$product->name} at the selected location.");
        }

        $stock->qty_available = $newQuantity;
        $stock->save();
    }
}
