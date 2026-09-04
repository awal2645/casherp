<?php

namespace App\Services\DataImport\Handlers;

use App\Business;
use App\BusinessDataImport;
use App\BusinessDataImportRow;
use App\Contact;
use Illuminate\Validation\Rule;

class ContactImportHandler extends AbstractDataImportHandler
{
    public function definition(): array
    {
        return [
            'key' => 'contacts',
            'label' => 'Customers and suppliers',
            'description' => 'Company contacts with international address and tax details.',
            'permission' => null,
            'industries' => ['*'],
            'columns' => [
                'type' => ['required' => true, 'example' => 'customer', 'aliases' => ['contact_type']],
                'name' => ['required' => true, 'example' => 'Acme Trading Ltd', 'aliases' => ['contact_name', 'full_name']],
                'business_name' => ['example' => 'Acme Trading Ltd', 'aliases' => ['company_name']],
                'contact_id' => ['example' => 'CUST-1001', 'aliases' => ['customer_code', 'supplier_code']],
                'email' => ['example' => 'accounts@example.com', 'aliases' => ['email_address']],
                'mobile' => ['example' => '+260 970 000 000', 'aliases' => ['phone', 'phone_number']],
                'alternate_number' => ['example' => '+260 211 000 000', 'aliases' => ['alternative_phone']],
                'tax_number' => ['example' => 'TPIN-123456', 'aliases' => ['tax_id', 'vat_number']],
                'address_line_1' => ['example' => '10 Cairo Road', 'aliases' => ['address', 'street']],
                'city' => ['example' => 'Lusaka'],
                'state' => ['example' => 'Lusaka Province', 'aliases' => ['province', 'region']],
                'country' => ['example' => 'Zambia'],
                'zip_code' => ['example' => '10101', 'aliases' => ['postal_code']],
            ],
        ];
    }

    protected function rules(Business $business): array
    {
        return [
            'type' => ['required', Rule::in(['customer', 'supplier', 'both'])],
            'name' => ['required', 'string', 'max:191'],
            'business_name' => ['nullable', 'string', 'max:191'],
            'contact_id' => ['nullable', 'string', 'max:191'],
            'email' => ['nullable', 'email:rfc', 'max:191'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'alternate_number' => ['nullable', 'string', 'max:50'],
            'tax_number' => ['nullable', 'string', 'max:191'],
            'address_line_1' => ['nullable', 'string', 'max:191'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'zip_code' => ['nullable', 'string', 'max:20'],
        ];
    }

    protected function normalize(array $data, Business $business, ?\App\BusinessLocation $location): array
    {
        if (! empty($data['type'])) {
            $data['type'] = strtolower(str_replace([' ', '/'], ['_', '_'], $data['type']));
        }
        if (! empty($data['email'])) {
            $data['email'] = mb_strtolower($data['email']);
        }

        return $data;
    }

    public function importRow(BusinessDataImport $import, array $data): array
    {
        $query = Contact::where('business_id', $import->business_id);
        $existing = null;
        if (! empty($data['contact_id'])) {
            $existing = (clone $query)->where('contact_id', $data['contact_id'])->first();
        }
        if (! $existing && ! empty($data['email'])) {
            $existing = (clone $query)->whereRaw('LOWER(email) = ?', [mb_strtolower($data['email'])])->first();
        }
        if (! $existing && ! empty($data['mobile'])) {
            $existing = (clone $query)->where('mobile', $data['mobile'])->first();
        }

        $attributes = $this->withoutNulls(array_intersect_key($data, array_flip(array_keys($this->definition()['columns']))));
        if ($existing) {
            return $this->handleExisting($import, $existing, $attributes);
        }

        $contact = Contact::create($attributes + [
            'business_id' => $import->business_id,
            'created_by' => $import->approved_by ?: $import->uploaded_by,
            'contact_status' => 'active',
        ]);

        return $this->result($contact, true);
    }

    public function rollbackRow(BusinessDataImport $import, BusinessDataImportRow $row): void
    {
        $this->rollbackModel($import, $row, Contact::class);
    }

    protected function assertCanDeleteCreated(\Illuminate\Database\Eloquent\Model $model): void
    {
        $this->assertNoDependencies($model, [
            ['transactions', 'contact_id'],
            ['bookings', 'contact_id'],
            ['users', 'crm_contact_id'],
            ['property_leases', 'contact_id'],
            ['property_maintenance_tickets', 'contact_id'],
            ['property_viewing_requests', 'contact_id'],
            ['business_documents', 'contact_id'],
            ['hms_guest_profiles', 'contact_id'],
            ['hms_group_bookings', 'organizer_contact_id'],
            ['hms_rate_plans', 'corporate_contact_id'],
            ['hms_folios', 'contact_id'],
        ]);
    }
}
