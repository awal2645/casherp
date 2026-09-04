<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Product;
use App\Restaurant\Recipe;
use App\Services\RestaurantContextService;
use App\Variation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecipeController extends Controller
{
    public function index(RestaurantContextService $context)
    {
        $this->authorizeView();
        $businessId = $context->businessId();
        $recipes = Recipe::where('business_id', $businessId)->withCount('lines')->orderBy('name')->paginate(50);
        $products = Product::where('business_id', $businessId)->active()->orderBy('name')->pluck('name', 'id');
        $variations = Variation::join('products as product', 'product.id', '=', 'variations.product_id')
            ->where('product.business_id', $businessId)
            ->orderBy('product.name')
            ->pluck(DB::raw("CONCAT(product.name, ' - ', variations.name, ' (', variations.sub_sku, ')')"), 'variations.id');

        return view('restaurant.operations.recipes', compact('recipes', 'products', 'variations'));
    }

    public function store(Request $request, RestaurantContextService $context)
    {
        $this->authorizeManage();
        $businessId = $context->businessId();
        $data = $this->validated($request);
        $this->assertProductAndIngredients($businessId, $data);
        DB::transaction(function () use ($data, $businessId, $request) {
            $recipe = Recipe::updateOrCreate([
                'business_id' => $businessId,
                'menu_product_id' => $data['menu_product_id'],
                'menu_variation_id' => $data['menu_variation_id'] ?? null,
            ], [
                'name' => $data['name'],
                'yield_quantity' => $data['yield_quantity'],
                'yield_unit_id' => $data['yield_unit_id'] ?? null,
                'is_active' => true,
                'created_by' => $request->user()->id,
            ]);
            $recipe->lines()->delete();
            foreach ($data['ingredients'] as $ingredient) {
                $recipe->lines()->create($ingredient);
            }
            $recipe->estimated_cost = $recipe->lines()->selectRaw('COALESCE(SUM(quantity * unit_cost_snapshot), 0) total')->value('total');
            $recipe->version++;
            $recipe->save();
        });

        return back()->with('status', ['success' => 1, 'msg' => 'Recipe and food-cost mapping saved.']);
    }

    public function destroy(int $recipe, RestaurantContextService $context)
    {
        $this->authorizeManage();
        Recipe::where('business_id', $context->businessId())->whereKey($recipe)->firstOrFail()->update(['is_active' => false]);

        return back()->with('status', ['success' => 1, 'msg' => 'Recipe disabled; historical food-cost records were preserved.']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'menu_product_id' => ['required', 'integer'],
            'menu_variation_id' => ['nullable', 'integer'],
            'yield_quantity' => ['required', 'numeric', 'gt:0'],
            'yield_unit_id' => ['nullable', 'integer'],
            'ingredients' => ['required', 'array', 'min:1'],
            'ingredients.*.ingredient_product_id' => ['required', 'integer'],
            'ingredients.*.ingredient_variation_id' => ['required', 'integer'],
            'ingredients.*.quantity' => ['required', 'numeric', 'gt:0'],
            'ingredients.*.unit_id' => ['nullable', 'integer'],
            'ingredients.*.waste_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'ingredients.*.unit_cost_snapshot' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    private function assertProductAndIngredients(int $businessId, array $data): void
    {
        Product::where('business_id', $businessId)->whereKey($data['menu_product_id'])->firstOrFail();
        if (! empty($data['menu_variation_id'])) {
            Variation::where('product_id', $data['menu_product_id'])->whereKey($data['menu_variation_id'])->firstOrFail();
        }
        foreach ($data['ingredients'] as $ingredient) {
            Product::where('business_id', $businessId)->whereKey($ingredient['ingredient_product_id'])->where('enable_stock', true)->firstOrFail();
            Variation::where('product_id', $ingredient['ingredient_product_id'])->whereKey($ingredient['ingredient_variation_id'])->firstOrFail();
        }
    }

    private function authorizeView(): void
    {
        if (! auth()->user()->can('restaurant.recipes.view') && ! auth()->user()->can('product.view')) {
            abort(403, 'Unauthorized action.');
        }
    }

    private function authorizeManage(): void
    {
        if (! auth()->user()->can('restaurant.recipes.manage')) {
            abort(403, 'Unauthorized action.');
        }
    }
}
