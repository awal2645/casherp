<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSubscriptionPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->can('superadmin');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'max_businesses' => ['required', 'integer', 'min:0'],
            'location_count' => ['required', 'integer', 'min:0'],
            'user_count' => ['required', 'integer', 'min:0'],
            'product_count' => ['required', 'integer', 'min:0'],
            'invoice_count' => ['required', 'integer', 'min:0'],
            'data_import_enabled' => ['nullable', 'boolean'],
            'monthly_import_rows' => ['required', 'integer', 'min:0', 'max:100000000'],
            'max_import_rows_per_file' => ['required', 'integer', 'min:1', 'max:1000000'],
            'max_import_file_size_mb' => ['required', 'integer', 'min:1', 'max:1024'],
            'concurrent_imports' => ['required', 'integer', 'min:1', 'max:100'],
            'import_rollback_days' => ['required', 'integer', 'min:0', 'max:90'],
            'interval' => ['required', Rule::in(['days', 'months', 'years'])],
            'interval_count' => ['required', 'integer', 'min:1'],
            'trial_days' => ['required', 'integer', 'min:0'],
            'price' => ['required', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'mark_package_as_popular' => ['nullable', 'boolean'],
            'is_private' => ['nullable', 'boolean'],
            'is_one_time' => ['nullable', 'boolean'],
            'enable_custom_link' => ['nullable', 'boolean'],
            'custom_link' => ['nullable', 'required_if:enable_custom_link,1', 'url', 'max:2048'],
            'custom_link_text' => ['nullable', 'required_if:enable_custom_link,1', 'string', 'max:255'],
            'businesses' => ['nullable', 'array'],
            'businesses.*' => ['integer', 'exists:business,id'],
            'custom_permissions' => ['nullable', 'array'],
            'custom_permissions.hms_max_properties' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'custom_permissions.hms_finance_operations' => ['nullable', 'boolean'],
            'custom_permissions.hms_revenue_operations' => ['nullable', 'boolean'],
            'custom_permissions.hms_group_operations' => ['nullable', 'boolean'],
            'custom_permissions.hms_guest_experience' => ['nullable', 'boolean'],
            'custom_permissions.hms_channel_manager' => ['nullable', 'boolean'],
            'update_subscriptions' => ['nullable', 'boolean'],
        ];
    }
}
