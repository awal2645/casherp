<?php

namespace App\Services\DataImport\Handlers;

use App\Business;
use App\BusinessDataImport;
use App\BusinessDataImportRow;
use App\BusinessLocation;
use App\Property;
use App\PropertyUnit;
use Illuminate\Validation\Rule;

class PropertyUnitImportHandler extends AbstractDataImportHandler
{
    public function definition(): array
    {
        return [
            'key' => 'property_units',
            'label' => 'Property units and spaces',
            'description' => 'Apartments, shops, offices, stalls, warehouses and other rentable spaces.',
            'permission' => 'property.manage',
            'industries' => ['property_management_rentals'],
            'columns' => [
                'property_name' => ['required' => true, 'example' => 'Cairo Road Office Park', 'aliases' => ['property']],
                'unit_code' => ['required' => true, 'example' => 'OFFICE-A12', 'aliases' => ['unit_number', 'space_code']],
                'unit_type' => ['example' => 'office', 'aliases' => ['space_type']],
                'listing_purpose' => ['example' => 'lease', 'aliases' => ['purpose']],
                'monthly_rent' => ['example' => '12500.00', 'aliases' => ['rent', 'base_rent']],
                'asking_price' => ['example' => '950000.00', 'aliases' => ['sale_price']],
                'available_from' => ['example' => '2026-10-01', 'aliases' => ['availability_date']],
                'is_listed' => ['example' => 'yes', 'aliases' => ['published']],
                'listing_status' => ['example' => 'active'],
                'status' => ['example' => 'vacant', 'aliases' => ['occupancy_status']],
            ],
        ];
    }

    protected function rules(Business $business): array
    {
        return [
            'property_name' => ['required', 'string', 'max:191'],
            'unit_code' => ['required', 'string', 'max:191'],
            'unit_type' => ['nullable', 'string', 'max:100'],
            'listing_purpose' => ['nullable', Rule::in(['rent', 'lease', 'sale', 'not_listed'])],
            'monthly_rent' => ['nullable', 'numeric', 'min:0', 'max:999999999999999999'],
            'asking_price' => ['nullable', 'required_if:listing_purpose,sale', 'numeric', 'min:0', 'max:999999999999999999'],
            'available_from' => ['nullable', 'date_format:Y-m-d'],
            'is_listed' => ['nullable', 'boolean'],
            'listing_status' => ['nullable', Rule::in(['draft', 'active', 'paused', 'under_offer', 'closed'])],
            'status' => ['nullable', Rule::in(['vacant', 'occupied', 'reserved', 'maintenance', 'unavailable'])],
        ];
    }

    protected function normalize(array $data, Business $business, ?BusinessLocation $location): array
    {
        foreach (['status', 'listing_purpose', 'listing_status'] as $key) {
            if (! empty($data[$key])) {
                $data[$key] = strtolower(str_replace([' ', '-'], '_', $data[$key]));
            }
        }
        $data['is_listed'] = $this->toBoolean($data['is_listed'] ?? null);

        return $data;
    }

    protected function referenceErrors(array $data, Business $business, ?BusinessLocation $location): array
    {
        if (empty($data['property_name'])) {
            return [];
        }

        $property = Property::where('business_id', $business->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($data['property_name'])])->first();
        if (! $property) {
            return ['property_name' => 'The property was not found in this company. Import properties first.'];
        }

        $errors = [];
        $unit = PropertyUnit::where('property_id', $property->id)
            ->where('unit_code', $data['unit_code'] ?? '')->first();
        $hasActiveLease = $unit && $unit->leases()->where('status', 'active')->exists();
        if (($data['status'] ?? null) === 'occupied' && ! $hasActiveLease) {
            $errors['status'] = 'A unit can be marked occupied only by an active lease. Import it as vacant and create the lease in CashERP.';
        }
        if ($hasActiveLease && isset($data['status']) && $data['status'] !== 'occupied') {
            $errors['status'] = 'This unit has an active lease, so its occupancy status must remain occupied.';
        }
        if (($data['listing_purpose'] ?? null) === 'not_listed' && ($data['is_listed'] ?? false)) {
            $errors['is_listed'] = 'An internal/not-listed unit cannot be published.';
        }

        return $errors;
    }

    public function importRow(BusinessDataImport $import, array $data): array
    {
        $property = Property::where('business_id', $import->business_id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($data['property_name'])])->firstOrFail();
        $existing = PropertyUnit::where('property_id', $property->id)->where('unit_code', $data['unit_code'])->first();
        $attributes = $this->withoutNulls([
            'unit_type' => $data['unit_type'] ?? null,
            'listing_purpose' => $data['listing_purpose'] ?? null,
            'monthly_rent' => $data['monthly_rent'] ?? null,
            'asking_price' => $data['asking_price'] ?? null,
            'available_from' => $data['available_from'] ?? null,
            'is_listed' => $data['is_listed'] ?? null,
            'listing_status' => $data['listing_status'] ?? null,
            'status' => $data['status'] ?? null,
        ]);
        if ($existing) {
            if (empty($attributes)) {
                return $this->duplicateResult($existing);
            }
            return $this->handleExisting($import, $existing, $attributes);
        }
        $unit = PropertyUnit::create($attributes + [
            'property_id' => $property->id,
            'unit_code' => $data['unit_code'],
            'listing_purpose' => $data['listing_purpose'] ?? 'rent',
            'monthly_rent' => $data['monthly_rent'] ?? 0,
            'is_listed' => $data['is_listed'] ?? false,
            'listing_status' => $data['listing_status'] ?? 'draft',
            'status' => $data['status'] ?? 'vacant',
        ]);

        return $this->result($unit, true);
    }

    protected function scopeRollbackQuery($query, BusinessDataImport $import): void
    {
        $query->whereHas('property', fn ($property) => $property->where('business_id', $import->business_id));
    }

    public function rollbackRow(BusinessDataImport $import, BusinessDataImportRow $row): void
    {
        $this->rollbackModel($import, $row, PropertyUnit::class);
    }

    protected function assertCanDeleteCreated(\Illuminate\Database\Eloquent\Model $model): void
    {
        $this->assertNoDependencies($model, [
            ['property_leases', 'property_unit_id'],
            ['property_maintenance_tickets', 'property_unit_id'],
            ['property_viewing_requests', 'property_unit_id'],
        ]);
    }
}
