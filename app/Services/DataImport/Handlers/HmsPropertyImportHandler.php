<?php

namespace App\Services\DataImport\Handlers;

use App\Business;
use App\BusinessDataImport;
use App\BusinessDataImportRow;
use App\BusinessLocation;
use Illuminate\Support\Str;
use Modules\Hms\Entities\HmsProperty;

class HmsPropertyImportHandler extends AbstractDataImportHandler
{
    public function definition(): array
    {
        return [
            'key' => 'hms_properties',
            'label' => 'Hotel properties',
            'description' => 'Hotels, lodges, guest houses and their operating calendars.',
            'permission' => 'hms.manage_rooms',
            'industries' => ['hotel_lodge_guesthouse', 'hotel_with_restaurant'],
            'columns' => [
                'location_name' => ['required' => true, 'example' => 'Mungo Villas', 'aliases' => ['business_location']],
                'name' => ['required' => true, 'example' => 'Mungo Villas', 'aliases' => ['property_name', 'hotel_name']],
                'code' => ['required' => true, 'example' => 'MVL', 'aliases' => ['property_code', 'hotel_code']],
                'timezone' => ['example' => 'Africa/Lusaka'],
                'business_date' => ['example' => '2026-09-03', 'aliases' => ['operating_date']],
                'default_check_in_time' => ['example' => '14:00', 'aliases' => ['check_in_time']],
                'default_check_out_time' => ['example' => '11:00', 'aliases' => ['check_out_time']],
                'is_active' => ['example' => 'yes', 'aliases' => ['active']],
            ],
        ];
    }

    protected function rules(Business $business): array
    {
        return [
            'location_name' => ['required', 'string', 'max:191'],
            'name' => ['required', 'string', 'max:191'],
            'code' => ['required', 'alpha_dash', 'max:40'],
            'timezone' => ['nullable', 'timezone'],
            'business_date' => ['nullable', 'date_format:Y-m-d'],
            'default_check_in_time' => ['nullable', 'date_format:H:i'],
            'default_check_out_time' => ['nullable', 'date_format:H:i'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function normalize(array $data, Business $business, ?BusinessLocation $location): array
    {
        $data['code'] = isset($data['code']) ? Str::upper(trim($data['code'])) : null;
        $data['is_active'] = $this->toBoolean($data['is_active'] ?? null);

        return $data;
    }

    protected function referenceErrors(array $data, Business $business, ?BusinessLocation $location): array
    {
        return ! empty($data['location_name']) && ! $this->resolveLocation($business, $location, $data['location_name'])
            ? ['location_name' => 'The company location was not found. Create the location first.'] : [];
    }

    public function importRow(BusinessDataImport $import, array $data): array
    {
        $location = $this->resolveLocation($import->business, $import->location, $data['location_name']);
        $existing = HmsProperty::withTrashed()->where('business_id', $import->business_id)
            ->where('code', $data['code'])->first();
        $timezone = $data['timezone'] ?? $import->business->time_zone ?? config('app.timezone', 'UTC');
        $attributes = $this->withoutNulls([
            'location_id' => $location->id,
            'currency_id' => $import->business->currency_id,
            'name' => $data['name'],
            'code' => $data['code'],
            'timezone' => $data['timezone'] ?? null,
            'business_date' => $data['business_date'] ?? null,
            'default_check_in_time' => $data['default_check_in_time'] ?? null,
            'default_check_out_time' => $data['default_check_out_time'] ?? null,
            'is_active' => $data['is_active'] ?? null,
        ]);
        if ($existing) {
            if ($existing->trashed()) {
                throw new \RuntimeException('An archived hotel property already uses this code. Restore it before importing updates.');
            }
            return $this->handleExisting($import, $existing, $attributes);
        }
        $property = HmsProperty::create($attributes + [
            'business_id' => $import->business_id,
            'created_by' => $import->approved_by ?: $import->uploaded_by,
            'timezone' => $timezone,
            'business_date' => $data['business_date'] ?? now($timezone)->toDateString(),
            'default_check_in_time' => $data['default_check_in_time'] ?? '14:00',
            'default_check_out_time' => $data['default_check_out_time'] ?? '11:00',
            'is_active' => $data['is_active'] ?? true,
        ]);

        return $this->result($property, true);
    }

    public function rollbackRow(BusinessDataImport $import, BusinessDataImportRow $row): void
    {
        $this->rollbackModel($import, $row, HmsProperty::class);
    }

    protected function assertCanDeleteCreated(\Illuminate\Database\Eloquent\Model $model): void
    {
        $this->assertNoDependencies($model, [
            ['hms_room_types', 'hms_property_id'],
            ['hms_rooms', 'hms_property_id'],
            ['transactions', 'hms_property_id'],
            ['hms_booking_lines', 'hms_property_id'],
            ['hms_rate_plans', 'hms_property_id'],
            ['hms_group_bookings', 'hms_property_id'],
            ['hms_folios', 'hms_property_id'],
            ['hms_cashier_shifts', 'hms_property_id'],
            ['hms_night_audits', 'hms_property_id'],
            ['hms_operational_tasks', 'hms_property_id'],
            ['hms_channel_connections', 'hms_property_id'],
            ['hms_revenue_budgets', 'hms_property_id'],
        ]);
    }
}
