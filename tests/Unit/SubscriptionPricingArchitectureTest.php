<?php

namespace Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use Illuminate\View\Compilers\BladeCompiler;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use App\Services\SubscriptionPricingService;
use App\Services\DpoPaymentService;
use App\Business;
use App\User;
use Modules\Superadmin\Entities\Package;
use Modules\Superadmin\Entities\SubscriptionPaymentAttempt;

class SubscriptionPricingArchitectureTest extends TestCase
{
    public function test_base_quote_and_payment_tolerance_execute_without_browser_values(): void
    {
        $service = new SubscriptionPricingService();
        $package = new Package();
        $package->setRawAttributes(['price' => 129.995]);
        $quote = $service->quote($package, 1);

        $this->assertSame(129.995, $quote['base_amount']);
        $this->assertSame(129.995, $quote['amount']);
        $this->assertNull($quote['coupon_code']);
        $service->assertExpectedAmount('129.995', $quote['amount']);
        $this->addToAssertionCount(1);
    }

    public function test_public_targeted_and_private_package_boundaries_are_explicit(): void
    {
        $package = $this->read('Modules/Superadmin/Entities/Package.php');
        $pricing = $this->read('app/Services/SubscriptionPricingService.php');
        $registration = $this->read('app/Http/Requests/RegisterBusinessRequest.php');

        $this->assertStringContainsString('scopePubliclyAvailable', $package);
        $this->assertStringContainsString("->orWhere('businesses', '[]')", $package);
        $this->assertStringContainsString('array_intersect($accountBusinessIds, $targetBusinesses)', $pricing);
        $this->assertStringContainsString('if ($package->is_private)', $pricing);
        $this->assertStringContainsString('publiclyAvailable()', $registration);
    }

    public function test_checkout_price_and_identity_are_server_authoritative(): void
    {
        $controller = $this->read('Modules/Superadmin/Http/Controllers/SubscriptionController.php');
        $service = $this->read('app/Services/SubscriptionPricingService.php');
        $myFatoorah = $this->read('app/Http/Controllers/MyFatoorahController.php');

        $this->assertStringContainsString('$this->pricing->quote', $controller);
        $this->assertStringNotContainsString('$request->price', $controller);
        $this->assertStringNotContainsString("input('price')", $controller);
        $this->assertStringContainsString('assertExpectedAmount', $service);
        $this->assertStringContainsString('assertExpectedCurrency', $service);
        $this->assertStringContainsString("session('user.business_id')", $myFatoorah);
        $this->assertStringNotContainsString("request('amount')", $myFatoorah);
        $this->assertStringNotContainsString("request('business_id')", $myFatoorah);
    }

    public function test_callbacks_are_company_bound_and_payment_replays_are_idempotent(): void
    {
        $controller = $this->read('Modules/Superadmin/Http/Controllers/SubscriptionController.php');
        $base = $this->read('Modules/Superadmin/Http/Controllers/BaseController.php');
        $migration = $this->read('database/migrations/2026_09_03_000021_add_payment_dedupe_key_to_subscriptions.php');

        foreach (['paypal_checkout', 'subscription_checkout', 'myfatoorah_checkout', 'different company session'] as $guard) {
            $this->assertStringContainsString($guard, $controller);
        }
        $this->assertStringContainsString('paymentDedupeKey', $base);
        $this->assertStringContainsString('lockForUpdate()', $base);
        $this->assertStringContainsString("->unique()", $migration);
        $this->assertStringContainsString("'currency_code'", $base);
    }

    public function test_state_changing_subscription_routes_are_post_only(): void
    {
        $routes = $this->read('Modules/Superadmin/Routes/web.php');
        $view = $this->read('Modules/Superadmin/Resources/views/subscription/index.blade.php');

        $this->assertStringContainsString("Route::post('/subscription/{package_id}/confirm'", $routes);
        $this->assertStringContainsString("Route::post('/subscription/{subcription_id}/force-active'", $routes);
        $this->assertStringNotContainsString("Route::any('/subscription", $routes);
        $this->assertStringContainsString('method: "POST"', $view);
    }

    public function test_saas_limits_are_account_aware_and_do_not_reset_product_capacity(): void
    {
        $service = $this->read('app/Services/SubscriptionPricingService.php');
        $moduleUtil = $this->read('app/Utils/ModuleUtil.php');
        $subscription = $this->read('Modules/Superadmin/Entities/Subscription.php');

        $this->assertStringContainsString("Business::where('owner_id'", $service);
        $this->assertStringContainsString('max_businesses', $service);
        $this->assertStringContainsString('User::forBusiness($business_id)', $moduleUtil);
        $productMethod = substr(
            $moduleUtil,
            strpos($moduleUtil, 'public function countProducts'),
            strpos($moduleUtil, 'public function checkPackageLimitsBeforeSubscription') - strpos($moduleUtil, 'public function countProducts')
        );
        $this->assertStringNotContainsString("whereBetween('created_at'", $productMethod);
        $this->assertStringContainsString('$requestedProducts = max(1, (int) $total_rows);', $moduleUtil);
        $this->assertStringContainsString("whereNull('covered_by_subscription_id')", $subscription);
    }

