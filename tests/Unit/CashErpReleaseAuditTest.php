<?php

namespace Tests\Unit;

use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

class CashErpReleaseAuditTest extends TestCase
{
    public function test_release_audit_is_read_only_secret_safe_and_covers_critical_surfaces(): void
    {
        $source = file_get_contents($this->path('app/Console/Commands/CashErpReleaseAudit.php'));
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);

        $this->assertNotEmpty($parser->parse($source));
        $this->assertStringContainsString('casherp:release-audit', $source);
        $this->assertStringContainsString('read-only', $source);
        $this->assertStringContainsString('without exposing', $source);
        $this->assertStringNotContainsString("'company_token' => config('dpo.company_token')", $source);
        $this->assertStringNotContainsString("line((string) config('dpo.company_token'))", $source);
        $this->assertStringContainsString("'business' => ['industry_id', 'onboarding_settings', 'onboarding_completed_at']", $source);
        $this->assertStringContainsString("'procurement_documents'", $source);
        $this->assertStringContainsString("'restaurant_order_fulfilments'", $source);
        $this->assertStringNotContainsString("'businesses' =>", $source);
        $this->assertStringNotContainsString("'restaurant_fulfilments'", $source);
        $this->assertStringNotContainsString('config(\'mail.mailers.smtp.password\')', $source);
        foreach ([
            'Canonical application origin',
            'Required database schema',
            'First six industries',
            'HRM industry availability',
            'Industry document library',
            'Built React asset',
            'DPO Pay configuration',
            'External scheduler and workers',
        ] as $check) {
            $this->assertStringContainsString($check, $source);
        }
    }

    public function test_event_deposit_upgrade_preserves_entries_and_repairs_context(): void
    {
        $migration = file_get_contents($this->path('Modules/Hms/database/migrations/2026_09_04_000008_unify_event_document_security_deposits.php'));

        $this->assertStringContainsString("where('source_type', 'hms_event_booking')", $migration);
        $this->assertStringContainsString("'context_type' => 'hms_event'", $migration);
        $this->assertStringContainsString("->update(['security_deposit_id' => \$target->id", $migration);
        $this->assertStringContainsString('max((float) $target->required_amount, (float) $source->required_amount)', $migration);
        $this->assertStringContainsString('rollback is a', $migration);
        $this->assertStringNotContainsString("DB::table('security_deposit_entries')->where('security_deposit_id', \$source->id)->delete", $migration);
    }

    public function test_standard_production_environment_keeps_core_schedules_enabled(): void
    {
        $kernel = file_get_contents($this->path('app/Console/Kernel.php'));

        $this->assertStringContainsString("in_array(\$env, ['live', 'production'], true)", $kernel);
        $this->assertStringContainsString("casherp:finalize-company-closures", $kernel);
        $this->assertStringContainsString("casherp:purge-expired-import-data", $kernel);
        $this->assertStringContainsString("casherp:send-crm-activity-reminders", $kernel);
    }

    private function path(string $relative): string
    {
        return dirname(__DIR__, 2).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }
}
