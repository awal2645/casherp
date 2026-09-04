<?php

namespace App\Services\DataImport\Handlers;

use App\Business;
use App\BusinessDataImport;
use App\BusinessDataImportRow;
use App\BusinessLocation;
use App\Property;
use Illuminate\Validation\Rule;

class PropertyImportHandler extends AbstractDataImportHandler
{
    public function definition(): array
    {
        return [
            'key' => 'properties',
            'label' => 'Real-estate properties',
            'description' => 'Property portfolios and operating locations. Import units separately after properties.',
            'permission' => 'property.manage',
            'industries' => ['property_management_rentals'],
            'columns' => [
                'location_name' => ['example' => 'Lusaka Portfolio', 'aliases' => ['business_location']],
                'property_name' => ['required' => true, 'example' => 'Cairo Road Office Park', 'aliases' => ['name']],
                'portfolio_category' => ['required' => true, 'example' => 'commercial', 'aliases' => ['category', 'type']],
                'property_subtype' => ['required' => true, 'example' => 'office_park', 'aliases' => ['subtype']],
                'address' => ['example' => 'Cairo Road, Lusaka', 'aliases' => ['physical_address']],
                'description' => ['example' => 'Multi-tenant office park with controlled access'],
                'amenities' => ['example' => 'Parking;Security;Backup power', 'aliases' => ['facilities']],
                'is_active' => ['example' => 'yes', 'aliases' => ['active']],
            ],
        ];
    }

    protected function rules(Business $business): array
    {
        return [
            'location_name' => ['nullable', 'string', 'max:191'],
            'property_name' => ['required', 'string', 'max:191'],
            'portfolio_category' => ['required', Rule::in(array_keys(config('property_management.portfolio_categories', [])))],
            'property_subtype' => ['required', Rule::in(array_keys(config('property_management.property_subtypes', [])))],
            'address' => ['nullable', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:5000'],
            'amenities' => ['nullable'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function normalize(array $data, Business $business, ?BusinessLocation $location): array
    {
        foreach (['portfolio_category', 'property_subtype'] as $key) {
            if (! empty($data[$key])) {
                $data[$key] = strtolower(str_replace([' ', '-'], '_', trim($data[$key])));
            }
        }
        $data['is_active'] = $this->toBoolean($data['is_active'] ?? null);
        $data['amenities'] = isset($data['amenities'])
            ? collect(preg_split('/[,;\r\n]+/', (string) $data['amenities']))
                ->map(fn ($amenity) => trim($amenity))->filter()->unique()->take(100)->values()->all()
            : null;

        return $data;
    }

    protected function referenceErrors(array $data, Business $business, ?BusinessLocation $location): array
    {
        return ! empty($data['location_name']) && ! $this->resolveLocation($business, $location, $data['location_name'])
            ? ['location_name' => 'The company location was not found. Create it first or correct the location name.']
            : [];
    }

    public function importRow(BusinessDataImport $import, array $data): array
    {
        $business = $import->business;
        $location = $this->resolveLocation($business, $import->location, $data['location_name'] ?? null);
        $existing = Property::where('business_id', $import->business_id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($data['property_name'])])
            ->first();
        $attributes = $this->withoutNulls([
            'name' => $data['property_name'],
            'type' => $data['portfolio_category'],
            'portfolio_category' => $data['portfolio_category'],
            'property_subtype' => $data['property_subtype'],
            'address' => $data['address'] ?? null,
            'description' => $data['description'] ?? null,
            'amenities' => $data['amenities'] ?? null,
            'is_active' => $data['is_active'] ?? null,
            'business_location_id' => optional($location)->id,
        ]);
        if ($existing) {
            return $this->handleExisting($import, $existing, $attributes);
        }
        $property = Property::create($attributes + [
            'business_id' => $import->business_id,
            'amenities' => $data['amenities'] ?? [],
            'is_active' => $data['is_active'] ?? true,
        ]);

        return $this->result($property, true);
    }

    public function rollbackRow(BusinessDataImport $import, BusinessDataImportRow $row): void
    {
        $this->rollbackModel($import, $row, Property::class);
    }

    protected function assertCanDeleteCreated(\Illuminate\Database\Eloquent\Model $model): void
    {
        $this->assertNoDependencies($model, [
            ['property_units', 'property_id'],
            ['property_viewing_requests', 'property_id'],
            ['property_dashboard_preferences', 'default_property_id'],
        ]);
    }
}
