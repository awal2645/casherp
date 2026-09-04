<?php

namespace App\Services;

use App\BusinessDocumentSetting;
use App\DocumentType;
use Illuminate\Support\Facades\DB;

class BusinessDocumentNumberService
{
    public function next(int $businessId, DocumentType $type): string
    {
        return DB::transaction(function () use ($businessId, $type) {
            BusinessDocumentSetting::firstOrCreate(
                ['business_id' => $businessId, 'document_type_id' => $type->id],
                [
                    'is_enabled' => true,
                    'source' => 'industry_default',
                    'prefix' => $type->default_prefix,
                    'next_number' => 1,
                ]
            );

            $setting = BusinessDocumentSetting::where('business_id', $businessId)
                ->where('document_type_id', $type->id)
                ->lockForUpdate()
                ->firstOrFail();

            $sequence = max(1, (int) $setting->next_number);
            $prefix = strtoupper(preg_replace('/[^A-Z0-9-]/i', '', $setting->prefix ?: $type->default_prefix));
            $number = sprintf('%s-%s-%06d', $prefix, now()->format('Y'), $sequence);
            $setting->update(['next_number' => $sequence + 1]);

            return $number;
        }, 3);
    }
}