    public function test_coupon_validation_and_date_calculation_are_bounded(): void
    {
        $request = $this->read('app/Http/Requests/SaveSubscriptionCouponRequest.php');
        $base = $this->read('Modules/Superadmin/Http/Controllers/BaseController.php');
        $migration = $this->read('database/migrations/2026_09_03_000022_make_coupon_codes_unique.php');

        $this->assertStringContainsString("Rule::unique('superadmin_coupons'", $request);
        $this->assertStringContainsString('percentage discount cannot exceed 100%', $request);
        $this->assertStringContainsString('superadmin_coupons_coupon_code_unique', $migration);
        $this->assertStringContainsString('Duplicate coupon codes must be resolved', $migration);
        $this->assertStringContainsString('addMonthsNoOverflow', $base);
        $this->assertStringContainsString('$start_date->copy()->addDays($package->trial_days)', $base);
        $this->assertStringContainsString('$trialEnd->gt($planEnd)', $base);
    }

    public function test_expiry_alerts_are_account_aware_portable_and_not_duplicated_by_coverage_rows(): void
    {
        $command = $this->read('Modules/Superadmin/Console/SubscriptionExpiryAlert.php');
        $notification = $this->read('Modules/Superadmin/Notifications/SendSubscriptionExpiryAlert.php');

        $this->assertStringNotContainsString('DATEDIFF(', $command);
        $this->assertStringContainsString("whereNull('covered_by_subscription_id')", $command);
        $this->assertStringContainsString('$owner->ownedBusinesses()', $command);
        $this->assertStringContainsString('$notifiedOwners', $command);
        $this->assertStringContainsString("'msg' => strip_tags", $notification);
        $this->assertStringNotContainsString('diffInDays($this->subscription->end_date) + 1', $notification);
    }

    public function test_dpo_option_a_uses_durable_server_verified_checkout(): void
    {
        $service = $this->read('app/Services/DpoPaymentService.php');
        $controller = $this->read('Modules/Superadmin/Http/Controllers/SubscriptionController.php');
        $settings = $this->read('Modules/Superadmin/Http/Controllers/SuperadminSettingsController.php');
        $settingsView = $this->read('Modules/Superadmin/Resources/views/superadmin_settings/partials/payment_gateways.blade.php');
        $routes = $this->read('Modules/Superadmin/Routes/web.php');
        $migration = $this->read('database/migrations/2026_09_03_000023_create_subscription_payment_attempts_table.php');

        $this->assertStringContainsString("newDocument('createToken')", $service);
        $this->assertStringContainsString("newDocument('verifyToken')", $service);
        $this->assertStringContainsString("'application/xml; charset=utf-8'", $service);
        $this->assertStringContainsString('LIBXML_NONET', $service);
        $this->assertStringContainsString("str_starts_with(\$endpoint, 'https://')", $service);
        $this->assertStringContainsString("\$resultCode !== '000'", $controller);
        $this->assertStringContainsString('assertExpectedAmount', $controller);
        $this->assertStringContainsString('assertExpectedCurrency', $controller);
        $this->assertStringContainsString('SubscriptionPaymentAttempt::', $controller);
        $this->assertStringContainsString('hash_hmac', $controller);
        $this->assertStringContainsString("verifyToken(\$returnedToken, false)", $controller);
        $this->assertStringContainsString("verifyToken(\$returnedToken, true)", $controller);
        $this->assertStringContainsString("Route::post('/subscription/{package_id}/dpo/create'", $routes);
        $this->assertStringContainsString("Route::post('/subscription/dpo/attempt/{attempt_id}/verify'", $routes);
        $this->assertStringContainsString("'/subscription/dpo/callback'", $routes);
        $this->assertStringContainsString("\$table->string('gateway_token', 191)->nullable()->unique()", $migration);
        $this->assertStringContainsString("'DPO_COMPANY_TOKEN' => null", $settings);
        $this->assertStringContainsString('autocomplete="new-password"', $settingsView);
        $this->assertStringNotContainsString("\$default_values['DPO_COMPANY_TOKEN'], ['class'", $settingsView);
    }

