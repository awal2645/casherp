<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Product;
use App\Restaurant\IngredientMovement;
use App\Services\RecipeConsumptionService;
use App\Services\RestaurantContextService;
use App\Variation;
use Illuminate\Http\Request;

class RestaurantInventoryController extends Controller
{
    public function index(Request $request, RestaurantContextService $context)
    {
        $this->authorizeView();
        $businessId = $context->businessId();
        $locationIds = $context->permittedLocationIds($businessId);
        $movements = IngredientMovement::where('business_id', $businessId)
            ->when($locationIds !== null, fn ($q) => $q->whereIn('location_id', $locationIds))
            ->when($request->filled('movement_type'), fn ($q) => $q->where('movement_type', $request->movement_type))
            ->latest()->paginate(50);
        $ingredients = Product::where('business_id', $businessId)->where('enable_stock', true)->active()->orderBy('name')->get(['id', 'name']);
        $variations = Variation::join('products as product', 'product.id', '=', 'variations.product_id')
            ->where('product.business_id', $businessId)
            ->where('product.enable_stock', true)
            ->orderBy('product.name')
            ->get(['variations.id', 'variations.product_id', 'variations.name', 'variations.sub_sku', 'product.name as product_name']);
        $locations = \App\BusinessLocation::forDropdown($businessId);
        $summary = IngredientMovement::where('business_id', $businessId)
            ->when($locationIds !== null, fn ($q) => $q->whereIn('location_id', $locationIds))
            ->selectRaw('movement_type, SUM(quantity) quantity, SUM(total_cost) total_cost')
            ->groupBy('movement_type')->get();

        return view('restaurant.operations.inventory', compact('movements', 'ingredients', 'variations', 'locations', 'summary'));
    }

    public function waste(Request $request, RestaurantContextService $context, RecipeConsumptionService $service)
    {
        if (! auth()->user()->can('restaurant.inventory.adjust')) {
            abort(403, 'Unauthorized action.');
        }
        $businessId = $context->businessId();
        $data = $request->validate([
            'location_id' => ['required', 'integer'],
            'ingredient_product_id' => ['required', 'integer'],
            'ingredient_variation_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:2000'],
            'idempotency_key' => ['nullable', 'string', 'max:191'],
        ]);
        $context->assertLocation($businessId, (int) $data['location_id'], $request->user());
        Product::where('business_id', $businessId)->whereKey($data['ingredient_product_id'])->where('enable_stock', true)->firstOrFail();
        Variation::where('product_id', $data['ingredient_product_id'])->whereKey($data['ingredient_variation_id'])->firstOrFail();
        $service->recordWaste($data + ['business_id' => $businessId], (int) $request->user()->id);

        return back()->with('status', ['success' => 1, 'msg' => 'Ingredient waste recorded with an auditable stock movement.']);
    }

    private function authorizeView(): void
    {
        if (! auth()->user()->can('restaurant.recipes.view') && ! auth()->user()->can('stock_adjustment.view')) {
            abort(403, 'Unauthorized action.');
        }
    }
}
