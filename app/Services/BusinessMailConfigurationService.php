<?php

namespace App\Services;

use App\Business;
use App\BusinessEmailConfigurationEvent;
use App\System;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class BusinessMailConfigurationService
{
    public const SETTINGS_VERSION = 2;

    public function publicPayload(Business $business): array
    {
        $this->migrateLegacyCredential($business);
        $settings = $this->normaliseStoredSettings($business->email_settings ?? []);
        $lastEvent = null;

        if (Schema::hasTable('business_email_configuration_events')) {
            $event = BusinessEmailConfigurationEvent::where('business_id', $business->id)
                ->latest('id')
                ->first();
            if ($event) {
                $lastEvent = [
                    'action' => $event->action,
                    'status' => $event->status,
                    'failure_category' => $event->failure_category,
                    'recipient_masked' => $event->recipient_masked,
                    'duration_ms' => $event->duration_ms,
                    'occurred_at' => optional($event->created_at)->toIso8601String(),
                    'correlation_id' => $event->correlation_id,
                ];
            }
        }

        return [
            'settings' => [
                'version' => self::SETTINGS_VERSION,
                'use_system_settings' => (bool) ($settings['use_system_settings'] ?? false),
                'provider' => $settings['provider'] ?? $this->detectProvider((string) ($settings['host'] ?? '')),
                'host' => (string) ($settings['host'] ?? ''),
                'port' => (int) ($settings['port'] ?? 587),
                'username' => (string) ($settings['username'] ?? ''),
                'encryption' => $settings['encryption'] ?? 'tls',
                'from_address' => (string) ($settings['from_address'] ?? ''),
                'from_name' => (string) ($settings['from_name'] ?? $business->name),
                'timeout' => (int) ($settings['timeout'] ?? config('casherp_mail.default_timeout', 15)),
                'password_configured' => $this->hasPassword($settings),
            ],
            'can_use_system_settings' => (bool) System::getProperty('allow_email_settings_to_businesses'),
            'system_sender' => (string) config('mail.system_smtp.from_address', ''),
            'provider_presets' => config('casherp_mail.provider_presets', []),
            'default_test_recipient' => (string) optional(auth()->user())->email,
            'last_event' => $lastEvent,
        ];
    }

    public function store(Business $business, array $input): array
    {
        $settings = $this->prepareForPersistence($input, $business->email_settings ?? []);
        $business->email_settings = $settings;
        $business->save();

        if ((int) session('user.business_id') === (int) $business->id) {
            session()->put('business.email_settings', $settings);
        }

        return $settings;
    }

    public function prepareForPersistence(array $input, array $existing = []): array
    {
        $current = $this->normaliseStoredSettings($existing);
        $usingSystem = filter_var($input['use_system_settings'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $settings = [
            'version' => self::SETTINGS_VERSION,
            'use_system_settings' => $usingSystem,
            'provider' => (string) ($input['provider'] ?? $current['provider'] ?? 'custom'),
            'host' => (string) ($input['host'] ?? $current['host'] ?? ''),
            'port' => (int) ($input['port'] ?? $current['port'] ?? 587),
            'username' => (string) ($input['username'] ?? $current['username'] ?? ''),
            'encryption' => $this->normaliseEncryption($input['encryption'] ?? $current['encryption'] ?? 'tls'),
            'from_address' => mb_strtolower((string) ($input['from_address'] ?? $current['from_address'] ?? '')),
            'from_name' => (string) ($input['from_name'] ?? $current['from_name'] ?? ''),
            'timeout' => (int) ($input['timeout'] ?? $current['timeout'] ?? config('casherp_mail.default_timeout', 15)),
        ];

        if (! empty($current['password_encrypted'])) {
            $settings['password_encrypted'] = $current['password_encrypted'];
        } elseif (! empty($current['password'])) {
            $settings['password_encrypted'] = Crypt::encryptString((string) $current['password']);
        }

        if (! empty($input['password'])) {
            $settings['password_encrypted'] = Crypt::encryptString((string) $input['password']);
        }
        if (filter_var($input['clear_password'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            unset($settings['password_encrypted']);
        }

        // Never persist a plaintext secret, including one inherited from the
        // legacy array structure.
        unset($settings['password'], $settings['mail_password']);

        return $settings;
    }

    public function resolvedSettings(array $storedSettings = [], bool $checkSystemPreference = true): array
    {
        $settings = $this->normaliseStoredSettings($storedSettings);
        $wantsSystem = (bool) ($settings['use_system_settings'] ?? false);
        $systemAllowed = (bool) System::getProperty('allow_email_settings_to_businesses');
        $customConfigured = ! empty($settings['host']) && ! empty($settings['from_address']);

        if (! $customConfigured || ($checkSystemPreference && $wantsSystem && $systemAllowed)) {
            return $this->systemSettings();
        }

        $password = null;
        if (! empty($settings['password_encrypted'])) {
            try {
                $password = Crypt::decryptString($settings['password_encrypted']);
            } catch (DecryptException $exception) {
                throw new RuntimeException('The saved SMTP credential cannot be decrypted. Re-enter the SMTP password.');
            }
        } elseif (array_key_exists('password', $settings)) {
            // Read-only compatibility for companies not yet migrated. The
            // next settings save or encryption command removes this value.
            $password = (string) $settings['password'];
        }

        return [
            'transport' => 'smtp',
            'host' => (string) $settings['host'],
            'port' => (int) ($settings['port'] ?? 587),
            'encryption' => $this->normaliseEncryption($settings['encryption'] ?? null),
            'username' => ($settings['username'] ?? '') !== '' ? (string) $settings['username'] : null,
            'password' => $password,
            'timeout' => (int) ($settings['timeout'] ?? config('casherp_mail.default_timeout', 15)),
            'local_domain' => null,
            'from_address' => (string) $settings['from_address'],
            'from_name' => (string) ($settings['from_name'] ?? 'CashERP'),
        ];
    }

    public function applyFromSettings(array $settings = [], bool $checkSystemPreference = true): array
    {
        $resolved = $this->resolvedSettings($settings, $checkSystemPreference);
        $this->applyRuntimeSettings($resolved);

        return $resolved;
    }

    public function withSettings(array $settings, callable $callback, bool $checkSystemPreference = true)
    {
        $previous = $this->captureRuntimeSettings();
        $resolved = $this->applyFromSettings($settings, $checkSystemPreference);

        try {
            return $callback($resolved);
        } finally {
            $this->applyRuntimeSettings($previous);
        }
    }

    public function recordEvent(Business $business, array $attributes): ?BusinessEmailConfigurationEvent
    {
        if (! Schema::hasTable('business_email_configuration_events')) {
            return null;
        }

        $recipient = mb_strtolower(trim((string) ($attributes['recipient'] ?? '')));

        return BusinessEmailConfigurationEvent::create([
            'business_id' => $business->id,
            'user_id' => $attributes['user_id'] ?? null,
            'correlation_id' => $attributes['correlation_id'] ?? (string) Str::uuid(),
            'action' => $attributes['action'],
            'status' => $attributes['status'],
            'provider' => $attributes['provider'] ?? 'custom',
            'recipient_masked' => $recipient !== '' ? $this->maskEmail($recipient) : null,
            'recipient_hash' => $recipient !== '' ? hash_hmac('sha256', $recipient, (string) config('app.key')) : null,
            'failure_category' => $attributes['failure_category'] ?? null,
            'duration_ms' => $attributes['duration_ms'] ?? null,
            'metadata' => $attributes['metadata'] ?? null,
        ]);
    }

    public function migrateLegacyCredential(Business $business, bool $persist = true): bool
    {
        $raw = $business->email_settings ?? [];
        if (! is_array($raw) || empty($raw['mail_password']) || ! empty($raw['password_encrypted'])) {
            return false;
        }

        $business->email_settings = $this->prepareForPersistence([], $raw);
        if ($persist) {
            $business->saveQuietly();
        }

        return true;
    }

    public function classifyFailure(\Throwable $exception): string
    {
        $message = strtolower($exception->getMessage());
        if (str_contains($message, 'authenticate') || str_contains($message, 'authenticator') || str_contains($message, '535')) {
            return 'authentication';
        }
        if (str_contains($message, 'getaddrinfo') || str_contains($message, 'php_network_getaddresses') || str_contains($message, 'name or service not known')) {
            return 'dns';
        }
        if (str_contains($message, 'certificate') || str_contains($message, 'tls') || str_contains($message, 'ssl')) {
            return 'encryption';
        }
        if (str_contains($message, 'timed out') || str_contains($message, 'connection') || str_contains($message, 'refused')) {
            return 'connection';
        }
        if (str_contains($message, 'rate') || str_contains($message, 'too many') || str_contains($message, '421')) {
            return 'rate_limited';
        }
        if (str_contains($message, 'recipient') || str_contains($message, 'mailbox') || str_contains($message, '550')) {
            return 'recipient_rejected';
        }

        return 'delivery';
    }

    public function safeFailureMessage(string $category, string $correlationId): string
    {
        $messages = [
            'authentication' => 'The SMTP server rejected the username or password. For providers such as Google or Microsoft, use an app password when required.',
            'dns' => 'The SMTP hostname could not be resolved. Check the server name and try again.',
            'encryption' => 'A secure connection could not be established. Check whether this server requires TLS on 587 or SSL on 465.',
            'connection' => 'CashERP could not connect to the SMTP server. Check the host, port, firewall, and provider access settings.',
            'rate_limited' => 'The mail provider temporarily limited this request. Wait briefly and try again.',
            'recipient_rejected' => 'The SMTP server rejected the test recipient. Verify the recipient and sender addresses.',
            'delivery' => 'The SMTP server did not accept the test email. Review the settings and try again.',
        ];

        return ($messages[$category] ?? $messages['delivery']).' Reference: '.$correlationId;
    }

    public function detectProvider(string $host): string
    {
        $host = strtolower(trim($host));
        foreach (config('casherp_mail.provider_presets', []) as $key => $preset) {
            if ($key !== 'custom' && ! empty($preset['host']) && strtolower($preset['host']) === $host) {
                return $key;
            }
        }

        return 'custom';
    }

    protected function normaliseStoredSettings(array $settings): array
    {
        return [
            'version' => (int) ($settings['version'] ?? 1),
            'use_system_settings' => (bool) ($settings['use_system_settings'] ?? $settings['use_superadmin_settings'] ?? false),
            'provider' => $settings['provider'] ?? $this->detectProvider((string) ($settings['host'] ?? $settings['mail_host'] ?? '')),
            'host' => $settings['host'] ?? $settings['mail_host'] ?? '',
            'port' => (int) ($settings['port'] ?? $settings['mail_port'] ?? 587),
            'username' => $settings['username'] ?? $settings['mail_username'] ?? '',
            'password_encrypted' => $settings['password_encrypted'] ?? $settings['mail_password_encrypted'] ?? null,
            'password' => $settings['password'] ?? $settings['mail_password'] ?? null,
            'encryption' => $this->normaliseEncryption($settings['encryption'] ?? $settings['mail_encryption'] ?? 'tls'),
            'from_address' => $settings['from_address'] ?? $settings['mail_from_address'] ?? '',
            'from_name' => $settings['from_name'] ?? $settings['mail_from_name'] ?? '',
            'timeout' => (int) ($settings['timeout'] ?? config('casherp_mail.default_timeout', 15)),
        ];
    }

    protected function systemSettings(): array
    {
        $settings = config('mail.system_smtp', []);

        return [
            'transport' => 'smtp',
            'host' => (string) ($settings['host'] ?? ''),
            'port' => (int) ($settings['port'] ?? 587),
            'encryption' => $this->normaliseEncryption($settings['encryption'] ?? null),
            'username' => $settings['username'] ?? null,
            'password' => $settings['password'] ?? null,
            'timeout' => (int) ($settings['timeout'] ?? config('casherp_mail.default_timeout', 15)),
            'local_domain' => $settings['local_domain'] ?? null,
            'from_address' => (string) ($settings['from_address'] ?? config('mail.from.address')),
            'from_name' => (string) ($settings['from_name'] ?? config('mail.from.name', 'CashERP')),
        ];
    }

    protected function captureRuntimeSettings(): array
    {
        $smtp = config('mail.mailers.smtp', []);

        return array_merge($smtp, [
            'transport' => 'smtp',
            'from_address' => (string) config('mail.from.address', ''),
            'from_name' => (string) config('mail.from.name', 'CashERP'),
        ]);
    }

    protected function applyRuntimeSettings(array $settings): void
    {
        $mailer = Arr::only($settings, [
            'transport', 'host', 'port', 'encryption', 'username', 'password', 'timeout', 'local_domain',
        ]);
        $mailer['transport'] = 'smtp';

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp' => $mailer,
            'mail.from.address' => $settings['from_address'] ?? config('mail.from.address'),
            'mail.from.name' => $settings['from_name'] ?? config('mail.from.name'),
        ]);

        // Laravel caches mailer instances. Purging is essential when a queue
        // worker processes messages for different CashERP companies.
        app('mail.manager')->purge('smtp');
    }

    protected function normaliseEncryption($value): ?string
    {
        $value = strtolower(trim((string) $value));

        return in_array($value, ['tls', 'ssl'], true) ? $value : null;
    }

    protected function hasPassword(array $settings): bool
    {
        return ! empty($settings['password_encrypted']) || ! empty($settings['password']);
    }

    protected function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $visible = mb_substr($local, 0, min(2, mb_strlen($local)));

        return $visible.str_repeat('*', max(2, mb_strlen($local) - mb_strlen($visible))).'@'.$domain;
    }
}
