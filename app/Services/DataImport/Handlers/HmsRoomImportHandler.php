<?php

namespace App\Services\DataImport\Handlers;

use App\Business;
use App\BusinessDataImport;
use App\BusinessDataImportRow;
use App\BusinessLocation;
use Modules\Hms\Entities\HmsProperty;
use Modules\Hms\Entities\HmsRoom;
use Modules\Hms\Entities\HmsRoomType;

class HmsRoomImportHandler extends AbstractDataImportHandler
{
    public function definition(): array
    {
        return [
            'key' => 'hms_rooms',
            'label' => 'Hotel rooms',
            'description' => 'Physical rooms linked to a property and room type.',
            'permission' => 'hms.manage_rooms',
            'industries' => ['hotel_lodge_guesthouse', 'hotel_with_restaurant'],
            'columns' => [
                'property_code' => ['required' => true, 'example' => 'MVL', 'aliases' => ['hotel_code']],
                'room_type' => ['required' => true, 'example' => 'Deluxe King', 'aliases' => ['type', 'category']],
                'room_number' => ['required' => true, 'example' => 'A-101', 'aliases' => ['room_no', 'room_code']],
            ],
        ];
    }

    protected function rules(Business $business): array
    {
        return [
            'property_code' => ['required', 'string', 'max:40'],
            'room_type' => ['required', 'string', 'max:191'],
            'room_number' => ['required', 'string', 'max:100'],
        ];
    }

    protected function normalize(array $data, Business $business, ?BusinessLocation $location): array
    {
        $data['property_code'] = isset($data['property_code']) ? strtoupper($data['property_code']) : null;
        return $data;
    }

    protected function referenceErrors(array $data, Business $business, ?BusinessLocation $location): array
    {
        if (empty($data['property_code']) || empty($data['room_type'])) {
            return [];
        }
        $property = HmsProperty::where('business_id', $business->id)->where('code', $data['property_code'])->first();
        if (! $property) {
            return ['property_code' => 'The hotel property was not found in this company.'];
        }
        return HmsRoomType::where('business_id', $business->id)->where('hms_property_id', $property->id)
            ->whereRaw('LOWER(type) = ?', [mb_strtolower($data['room_type'])])->exists()
            ? [] : ['room_type' => 'The room type was not found for this property. Import room types first.'];
    }

    public function importRow(BusinessDataImport $import, array $data): array
    {
        $property = HmsProperty::where('business_id', $import->business_id)->where('code', $data['property_code'])->firstOrFail();
        $type = HmsRoomType::where('business_id', $import->business_id)->where('hms_property_id', $property->id)
            ->whereRaw('LOWER(type) = ?', [mb_strtolower($data['room_type'])])->firstOrFail();
        $existing = HmsRoom::withTrashed()->where('hms_property_id', $property->id)
            ->where('room_number', $data['room_number'])->first();
        $attributes = ['hms_property_id' => $property->id, 'hms_room_type_id' => $type->id, 'room_number' => $data['room_number']];
        if ($existing) {
            if ($existing->trashed()) {
                throw new \RuntimeException('An archived room already uses this number. Restore it before importing updates.');
            }
            return $this->handleExisting($import, $existing, $attributes);
        }
        $room = HmsRoom::create($attributes);

        return $this->result($room, true);
    }

    protected function scopeRollbackQuery($query, BusinessDataImport $import): void
    {
        $query->whereHas('property', fn ($property) => $property->where('business_id', $import->business_id));
    }

    public function rollbackRow(BusinessDataImport $import, BusinessDataImportRow $row): void
    {
        $this->rollbackModel($import, $row, HmsRoom::class);
    }

    protected function assertCanDeleteCreated(\Illuminate\Database\Eloquent\Model $model): void
    {
        $this->assertNoDependencies($model, [
            ['hms_booking_lines', 'hms_room_id'],
            ['hms_housekeeping_tasks', 'hms_room_id'],
            ['hms_group_room_blocks', 'hms_room_id'],
            ['hms_operational_tasks', 'hms_room_id'],
        ]);
    }
}
