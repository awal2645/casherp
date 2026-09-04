<?php

namespace Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

class SmtpArchitectureTest extends TestCase
{
    public function test_company_smtp_api_is_authenticated_scoped_and_throttled(): void
    {
        $routes = $this->read('routes/web.php');
        $controller = $this->read('app/Http/Controllers/BusinessSmtpSettingsController.php');
        $request = $this->read('app/Http/Requests/BusinessSmtpSettingsRequest.php');

        $this->assertStringContainsString("'/business/smtp-settings'", $routes);
        $this->assertStringContainsString("'/business/smtp-settings/test'", $routes);
        $this->assertStringContainsString("middleware('throttle:5,10')", $routes);
        $this->assertStringContainsString('accessibleBusinesses()', $controller);
        $this->assertStringNotContainsString("input('business_id')", $controller);
        $this->assertStringContainsString("can('business_settings.access')", $request);
    }

    public function test_password_is_encrypted_preserved_and_never_returned_to_react(): void
    {
        $service = $this->read('app/Services/BusinessMailConfigurationService.php');
        $view = $this->read('resources/views/business/partials/settings_email.blade.php');
        $react = $this->read('resources/js/smtp-settings-react.jsx');

        $this->assertStringContainsString('Crypt::encryptString', $service);
        $this->assertStringContainsString('Crypt::decryptString', $service);
        $this->assertStringContainsString("unset(\$settings['password'], \$settings['mail_password'])", $service);
        $this->assertStringContainsString("'password_configured'", $service);
        $this->assertStringNotContainsString('email_settings[mail_password]', $view);
        $this->assertStringContainsString("placeholder={settings.password_configured", $react);
    }

    public function test_laravel_nine_mailer_keys_and_worker_tenant_isolation_are_used(): void
    {
        $service = $this->read('app/Services/BusinessMailConfigurationService.php');
        $notificationUtil = $this->read('app/Utils/NotificationUtil.php');

        $this->assertStringContainsString("'mail.mailers.smtp'", $service);
        $this->assertStringContainsString("purge('smtp')", $service);
        $this->assertStringContainsString("config('mail.system_smtp'", $service);
        $this->assertStringContainsString('BusinessMailConfigurationService::class', $notificationUtil);
        $this->assertStringNotContainsString("Config::set('mail.driver'", $notificationUtil);
    }

    public function test_diagnostics_are_safe_audited_and_do_not_leak_raw_errors(): void
    {
        $controller = $this->read('app/Http/Controllers/BusinessSmtpSettingsController.php');
        $service = $this->read('app/Services/BusinessMailConfigurationService.php');
        $migration = $this->read('database/migrations/2026_09_03_000019_create_business_email_configuration_events.php');

        $this->assertStringContainsString('classifyFailure', $controller);
        $this->assertStringContainsString('safeFailureMessage', $controller);
        $this->assertStringNotContainsString("'message' => \$exception->getMessage()", $controller);
        $this->assertStringContainsString('correlation_id', $migration);
        $this->assertStringContainsString("hash_hmac('sha256'", $service);
        $this->assertStringContainsString('SmtpConfigurationNotification', $controller);
    }

    public function test_private_smtp_targets_are_rejected_by_default(): void
    {
        $rule = $this->read('app/Rules/PublicSmtpHost.php');
        $config = $this->read('config/casherp_mail.php');

        foreach (['FILTER_FLAG_NO_PRIV_RANGE', 'FILTER_FLAG_NO_RES_RANGE', "'.local'", "'.internal'", "'localhost'"] as $guard) {
            $this->assertStringContainsString($guard, $rule);
        }
        $this->assertStringContainsString("MAIL_ALLOW_PRIVATE_SMTP_HOSTS', false", $config);
    }

    public function test_react_workspace_and_blade_shell_are_wired_and_parseable(): void
    {
        $settings = $this->read('resources/views/business/settings.blade.php');
        $partial = $this->read('resources/views/business/partials/settings_email.blade.php');
        $package = json_decode($this->read('package.json'), true, flags: JSON_THROW_ON_ERROR);

        $this->assertStringContainsString('smtp-settings-react.js', $settings);
        $this->assertStringContainsString('smtp-settings.css', $settings);
        $this->assertStringContainsString('casherp-smtp-settings-root', $partial);
        $this->assertSame('18.3.1', $package['dependencies']['react']);
        $this->assertFileExists($this->path('public/js/smtp-settings-react.js'));

        $compiler = new BladeCompiler(new Filesystem(), sys_get_temp_dir());
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        foreach (['resources/views/business/settings.blade.php', 'resources/views/business/partials/settings_email.blade.php'] as $view) {
            $this->assertNotEmpty($parser->parse($compiler->compileString($this->read($view))));
        }
    }

    public function test_hospitality_and_crm_messages_resolve_company_mail_at_delivery_time(): void
    {
        $hmsNotification = $this->read('Modules/Hms/Notifications/CustomerNotification.php');
        $guestService = $this->read('Modules/Hms/Services/GuestMessagingService.php');
        $crmNotification = $this->read('Modules/Crm/Notifications/SendProposalNotification.php');
        $customerNotification = $this->read('app/Notifications/CustomerNotification.php');

        $this->assertStringContainsString("'business_id' => \$rule->business_id", $guestService);
        $this->assertStringContainsString('Business::find($settings', $hmsNotification);
        $this->assertStringContainsString('Business::find($this->proposal->business_id)', $crmNotification);
        $this->assertStringContainsString('configureEmail($this->notificationInfo)', $customerNotification);
    }

    private function read(string $relative): string
    {
        return file_get_contents($this->path($relative));
    }

    private function path(string $relative): string
    {
        return dirname(__DIR__, 2).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }
}
