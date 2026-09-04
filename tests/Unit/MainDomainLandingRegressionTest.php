<?php

namespace Tests\Unit;

use App\Http\Middleware\EnforceCanonicalDomain;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class MainDomainLandingRegressionTest extends TestCase
{
    public function test_landing_page_uses_same_origin_application_links(): void
    {
        $view = file_get_contents($this->projectPath('resources/views/welcome.blade.php'));
        $header = file_get_contents($this->projectPath('resources/views/landing/partials/header.blade.php'));
        $shell = $view . $header;

        $this->assertStringContainsString("url('/login')", $shell);
        $this->assertStringContainsString("url('/business/register')", $view);
        $this->assertStringContainsString("url('/home')", $shell);
        $this->assertStringContainsString("url('/pricing')", $shell);
        $this->assertStringContainsString("url('/get-started')", $header);
        $this->assertStringNotContainsString('https://app.casherp.com', $shell);
    }

    public function test_landing_page_represents_the_six_approved_industries(): void
    {
        $view = file_get_contents($this->projectPath('resources/views/welcome.blade.php'));

        foreach ([
            'General Business &amp; Trading',
            'Restaurant, Cafe &amp; Fast Food',
            'Hotel, Lodge &amp; Guest House',
            'Hotel / Lodge with Restaurant',
            'Property Management &amp; Rentals',
            'Professional Services',
        ] as $industry) {
            $this->assertStringContainsString($industry, $view);
        }

        $this->assertStringContainsString('HMS and HRM remain separate modules', $view);
        $this->assertSame(6, substr_count($view, 'class="industry-card reveal"'));
    }

    public function test_landing_page_contains_no_unverified_statistics_or_legacy_target(): void
    {
        $view = file_get_contents($this->projectPath('resources/views/welcome.blade.php'));

        foreach (['41,000', '41000', '324+', '$320M+', 'Trusted by Over'] as $claim) {
            $this->assertStringNotContainsString($claim, $view);
        }
    }

    public function test_canonical_domain_guard_is_registered_and_rejects_unknown_hosts(): void
    {
        $kernel = file_get_contents($this->projectPath('app/Http/Kernel.php'));
        $middleware = file_get_contents($this->projectPath('app/Http/Middleware/EnforceCanonicalDomain.php'));
        $config = file_get_contents($this->projectPath('config/canonical.php'));
        $provider = file_get_contents($this->projectPath('app/Providers/AppServiceProvider.php'));
        $cors = file_get_contents($this->projectPath('config/cors.php'));
        $environment = file_get_contents($this->projectPath('.env.domain.example'));
        $publicHtaccess = file_get_contents($this->projectPath('deployment/main-domain/public_html.htaccess'));
        $legacyHtaccess = file_get_contents($this->projectPath('deployment/main-domain/app_legacy_redirect.htaccess'));
        $environmentUpdater = file_get_contents($this->projectPath('deployment/main-domain/set-domain-env.php'));
        $deploymentScript = file_get_contents($this->projectPath('deployment/main-domain/deploy.sh'));

        $this->assertStringContainsString('EnforceCanonicalDomain::class', $kernel);
        $this->assertStringContainsString("env('CANONICAL_URL')", $config);
        $this->assertStringContainsString('! in_array($currentHost, $knownHosts, true)', $middleware);
        $this->assertStringContainsString("new Response('Misdirected Request', 421", $middleware);
        $this->assertStringContainsString("\$request->isMethod('GET')", $middleware);
        $this->assertStringContainsString("'Vary' => 'Host'", $middleware);
        $this->assertStringContainsString('forceRootUrl($canonicalUrl)', $provider);
        $this->assertStringNotContainsString("'allowed_origins' => ['*']", $cors);
        $this->assertStringContainsString('APP_URL=https://www.casherp.com', $environment);
        $this->assertStringContainsString('ASSET_URL=https://www.casherp.com', $environment);
        $this->assertStringContainsString('CANONICAL_URL=https://www.casherp.com', $environment);
        $this->assertStringContainsString('CORS_ALLOWED_ORIGINS=https://www.casherp.com', $environment);
        $this->assertStringContainsString('SESSION_DOMAIN=www.casherp.com', $environment);
        $this->assertStringContainsString('CANONICAL_LEGACY_HOSTS=casherp.com', $environment);
        $this->assertStringNotContainsString('CANONICAL_LEGACY_HOSTS=casherp.com,app.casherp.com', $environment);
        $this->assertStringContainsString("'CANONICAL_LEGACY_HOSTS' => 'casherp.com'", $environmentUpdater);
        $this->assertStringContainsString('!^www\\.casherp\\.com$', $publicHtaccess);
        $this->assertStringContainsString('[R=421,L]', $publicHtaccess);
        $this->assertStringContainsString('!^(GET|HEAD)$', $legacyHtaccess);
        $this->assertStringContainsString('[R=308,L,NE]', $legacyHtaccess);
        $this->assertStringContainsString('php artisan route:cache', $deploymentScript);
    }

    public function test_canonical_domain_runtime_behavior_has_one_application_origin(): void
    {
        $app = new Container();
        Container::setInstance($app);
        $app->instance('config', new Repository([
            'canonical' => [
                'url' => 'https://www.casherp.com',
                'legacy_hosts' => ['casherp.com'],
            ],
        ]));
        $middleware = new EnforceCanonicalDomain();
        $next = static fn () => new Response('CashERP application', 200);

        try {
            $canonical = $middleware->handle(Request::create('https://www.casherp.com/home?company=1'), $next);
            $this->assertSame(200, $canonical->getStatusCode());
            $this->assertSame('CashERP application', $canonical->getContent());

            $apex = $middleware->handle(Request::create('https://casherp.com/pricing?period=year'), $next);
            $this->assertSame(301, $apex->getStatusCode());
            $this->assertSame('https://www.casherp.com/pricing?period=year', $apex->headers->get('Location'));

            $legacyPost = $middleware->handle(Request::create('https://casherp.com/subscription/1/confirm', 'POST'), $next);
            $this->assertSame(308, $legacyPost->getStatusCode());
            $this->assertSame('https://www.casherp.com/subscription/1/confirm', $legacyPost->headers->get('Location'));

            foreach (['app.casherp.com', 'anything.casherp.com'] as $unknownHost) {
                $unknown = $middleware->handle(Request::create('https://'.$unknownHost.'/home'), $next);
                $this->assertSame(421, $unknown->getStatusCode());
                $this->assertSame('Misdirected Request', $unknown->getContent());
                $this->assertStringContainsString('no-store', (string) $unknown->headers->get('Cache-Control'));
            }
        } finally {
            Container::setInstance(null);
        }
    }

    private function projectPath(string $path): string
    {
        return dirname(__DIR__, 2).'/'.ltrim($path, '/');
    }
}
