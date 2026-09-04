<?php

namespace App\Services;

use App\BusinessPaymentMethod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class TenantPaymentMethodService
{
    public const DEFAULTS = [
        'cash' => ['name' => 'Cash', 'kind' => 'cash', 'accepts_money' => true, 'is_enabled' => true],
        'card' => ['name' => 'Visa / Card', 'kind' => 'card', 'accepts_money' => true, 'is_enabled' => true],
        'mobile_money' => ['name' => 'Mobile Money', 'kind' => 'mobile_money', 'accepts_money' => true, 'is_enabled' => true],
        'bank_transfer' => ['name' => 'Bank Transfer', 'kind' => 'bank', 'accepts_money' => true, 'is_enabled' => true],
        'cheque' => ['name' => 'Cheque', 'kind' => 'cheque', 'accepts_money' => true, 'is_enabled' => true],
        'credit' => ['name' => 'Credit (Pay Later)', 'kind' => 'credit', 'accepts_money' => false, 'is_enabled' => true],
        'complimentary' => ['name' => 'Free / Complimentary', 'kind' => 'complimentary', 'accepts_money' => false, 'is_enabled' => true],
        'other' => ['name' => 'Other', 'kind' => 'other', 'accepts_money' => true, 'is_enabled' => true],
        'custom_1' => ['name' => 'Custom payment method 1', 'kind' => 'other', 'accepts_money' => true, 'is_enabled' => false],
        'custom_2' => ['name' => 'Custom payment method 2', 'kind' => 'other', 'accepts_money' => true, 'is_enabled' => false],
        'custom_3' => ['name' => 'Custom payment method 3', 'kind' => 'other', 'accepts_money' => true, 'is_enabled' => false],
        'custom_4' => ['name' => 'Custom payment method 4', 'kind' => 'other', 'accepts_money' => true, 'is_enabled' => false],
        'custom_5' => ['name' => 'Custom payment method 5', 'kind' => 'other', 'accepts_money' => true, 'is_enabled' => false],
    ];

    public function all(int $businessId): Collection
    {
        if (! Schema::hasTable('business_payment_methods')) {
            return collect(self::DEFAULTS)->map(fn ($item, $code) => (object) ($item + ['code' => $code]));
        }
        $existing = BusinessPaymentMethod::forBusiness($businessId)->get()->keyBy('code');
        foreach (self::DEFAULTS as $code => $default) {
            if (! $existing->has($code)) {
                BusinessPaymentMethod::create([
                    'business_id' => $businessId, 'code' => $code, 'name' => $default['name'],
                    'kind' => $default['kind'], 'accepts_money' => $default['accepts_money'],
                    'is_enabled' => $default['is_enabled'], 'sort_order' => (array_search($code, array_keys(self::DEFAULTS), true) + 1) * 10,
                ]);
            }
        }

        return BusinessPaymentMethod::forBusiness($businessId)->orderBy('sort_order')->orderBy('id')->get();
    }

    public function settlementOptions(int $businessId): array
    {
        return $this->all($businessId)->where('is_enabled', true)->where('accepts_money', true)->pluck('name', 'code')->all();
    }

    public function assertSettlementMethod(int $businessId, string $code): void
    {
        if (! array_key_exists($code, $this->settlementOptions($businessId))) {
            throw ValidationException::withMessages(['method' => 'Choose an enabled company payment method that represents money actually received. Credit and complimentary are payment terms, not receipts.']);
        }
    }

    public function update(int $businessId, array $settings): void
    {
        $allowed = array_keys(self::DEFAULTS);
        foreach ($settings as $code => $values) {
            if (! in_array($code, $allowed, true)) {
                continue;
            }
            $default = self::DEFAULTS[$code];
            BusinessPaymentMethod::updateOrCreate(
                ['business_id' => $businessId, 'code' => $code],
                [
                    'name' => trim((string) ($values['name'] ?? '')) ?: $default['name'],
                    'kind' => $default['kind'],
                    'accepts_money' => $default['accepts_money'],
                    'is_enabled' => ! empty($values['is_enabled']),
                    'sort_order' => (array_search($code, $allowed, true) + 1) * 10,
                ]
            );
        }
    }
}