    public function test_dpo_xml_is_generated_server_side_and_special_characters_are_escaped(): void
    {
        $app = new Container();
        Container::setInstance($app);
        $app->instance('config', new Repository([
            'app' => ['key' => 'base64:test-application-key'],
            'dpo' => [
                'enabled' => true,
                'mode' => 'test',
                'company_token' => '00000000-0000-4000-8000-000000000000',
                'service_type' => '85325',
                'service_description' => 'CashERP SaaS subscription',
                'api_endpoint' => 'https://secure.3gdirectpay.com/API/v6/',
                'payment_url' => 'https://secure.3gdirectpay.com/payv3.php',
                'payment_time_limit_minutes' => 30,
                'http_timeout_seconds' => 20,
            ],
        ]));
        $app->instance(Factory::class, new Factory());
        Facade::setFacadeApplication($app);
        Http::fake([
            'secure.3gdirectpay.com/*' => Http::response(
                '<API3G><Result>000</Result><ResultExplanation>Transaction created</ResultExplanation><TransToken>11111111-1111-4111-8111-111111111111</TransToken><TransRef>DPO-1</TransRef></API3G>',
                200,
                ['Content-Type' => 'application/xml']
            ),
        ]);

        $attempt = new SubscriptionPaymentAttempt();
        $attempt->setRawAttributes([
            'reference' => 'CSR-TEST1234567890',
            'amount' => '125.50',
            'currency_code' => 'USD',
        ]);
        $business = new Business();
        $business->setRawAttributes(['name' => 'Cash & ERP']);
        $user = new User();
        $user->setRawAttributes([
            'first_name' => 'Pat & Co',
            'last_name' => '<Owner>',
            'email' => 'owner@example.test',
        ]);

        try {
            $service = new DpoPaymentService();
            $response = $service->createToken(
                $attempt,
                $business,
                $user,
                'https://www.casherp.com/subscription/dpo/callback?reference=CSR-TEST1234567890',
                'https://www.casherp.com/subscription/1/pay'
            );

            $this->assertSame('000', $response['Result']);
            $this->assertSame(
                'https://secure.3gdirectpay.com/payv3.php?ID=11111111-1111-4111-8111-111111111111',
                $service->paymentUrl($response['TransToken'])
            );
            Http::assertSent(function ($request) {
                $body = $request->body();

                return str_contains($body, '<Request>createToken</Request>')
                    && str_contains($body, '<PaymentAmount>125.50</PaymentAmount>')
                    && str_contains($body, '<PaymentCurrency>USD</PaymentCurrency>')
                    && str_contains($body, '<CompanyRef>CSR-TEST1234567890</CompanyRef>')
                    && str_contains($body, '<ServiceType>85325</ServiceType>')
                    && str_contains($body, '<customerFirstName>Pat &amp; Co</customerFirstName>')
                    && str_contains($body, '<customerLastName>&lt;Owner&gt;</customerLastName>');
            });
        } finally {
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication(null);
            Container::setInstance(null);
        }
    }

    public function test_modified_pricing_blade_templates_compile(): void
    {
        $compiler = new BladeCompiler(new Filesystem(), sys_get_temp_dir());
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $views = [
            'Modules/Superadmin/Resources/views/pricing/index.blade.php',
            'Modules/Superadmin/Resources/views/packages/index.blade.php',
            'Modules/Superadmin/Resources/views/packages/edit.blade.php',
            'Modules/Superadmin/Resources/views/coupons/index.blade.php',
            'Modules/Superadmin/Resources/views/coupons/edit.blade.php',
            'Modules/Superadmin/Resources/views/subscription/index.blade.php',
            'Modules/Superadmin/Resources/views/subscription/pay.blade.php',
            'Modules/Superadmin/Resources/views/subscription/partials/packages.blade.php',
            'Modules/Superadmin/Resources/views/subscription/partials/package_card.blade.php',
            'Modules/Superadmin/Resources/views/subscription/partials/pay_offline.blade.php',
            'Modules/Superadmin/Resources/views/subscription/partials/pay_paypal.blade.php',
            'Modules/Superadmin/Resources/views/subscription/partials/pay_paystack.blade.php',
            'Modules/Superadmin/Resources/views/subscription/partials/pay_flutterwave.blade.php',
            'Modules/Superadmin/Resources/views/subscription/partials/pay_myfatoorah.blade.php',
            'Modules/Superadmin/Resources/views/subscription/partials/pay_pesapal.blade.php',
            'Modules/Superadmin/Resources/views/subscription/partials/pay_razorpay.blade.php',
            'Modules/Superadmin/Resources/views/subscription/partials/pay_stripe.blade.php',
            'Modules/Superadmin/Resources/views/subscription/partials/pay_dpo.blade.php',
        ];

        foreach ($views as $view) {
            $this->assertNotEmpty($parser->parse($compiler->compileString($this->read($view))), $view);
        }
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
