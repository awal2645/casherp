<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CashErpReleaseAudit extends Command
{
    protected $signature = 'casherp:release-audit {--json : Return machine-readable JSON}';

    protected $description = 'Run a read-only, secret-safe CashERP deployment readiness audit.';

    private array $results = [];

    public function handle(): int
    {
        $this->configurationChecks();
        $databaseReady = $this->databaseChecks();
        if ($databaseReady) {
            $this->profileChecks();
        }
        $this->runtimeChecks();

        $summary = [
            'passed' => $this->count('pass'),
            'warnings' => $this->count('warn'),
            'blockers' => $this->count('fail'),
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'generated_at' => now()->toIso8601String(),
                'summary' => $summary,
                'checks' => $this->results,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(
                ['Status', 'Check', 'Result'],
                collect($this->results)->map(fn ($result) => [
                    strtoupper($result['status']),
                    $result['check'],
                    $result['message'],
                ])->all()
            );
            $this->newLine();
            $this->line("Passed: {$summary['passed']}  Warnings: {$summary['warnings']}  Blockers: {$summary['blockers']}");
            $this->line('This command is read-only. It does not migrate, send mail, charge a card, modify DNS, or change application data.');
        }

        return $summary['blockers'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function configurationChecks(): void
    {
        $key = (string) config('app.key');
        $this->add(
            $key !== '' && $key !== 'base64:',
            'Application key',
            $key !== '' ? 'APP_KEY is configured without exposing it.' : 'APP_KEY is missing.'
        );

        $environment = (string) config('app.env');
        $this->add(
            in_array($environment, ['production', 'live'], true),
            'Application environment',
            "APP_ENV is {$environment}.",
            'warn'
        );
        $this->add(
            ! (bool) config('app.debug'),
            'Debug mode',
            config('app.debug') ? 'APP_DEBUG must be false before public launch.' : 'APP_DEBUG is disabled.'
        );

        $canonical = rtrim((string) config('canonical.url'), '/');
        $applicationUrl = rtrim((string) config('app.url'), '/');
        $this->add(
            $canonical === 'https://www.casherp.com',
            'Canonical application origin',
            $canonical === '' ? 'CANONICAL_URL is missing.' : "CANONICAL_URL is {$canonical}."
        );
        $this->add(
            $applicationUrl === 'https://www.casherp.com',
            'Application URL',
            "APP_URL is {$applicationUrl}."
        );
        $this->add(
            (bool) config('session.secure'),
            'Secure session cookie',
            config('session.secure') ? 'SESSION_SECURE_COOKIE is enabled.' : 'Enable SESSION_SECURE_COOKIE for the HTTPS production origin.'
        );

        $origins = array_map(fn ($origin) => rtrim((string) $origin, '/'), (array) config('cors.allowed_origins', []));
        $this->add(
            in_array('https://www.casherp.com', $origins, true) && ! in_array('*', $origins, true),
            'CORS origin',
            'CORS must allow the canonical origin without using a wildcard.'
        );
    }

    private function databaseChecks(): bool
    {
        try {
            DB::connection()->getPdo();
            $this->add(true, 'Database connection', 'The configured database accepted a connection.');
        } catch (Throwable $exception) {
            $this->add(false, 'Database connection', 'Database connection failed. Review server logs for the protected driver error.');

            return false;
        }

        $requiredTables = [
            'industries', 'features', 'industry_features', 'business_features',
            'document_types', 'industry_document_types', 'business_document_settings',
            'business_documents', 'business_document_lines', 'business_document_payments',
            'security_deposits', 'security_deposit_entries',
            'procurement_documents', 'procurement_approval_steps', 'procurement_supplier_quotes',
            'business_data_imports', 'business_data_import_rows',
            'premium_module_plans', 'business_module_entitlements', 'subscription_payment_attempts',
            'crm_pipelines', 'crm_opportunities', 'crm_activities',
            'company_hub_channels', 'company_hub_posts',
            'hms_folios', 'hms_folio_entries', 'hms_event_bookings', 'restaurant_order_fulfilments',
        ];
        $missing = collect($requiredTables)->reject(fn ($table) => Schema::hasTable($table))->values()->all();
        $this->add(
            empty($missing),
            'Required database schema',
            empty($missing) ? 'All release-critical tables are present.' : 'Missing tables: '.implode(', ', $missing).'. Run all core and module migrations.'
        );

        $columns = [
            'business' => ['industry_id', 'onboarding_settings', 'onboarding_completed_at'],
            'business_documents' => ['source_type', 'source_id', 'asset_type', 'asset_id', 'scenario_code', 'service_start_at', 'service_end_at'],
            'security_deposits' => ['context_type', 'context_id', 'required_amount', 'status'],
            'hms_folios' => ['hms_event_booking_id'],
        ];
        $missingColumns = [];
        foreach ($columns as $table => $names) {
            if (! Schema::hasTable($table)) {
                $missingColumns[] = $table.'.*';
                continue;
            }
            foreach ($names as $name) {
                if (! Schema::hasColumn($table, $name)) {
                    $missingColumns[] = $table.'.'.$name;
                }
            }
        }
        $this->add(
            empty($missingColumns),
            'Required database columns',
            empty($missingColumns) ? 'Industry, source, asset and event-deposit columns are present.' : 'Missing columns: '.implode(', ', $missingColumns).'.'
        );

        return empty($missing) && empty($missingColumns);
    }

    private function profileChecks(): void
    {
        $industries = [
            'general_business',
            'restaurant_food_service',
            'hotel_lodge_guesthouse',
            'hotel_with_restaurant',
            'property_management_rentals',
            'professional_services',
        ];
        $present = DB::table('industries')->whereIn('code', $industries)->pluck('code')->all();
        $missingIndustries = array_values(array_diff($industries, $present));
        $this->add(
            empty($missingIndustries),
            'First six industries',
            empty($missingIndustries) ? 'All six selectable industry profiles exist.' : 'Missing industries: '.implode(', ', $missingIndustries).'.'
        );

        $hrmIndustryCount = DB::table('industry_features as profile')
            ->join('industries', 'industries.id', '=', 'profile.industry_id')
            ->join('features', 'features.id', '=', 'profile.feature_id')
            ->whereIn('industries.code', $industries)
            ->where('features.code', 'hrm')
            ->where('profile.enabled_by_default', true)
            ->distinct()
            ->count('industries.id');
        $this->add(
            $hrmIndustryCount === count($industries),
            'HRM industry availability',
            $hrmIndustryCount === count($industries)
                ? 'HRM is enabled by default for all six industries and remains separate from HMS.'
                : "HRM is enabled for {$hrmIndustryCount} of 6 required industries."
        );

        $expectedDocumentTypes = array_keys((array) config('smart_documents.types', []));
        $presentDocumentTypes = DB::table('document_types')->whereIn('code', $expectedDocumentTypes)->pluck('code')->all();
        $missingDocumentTypes = array_values(array_diff($expectedDocumentTypes, $presentDocumentTypes));
        $this->add(
            empty($missingDocumentTypes),
            'Industry document library',
            empty($missingDocumentTypes)
                ? 'All configured document types exist in the database.'
                : 'Missing document types: '.implode(', ', $missingDocumentTypes).'.'
        );
    }

    private function runtimeChecks(): void
    {
        foreach (['framework/cache', 'framework/sessions', 'framework/views', 'logs'] as $directory) {
            $path = storage_path($directory);
            $this->add(
                is_dir($path) && is_writable($path),
                'Writable storage: '.$directory,
                is_dir($path) && is_writable($path) ? 'Directory exists and is writable.' : 'Create this storage directory and grant the PHP process write access.'
            );
        }

        foreach (['public/js/casherp-workspaces-react.js', 'public/js/smtp-settings-react.js'] as $asset) {
            $path = base_path($asset);
            $this->add(
                is_file($path) && filesize($path) > 0,
                'Built React asset: '.basename($asset),
                is_file($path) && filesize($path) > 0 ? 'Compiled asset is present.' : 'Run the production frontend build before launch.'
            );
        }

        $queue = (string) config('queue.default');
        $this->add(
            $queue !== 'sync',
            'Background queue',
            $queue === 'sync' ? 'QUEUE_CONNECTION=sync is functional but not recommended for production mail, imports and notifications.' : "Queue connection is {$queue}.",
            'warn'
        );

        $mailAddress = (string) config('mail.from.address');
        $mailHost = (string) config('mail.mailers.smtp.host');
        $mailReady = filter_var($mailAddress, FILTER_VALIDATE_EMAIL)
            && ! str_ends_with(strtolower($mailAddress), '@example.com')
            && $mailHost !== '';
        $this->add(
            $mailReady,
            'System mail baseline',
            $mailReady ? 'System SMTP sender and host are configured.' : 'Configure a real MAIL_FROM_ADDRESS and SMTP host; tenant SMTP cannot replace system recovery mail.',
            'warn'
        );

        if ((bool) config('dpo.enabled')) {
            $dpoReady = in_array(config('dpo.mode'), ['test', 'live'], true)
                && ! empty(config('dpo.company_token'))
                && ctype_digit((string) config('dpo.service_type'))
                && str_starts_with((string) config('dpo.api_endpoint'), 'https://')
                && str_starts_with((string) config('dpo.payment_url'), 'https://');
            $this->add(
                $dpoReady,
                'DPO Pay configuration',
                $dpoReady ? 'DPO Pay configuration is structurally complete without exposing credentials.' : 'DPO Pay is enabled but its protected configuration is incomplete.'
            );
            $this->add(
                config('dpo.mode') === 'live',
                'DPO Pay operating mode',
                config('dpo.mode') === 'live' ? 'DPO Pay is configured for live processing.' : 'DPO Pay remains in test mode; obtain approval before switching to live credentials.',
                'warn'
            );
        } else {
            $this->add(true, 'DPO Pay configuration', 'DPO Pay is disabled; no DPO checkout will be offered.', 'warn');
        }

        $this->add(
            false,
            'External scheduler and workers',
            'Confirm the one-minute Laravel scheduler and persistent queue workers in cPanel; source code cannot verify external processes.',
            'warn'
        );
    }

    private function add(bool $condition, string $check, string $message, string $failureStatus = 'fail'): void
    {
        $this->results[] = [
            'status' => $condition ? 'pass' : $failureStatus,
            'check' => $check,
            'message' => $message,
        ];
    }

    private function count(string $status): int
    {
        return count(array_filter($this->results, fn ($result) => $result['status'] === $status));
    }
}
