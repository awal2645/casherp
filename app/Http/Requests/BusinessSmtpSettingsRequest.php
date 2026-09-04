<?php

namespace App\Http\Requests;

use App\Rules\PublicSmtpHost;
use App\System;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BusinessSmtpSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) optional($this->user())->can('business_settings.access');
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        foreach (['provider', 'host', 'username', 'password', 'encryption', 'from_address', 'from_name', 'test_recipient'] as $key) {
            if ($this->has($key) && is_string($this->input($key))) {
                $values[$key] = trim($this->input($key));
            }
        }

        $legacyMap = [
            'mail_host' => 'host',
            'mail_port' => 'port',
            'mail_username' => 'username',
            'mail_password' => 'password',
            'mail_encryption' => 'encryption',
            'mail_from_address' => 'from_address',
            'mail_from_name' => 'from_name',
            'use_superadmin_settings' => 'use_system_settings',
        ];
        foreach ($legacyMap as $legacy => $canonical) {
            if (! $this->has($canonical) && $this->has($legacy)) {
                $values[$canonical] = is_string($this->input($legacy))
                    ? trim($this->input($legacy))
                    : $this->input($legacy);
            }
        }

        if (array_key_exists('encryption', $values) && in_array(strtolower($values['encryption']), ['', 'none', 'null'], true)) {
            $values['encryption'] = null;
        }
        if (! empty($values['from_address'])) {
            $values['from_address'] = mb_strtolower($values['from_address']);
        }
        if (! empty($values['test_recipient'])) {
            $values['test_recipient'] = mb_strtolower($values['test_recipient']);
        }

        $values['use_system_settings'] = filter_var(
            $values['use_system_settings'] ?? $this->input('use_system_settings', false),
            FILTER_VALIDATE_BOOLEAN
        );
        $values['clear_password'] = $this->boolean('clear_password');
        if ($this->routeIs('business.smtp-settings.test', 'business.smtp-settings.legacy-test')
            && empty($values['test_recipient'])
            && ! empty($values['from_address'])) {
            $values['test_recipient'] = $values['from_address'];
        }

        $this->merge($values);
    }

    public function rules(): array
    {
        if ($this->isMethod('GET')) {
            return [];
        }

        $testing = $this->routeIs('business.smtp-settings.test', 'business.smtp-settings.legacy-test');
        $customRequired = Rule::requiredIf(! $this->boolean('use_system_settings'));

        return [
            'use_system_settings' => ['required', 'boolean'],
            'provider' => ['nullable', Rule::in(array_keys(config('casherp_mail.provider_presets', [])))],
            'host' => [$customRequired, 'nullable', 'string', 'max:253', new PublicSmtpHost],
            'port' => [$customRequired, 'nullable', 'integer', 'between:1,65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:4096'],
            'clear_password' => ['nullable', 'boolean'],
            'encryption' => ['nullable', Rule::in(['tls', 'ssl'])],
            'from_address' => [$customRequired, 'nullable', 'email:rfc', 'max:255'],
            'from_name' => [$customRequired, 'nullable', 'string', 'max:255'],
            'timeout' => ['nullable', 'integer', 'between:5,60'],
            'test_recipient' => [Rule::requiredIf($testing), 'nullable', 'email:rfc', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->isMethod('GET')) {
                return;
            }

            if ($this->boolean('use_system_settings')
                && ! (bool) System::getProperty('allow_email_settings_to_businesses')) {
                $validator->errors()->add(
                    'use_system_settings',
                    'The shared system email service is not enabled for companies.'
                );
            }
        });
    }
}
