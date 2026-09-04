<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Product;
use App\Restaurant\KitchenStation;
use App\Restaurant\ProductStation;
use App\Services\RestaurantContextService;
use App\Variation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class KitchenStationController extends Controller
{
    public function index(RestaurantContextService $context)
    {
        $this->authorizeManage();
        $businessId = $context->businessId();
        $locationIds = $context->permittedLocationIds($businessId);
        $stations = KitchenStation::where('business_id', $businessId)
            ->when($locationIds !== null, fn ($q) => $q->whereIn('location_id', $locationIds))
            ->withCount('productMappings')
            ->orderBy('location_id')->orderBy('sort_order')->get();
        $locations = \App\BusinessLocation::forDropdown($businessId);
        $products = Product::where('business_id', $businessId)->active()->orderBy('name')->pluck('name', 'id');
        $variations = Variation::join('products as product', 'product.id', '=', 'variations.product_id')
            ->where('product.business_id', $businessId)
            ->orderBy('product.name')
            ->pluck(DB::raw("CONCAT(product.name, ' - ', variations.name, ' (', variations.sub_sku, ')')"), 'variations.id');

        return view('restaurant.operations.stations', compact('stations', 'locations', 'products', 'variations'));
    }

    public function store(Request $request, RestaurantContextService $context)
    {
        $this->authorizeManage();
        $businessId = $context->businessId();
        $data = $request->validate([
            'location_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:40'],
            'station_type' => ['required', Rule::in(array_keys(config('restaurant_operations.station_types', [])))],
            'printer_id' => ['nullable', 'integer'],
            'service_level_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $context->assertLocation($businessId, (int) $data['location_id'], $request->user());
        $code = Str::upper(Str::slug($data['code'] ?: $data['name'], '_'));
        if (KitchenStation::where('business_id', $businessId)->where('location_id', $data['location_id'])->where('code', $code)->exists()) {
            return back()->withErrors(['code' => 'Station code already exists at this location.'])->withInput();
        }

        DB::transaction(function () use ($data, $businessId, $code) {
            if (! empty($data['is_default'])) {
                KitchenStation::where('business_id', $businessId)->where('location_id', $data['location_id'])->update(['is_default' => false]);
            }
            KitchenStation::create($data + [
                'business_id' => $businessId,
                'code' => $code,
                'is_default' => ! empty($data['is_default']),
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            ]);
        });

        return back()->with('status', ['success' => 1, 'msg' => 'Kitchen station created.']);
    }

    public function update(Request $request, int $station, RestaurantContextService $context)
    {
        $this->authorizeManage();
        $businessId = $context->businessId();
        $model = KitchenStation::where('business_id', $businessId)->whereKey($station)->firstOrFail();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'station_type' => ['required', Rule::in(array_keys(config('restaurant_operations.station_types', [])))],
            'printer_id' => ['nullable', 'integer'],
            'service_level_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $context->assertLocation($businessId, (int) $model->location_id, $request->user());
        DB::transaction(function () use ($model, $data, $businessId) {
            if (! empty($data['is_default'])) {
                KitchenStation::where('business_id', $businessId)->where('location_id', $model->location_id)->where('id', '!=', $model->id)->update(['is_default' => false]);
            }
            $model->update($data + ['is_default' => ! empty($data['is_default']), 'is_active' => ! empty($data['is_active'])]);
        });

        return back()->with('status', ['success' => 1, 'msg' => 'Kitchen station updated.']);
    }

    public function destroy(int $station, RestaurantContextService $context)
    {
        $this->authorizeManage();
        $businessId = $context->businessId();
        $model = KitchenStation::where('business_id', $businessId)->whereKey($station)->firstOrFail();
        $context->assertLocation($businessId, (int) $model->location_id);
        if ($model->tickets()->whereIn('status', ['queued', 'accepted', 'preparing', 'ready'])->exists()) {
            return back()->withErrors(['station' => 'Complete or transfer active tickets before disabling this station.']);
        }
        $model->delete();

        return back()->with('status', ['success' => 1, 'msg' => 'Kitchen station disabled.']);
    }

    public function mapProduct(Request $request, int $station, RestaurantContextService $context)
    {
        $this->authorizeManage();
        $businessId = $context->businessId();
        $model = KitchenStation::where('business_id', $businessId)->whereKey($station)->firstOrFail();
        $context->assertLocation($businessId, (int) $model->location_id, $request->user());
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'variation_id' => ['nullable', 'integer'],
            'preparation_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);
        Product::where('business_id', $businessId)->whereKey($data['product_id'])->firstOrFail();
        if (! empty($data['variation_id'])) {
            Variation::where('product_id', $data['product_id'])->whereKey($data['variation_id'])->firstOrFail();
        }
        ProductStation::updateOrCreate([
            'business_id' => $businessId,
            'station_id' => $model->id,
            'product_id' => $data['product_id'],
            'variation_id' => $data['variation_id'] ?? null,
        ], [
            'preparation_minutes' => $data['preparation_minutes'] ?? null,
            'priority' => $data['priority'] ?? 0,
        ]);

        return back()->with('status', ['success' => 1, 'msg' => 'Menu item routing saved.']);
    }

    private function authorizeManage(): void
    {
        if (! auth()->user()->can('restaurant.stations.manage')) {
            abort(403, 'Unauthorized action.');
        }
    }
}
