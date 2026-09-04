<?php

namespace App\Services\DataImport\Handlers;

use App\Business;
use App\BusinessDataImport;
use App\BusinessDataImportRow;
use App\BusinessLocation;
use Modules\Hms\Entities\HmsProperty;
use Modules\Hms\Entities\HmsRoomType;

class HmsRoomTypeImportHandler extends AbstractDataImportHandler
{
    public function definition(): array
    {
        return [
            'key' => 'hms_room_types',
            'label' => 'Hotel room types',
            'description' => 'Occupancy-controlled room categories and amenities.',
            'permission' => 'hms.manage_rooms',
            'industries' => ['hotel_lodge_guesthouse', 'hotel_with_restaurant'],
            'columns' => [
                'property_code' => ['required' => true, 'example' => 'MVL', 'aliases' => ['hotel_code']],
                'room_type' => ['required' => true, 'example' => 'Deluxe King', 'aliases' => ['type', 'category']],
                'adults' => ['required' => true, 'example' => '2', 'aliases' => ['no_of_adult']],
                'children' => ['example' => '1', 'aliases' => ['no_of_child']],
                'max_occupancy' => ['required' => true, 'example' => '3', 'aliases' => ['maximum_occupancy']],
                'amenities' => ['example' => 'Wi-Fi;Air conditioning;Breakfast'],
                'description' => ['example' => 'King room with garden view'],
            ],
        ];
    }

    protected function rules(Business $business): array
    {
        return [
            'property_code' => ['required', 'string', 'max:40'],
            'room_type' => ['required', 'string', 'max:191'],
            'adults' => ['required', 'integer', 'min:1', 'max:50'],
            'children' => ['nullable', 'integer', 'min:0', 'max:50'],
            'max_occupancy' => ['required', 'integer', 'min:1', 'max:100'],
            'amenities' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function normalize(array $data, Business $business, ?BusinessLocation $location): array
    {
        $data['property_code'] = isset($data['property_code']) ? strtoupper($data['property_code']) : null;
        return $data;
    }

    protected function referenceErrors(array $data, Business $business, ?BusinessLocation $location): array
    {
        if (empty($data['property_code'])) {
            return [];
        }
        $errors = [];
        if (! HmsProperty::where('business_id', $business->id)->where('code', $data['property_code'])->exists()) {
            $errors['property_code'] = 'The hotel property was not found in this company. Import hotel properties first.';
        }
        if (isset($data['adults'], $data['max_occupancy'])
            && ((int) $data['adults'] + (int) ($data['children'] ?? 0)) > (int) $data['max_occupancy']) {
            $errors['max_occupancy'] = 'Maximum occupancy cannot be lower than the configured adults plus children.';
        }

        return $errors;
    }

    public function importRow(BusinessDataImport $import, array $data): array
    {
        $property = HmsProperty::where('business_id', $import->business_id)->where('code', $data['property_code'])->firstOrFail();
        $existing = HmsRoomType::withTrashed()->where('business_id', $import->business_id)
            ->where('hms_property_id', $property->id)
            ->whereRaw('LOWER(type) = ?', [mb_strtolower($data['room_type'])])->first();
        $attributes = $this->withoutNulls([
            'hms_property_id' => $property->id,
            'type' => $data['room_type'],
            'no_of_adult' => $data['adults'],
            'no_of_child' => $data['children'] ?? null,
            'max_occupancy' => $data['max_occupancy'],
            'amenities' => $data['amenities'] ?? null,
            'description' => $data['description'] ?? null,
        ]);
        if ($existing) {
            if ($existing->trashed()) {
                throw new \RuntimeException('An archived room type already uses this name. Restore it before importing updates.');
            }
            return $this->handleExisting($import, $existing, $attributes);
        }
        $roomType = HmsRoomType::create($attributes + [
            'business_id' => $import->business_id,
            'created_by' => $import->approved_by ?: $import->uploaded_by,
            'no_of_child' => $data['children'] ?? 0,
        ]);

        return $this->result($roomType, true);
    }

    public function rollbackRow(BusinessDataImport $import, BusinessDataImportRow $row): void
    {
        $this->rollbackModel($import, $row, HmsRoomType::class);
    }

    protected function assertCanDeleteCreated(\Illuminate\Database\Eloquent\Model $model): void
    {
        $this->assertNoDependencies($model, [
            ['hms_rooms', 'hms_room_type_id'],
            ['hms_room_type_pricings', 'hms_room_type_id'],
            ['hms_booking_lines', 'hms_room_type_id'],
            ['hms_coupons', 'hms_room_type_id'],
            ['hms_rate_plan_room_types', 'hms_room_type_id'],
        ]);
    }
}
