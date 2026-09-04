<?php

namespace Modules\Superadmin\Http\Controllers;

use App\Business;
use App\System;
use App\Services\DpoPaymentService;
use App\Services\SubscriptionPricingService;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Superadmin\Entities\Package;
use Modules\Superadmin\Entities\Subscription;
use Modules\Superadmin\Entities\SubscriptionPaymentAttempt;
use Modules\Superadmin\Entities\SuperadminCoupon;
use Modules\Superadmin\Notifications\SubscriptionOfflinePaymentActivationConfirmation;
use Notification;
use Paystack;
use Pesapal;
use Razorpay\Api\Api;
use Srmklive\PayPal\Services\ExpressCheckout;
use Stripe\Charge;
use Stripe\Customer;
use Stripe\Stripe;
use Yajra\DataTables\Facades\DataTables;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use MyFatoorah\Library\API\Payment\MyFatoorahPaymentStatus;

class SubscriptionController extends BaseController
{
    protected $provider;

    protected SubscriptionPricingService $pricing;

    protected DpoPaymentService $dpo;

    public function __construct(ModuleUtil $moduleUtil, SubscriptionPricingService $pricing, DpoPaymentService $dpo)
    {
        if (! defined('CURL_SSLVERSION_TLSv1_2')) {
            define('CURL_SSLVERSION_TLSv1_2', 6);
        }

        if (! defined('CURLOPT_SSLVERSION')) {
            define('CURLOPT_SSLVERSION', 6);
        }

        $this->mfConfig = [
            'apiKey'      => config('myfatoorah.api_key'),
            'isTest'      => config('myfatoorah.test_mode'),
            'countryCode' => config('myfatoorah.country_iso'),
        ];

        $this->moduleUtil = $moduleUtil;
        $this->pricing = $pricing;
        $this->dpo = $dpo;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        if (! auth()->user()->can('superadmin.access_package_subscriptions')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        //Get active subscription and upcoming subscriptions.
        $active = Subscription::active_subscription($business_id);

        $nexts = Subscription::upcoming_subscriptions($business_id);
        $waiting = Subscription::waiting_approval($business_id);
        $paymentAttempts = SubscriptionPaymentAttempt::whereIn('business_id', $this->accountBusinessIds((int) $business_id))
            ->where('gateway', 'dpo')
            ->latest('id')
            ->limit(10)
            ->get();

        $packages = $this->pricing->availablePackages(
            (int) $business_id,
            auth()->user()->can('superadmin')
        );

        //Get all module permissions and convert them into name => label
        $permissions = $this->moduleUtil->getModuleData('superadmin_package');
        $permission_formatted = [];
        foreach ($permissions as $permission) {
            foreach ($permission as $details) {
                $permission_formatted[$details['name']] = $details['label'];
            }
        }

        $intervals = ['days' => __('lang_v1.days'), 'months' => __('lang_v1.months'), 'years' => __('lang_v1.years')];

        return view('superadmin::subscription.index')
            ->with(compact('packages', 'active', 'nexts', 'waiting', 'paymentAttempts', 'permission_formatted', 'intervals'));
    }

    /**
     * Show pay form for a new package.
     *
     * @return Response
     */
    public function pay(Request $request, $package_id, $form_register = null)
    {
        if (! auth()->user()->can('superadmin.access_package_subscriptions')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = (int) $request->session()->get('user.business_id');
            $user_id = (int) $request->session()->get('user.id');
            $package = $this->pricing->packageForBusiness(
                (int) $package_id,
                $business_id,
                auth()->user()->can('superadmin')
            );
            $this->pricing->assertCanSubscribe($package, $business_id);

            $couponCode = $request->filled('code') ? (string) $request->input('code') : null;
            $quote = $this->pricing->quote($package, $business_id, $couponCode);
            $this->pricing->assertZeroCostCheckoutAvailable($package, $business_id, $quote['amount']);
            $coupon_status = ['status' => '', 'msg' => ''];
            if ($couponCode) {
                $coupon_status = $quote['coupon']
                    ? ['status' => 'success', 'msg' => __('lang_v1.success')]
                    : ['status' => 'danger', 'msg' => __('superadmin::lang.invalid_coupon')];
            }

            if ($quote['amount'] <= 0) {
                DB::transaction(function () use ($quote, $business_id, $package, $user_id) {
                    $this->_add_subscription(
                        $quote['coupon_code'],
                        0,
                        $business_id,
                        $package,
                        null,
                        'FREE-'.strtoupper(Str::random(20)),
                        $user_id
                    );
                });

                return redirect()
                    ->action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'index'])
                    ->with('status', ['success' => 1, 'msg' => empty($form_register)
                        ? __('lang_v1.success')
                        : __('superadmin::lang.registered_and_subscribed')]);
            }

            $gateways = $this->_payment_gateways();
            $system_currency = System::getCurrency();
            $layout = empty($form_register) ? 'layouts.app' : 'layouts.auth';
            $user = $request->session()->get('user');
            $offline_payment_details = System::getProperty('offline_payment_details');
            $package_price_after_discount = $quote['amount'];
            $discount_amount = $quote['discount_amount'];
            $checkoutAmount = $quote['amount'];
            $checkoutCouponCode = $quote['coupon_code'];
            $checkoutCurrency = $this->gatewayCurrencyCode();
            $request->session()->put('subscription_checkout', [
                'package_id' => $package->id,
                'business_id' => $business_id,
                'user_id' => $user_id,
                'coupon_code' => $checkoutCouponCode,
                'amount' => $checkoutAmount,
                'currency' => $checkoutCurrency,
            ]);

            return view('superadmin::subscription.pay')
                ->with(compact('package', 'gateways', 'system_currency', 'layout', 'user', 'offline_payment_details', 'coupon_status', 'package_price_after_discount', 'discount_amount', 'checkoutAmount', 'checkoutCouponCode', 'checkoutCurrency'));
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $message = $e instanceof ValidationException
                ? collect($e->errors())->flatten()->first()
                : __('messages.something_went_wrong');
            $output = ['success' => 0, 'msg' => $message];

            return redirect()
                ->action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'index'])
                ->with('status', $output);
        }
    }

    /**
     * Show pay form for a new package.
     *
     * @return Response
     */
    public function registerPay($package_id, Request $request)
    {
        return $this->pay($request, $package_id, 1);
    }

    /**
     * Create a durable DPO Pay Option A checkout and redirect to its hosted
     * payment page. Price, currency, account and package are server-owned.
     */
    public function createDpoCheckout(Request $request, $package_id)
    {
        if (! auth()->user()->can('superadmin.access_package_subscriptions')) {
            abort(403, 'Unauthorized action.');
        }

        $ownsAttemptCreation = false;
        try {
            if (config('app.env') === 'demo') {
                throw ValidationException::withMessages(['payment' => 'Feature disabled in demo.']);
            }

            $request->validate(['coupon_code' => ['nullable', 'string', 'max:100']]);
            $businessId = (int) $request->session()->get('user.business_id');
            $userId = (int) $request->session()->get('user.id');
            $package = $this->pricing->packageForBusiness(
                (int) $package_id,
                $businessId,
                auth()->user()->can('superadmin')
            );
            $this->pricing->assertCanSubscribe($package, $businessId);
            $quote = $this->pricing->quote(
                $package,
                $businessId,
                $request->filled('coupon_code') ? (string) $request->input('coupon_code') : null,
                true
            );
            if ($quote['amount'] <= 0) {
                throw ValidationException::withMessages(['payment' => 'This checkout does not require DPO Pay.']);
            }
            if (! $this->dpo->configured()) {
                throw ValidationException::withMessages(['gateway' => 'DPO Pay is not fully configured.']);
            }

            $currency = $this->gatewayCurrencyCode();
            $attempt = DB::transaction(function () use ($businessId, $userId, $package, $quote, $currency) {
                Business::whereKey($businessId)->lockForUpdate()->firstOrFail();
                $existing = SubscriptionPaymentAttempt::where('gateway', 'dpo')
                    ->where('business_id', $businessId)
                    ->where('package_id', $package->id)
                    ->where('user_id', $userId)
                    ->where('amount', $quote['amount'])
                    ->where('currency_code', $currency)
                    ->where('coupon_code', $quote['coupon_code'])
                    ->whereIn('status', ['initiated', 'creating', 'pending'])
                    ->where('expires_at', '>', now())
                    ->latest('id')
                    ->first();

                if ($existing) {
                    return $existing;
                }

                return SubscriptionPaymentAttempt::create([
                    'reference' => 'CSR-'.strtoupper(Str::random(20)),
                    'gateway' => 'dpo',
                    'business_id' => $businessId,
                    'package_id' => $package->id,
                    'user_id' => $userId,
                    'amount' => $quote['amount'],
                    'currency_code' => $currency,
                    'coupon_code' => $quote['coupon_code'],
                    'package_snapshot' => $this->snapshotPackage($package),
                    'status' => 'initiated',
                    'expires_at' => now()->addMinutes(max(5, min(1440, (int) config('dpo.payment_time_limit_minutes', 30)))),
                ]);
            });

            if (empty($attempt->gateway_token)) {
                $claimed = SubscriptionPaymentAttempt::whereKey($attempt->id)
                    ->where('status', 'initiated')
                    ->update(['status' => 'creating']);
                if ($claimed !== 1) {
                    throw ValidationException::withMessages([
                        'payment' => 'This DPO Pay checkout is already being prepared. Please wait a moment before trying again.',
                    ]);
                }
                $ownsAttemptCreation = true;
                $attempt->refresh();
                $signature = $this->dpoSignature($attempt->reference);
                $redirectUrl = route('subscription.dpo.callback', [
                    'reference' => $attempt->reference,
                    'signature' => $signature,
                ]);
                $backUrl = action([self::class, 'pay'], [
                    'package_id' => $package->id,
                    'code' => $quote['coupon_code'],
                ]);
                $response = $this->dpo->createToken(
                    $attempt,
                    Business::findOrFail($businessId),
                    auth()->user(),
                    $redirectUrl,
                    $backUrl
                );
                $attempt->update([
                    'gateway_token' => $response['TransToken'],
                    'gateway_reference' => $response['TransRef'] ?? null,
                    'status' => 'pending',
                    'provider_result_code' => $response['Result'],
                    'provider_result_message' => mb_substr((string) ($response['ResultExplanation'] ?? ''), 0, 500),
                    'provider_response' => $this->dpo->publicResult($response),
                ]);
            }

            return redirect()->away($this->dpo->paymentUrl((string) $attempt->fresh()->gateway_token));
        } catch (\Exception $exception) {
            if ($ownsAttemptCreation && isset($attempt) && empty($attempt->gateway_token)) {
                $attempt->update(['status' => 'failed']);
            }
            \Log::error('DPO Pay subscription checkout could not be created', [
                'business_id' => $businessId ?? null,
                'package_id' => (int) $package_id,
                'exception' => $exception,
            ]);

            return back()->with('status', [
                'success' => 0,
                'msg' => $exception instanceof ValidationException
                    ? collect($exception->errors())->flatten()->first()
                    : __('messages.something_went_wrong'),
            ]);
        }
    }

    /**
     * Process both the customer return and retry-safe status checks. DPO's
     * browser values never activate access until verifyToken confirms code 000,
     * amount, currency and the signed CashERP checkout reference.
     */
    public function dpoCallback(Request $request)
    {
        $request->validate([
            'reference' => ['required', 'string', 'max:64'],
            'signature' => ['required', 'string', 'size:64'],
            'TransactionToken' => ['nullable', 'string', 'max:191'],
            'TransToken' => ['nullable', 'string', 'max:191'],
        ]);
        $reference = (string) $request->input('reference');
        abort_unless(hash_equals($this->dpoSignature($reference), (string) $request->input('signature')), 403);

        try {
            $attempt = SubscriptionPaymentAttempt::where('gateway', 'dpo')
                ->where('reference', $reference)
                ->firstOrFail();
            if ($attempt->status === 'paid' && $attempt->subscription_id) {
                return $this->dpoResultRedirect(true, 'Payment was already verified and the subscription is active.');
            }

            $returnedToken = (string) ($request->input('TransactionToken') ?: $request->input('TransToken') ?: $attempt->gateway_token);
            if (empty($attempt->gateway_token) || ! hash_equals((string) $attempt->gateway_token, $returnedToken)) {
                throw ValidationException::withMessages(['payment' => 'The DPO Pay token does not match this checkout.']);
            }

            $verification = $this->dpo->verifyToken($returnedToken, false);
            $resultCode = (string) ($verification['Result'] ?? '');
            $resultMessage = mb_substr((string) ($verification['ResultExplanation'] ?? ''), 0, 500);
            $attempt->update([
                'provider_result_code' => $resultCode,
                'provider_result_message' => $resultMessage,
                'provider_response' => $this->dpo->publicResult($verification),
                'gateway_reference' => $verification['TransRef'] ?? $attempt->gateway_reference,
                'verified_at' => now(),
            ]);

            if ($resultCode !== '000') {
                $isPending = in_array($resultCode, ['001', '003', '005', '007', '900'], true);
                $attempt->update(['status' => $isPending ? 'pending' : 'failed']);

                return $this->dpoResultRedirect(
                    false,
                    $isPending
                        ? 'DPO Pay has not confirmed the payment yet. Your subscription was not activated; please check again after completing payment.'
                        : 'DPO Pay did not confirm a successful payment. Your subscription was not activated.'
                );
            }

            if (! empty($verification['CompanyRef']) && ! hash_equals($attempt->reference, (string) $verification['CompanyRef'])) {
                throw ValidationException::withMessages(['payment' => 'The verified DPO Pay reference does not match this checkout.']);
            }
            $this->pricing->assertExpectedAmount($verification['TransactionAmount'] ?? null, (float) $attempt->amount);
            $this->pricing->assertExpectedCurrency($verification['TransactionCurrency'] ?? null, $attempt->currency_code);
            $package = $this->packageFromAttempt($attempt);
            $this->pricing->assertResourceCompatibility($package, (int) $attempt->business_id);

            DB::transaction(function () use ($attempt, $package, $returnedToken, $verification) {
                $lockedAttempt = SubscriptionPaymentAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
                if ($lockedAttempt->status === 'paid' && $lockedAttempt->subscription_id) {
                    return;
                }

                $subscription = $this->_add_subscription(
                    $lockedAttempt->coupon_code,
                    (float) $lockedAttempt->amount,
                    (int) $lockedAttempt->business_id,
                    $package,
                    'dpo',
                    $returnedToken,
                    (int) $lockedAttempt->user_id
                );
                $lockedAttempt->update([
                    'status' => 'paid',
                    'subscription_id' => $subscription->id,
                    'gateway_reference' => $verification['TransRef'] ?? $lockedAttempt->gateway_reference,
                    'verified_at' => now(),
                ]);
            });

            try {
                $websiteVerification = $this->dpo->verifyToken($returnedToken, true);
                if ((string) ($websiteVerification['Result'] ?? '') !== '000') {
                    throw new \RuntimeException('DPO Pay did not acknowledge website verification.');
                }
            } catch (\Throwable $exception) {
                \Log::warning('DPO Pay transaction was activated but could not be marked website-verified', [
                    'attempt_id' => $attempt->id,
                    'exception' => $exception,
                ]);
            }

            return $this->dpoResultRedirect(true, 'DPO Pay verified the payment and activated the subscription.');
        } catch (\Exception $exception) {
            \Log::error('DPO Pay subscription verification failed', [
                'reference' => $request->input('reference'),
                'exception' => $exception,
            ]);

            return $this->dpoResultRedirect(
                false,
                $exception instanceof ValidationException
                    ? collect($exception->errors())->flatten()->first()
                    : __('messages.something_went_wrong')
            );
        }
    }

    public function verifyDpoAttempt(Request $request, $attempt_id)
    {
        if (! auth()->user()->can('superadmin.access_package_subscriptions')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = (int) $request->session()->get('user.business_id');
        $attempt = SubscriptionPaymentAttempt::whereIn('business_id', $this->accountBusinessIds($businessId))
            ->where('gateway', 'dpo')
            ->whereIn('status', ['pending', 'paid'])
            ->findOrFail($attempt_id);

        return redirect()->route('subscription.dpo.callback', [
            'reference' => $attempt->reference,
            'signature' => $this->dpoSignature($attempt->reference),
        ]);
    }

    /**
     * Save the payment details and add subscription details
     * package_id becomes null for pesapal, and it's received from session
     * @return Response
     */
    public function confirm($package_id = null, Request $request)
    {
        if (! auth()->user()->can('superadmin.access_package_subscriptions')) {
            abort(403, 'Unauthorized action.');
        }

        try {

            //Disable in demo
            if (config('app.env') == 'demo') {
                $output = ['success' => 0,
                    'msg' => 'Feature disabled in demo!!',
                ];

                return back()->with('status', $output);
            }

            $business_id = (int) $request->session()->get('user.business_id');
            $business_name = (string) $request->session()->get('business.name');
            $user_id = (int) $request->session()->get('user.id');
            $gateway = (string) $request->input('gateway');
            $gateways = $this->_payment_gateways();
            if (! array_key_exists($gateway, $gateways)) {
                throw ValidationException::withMessages(['gateway' => 'Select an available payment method.']);
            }

            $package = $this->pricing->packageForBusiness(
                (int) $package_id,
                $business_id,
                auth()->user()->can('superadmin')
            );
            $this->pricing->assertCanSubscribe($package, $business_id);
            $quote = $this->pricing->quote($package, $business_id, $request->input('coupon_code'), true);

            $pay_function = 'pay_'.$gateway;
            if (! method_exists($this, $pay_function)) {
                throw ValidationException::withMessages(['gateway' => 'The selected payment method cannot process this checkout.']);
            }

            $payment_transaction_id = $this->$pay_function(
                $business_id,
                $business_name,
                $package,
                $request,
                $quote['amount']
            );

            DB::transaction(function () use ($quote, $business_id, $package, $gateway, $payment_transaction_id, $user_id) {
                $this->_add_subscription(
                    $quote['coupon_code'],
                    $quote['amount'],
                    $business_id,
                    $package,
                    $gateway,
                    $payment_transaction_id,
                    $user_id
                );
            });

            $msg = __('lang_v1.success');
            if ($gateway == 'offline') {
                $msg = __('superadmin::lang.notification_sent_for_approval');
            }
            $output = ['success' => 1, 'msg' => $msg];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $message = $e instanceof ValidationException
                ? collect($e->errors())->flatten()->first()
                : __('messages.something_went_wrong');
            $output = ['success' => 0, 'msg' => $message];
        }

        return redirect()
            ->action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'index'])
            ->with('status', $output);
    }

    /**
     * Confirm for pesapal gateway
     * when payment gateway is PesaPal payment gateway request package_id
     * is transaction_id & merchant_reference in session contains
     * the package_id.
     *
     * @return Response
     */
    protected function confirm_pesapal($transaction_id, $request)
    {
        $merchant_reference = (string) $request->merchant_reference;
        $pesapal_session = $request->session()->pull('pesapal');

        if ($pesapal_session && hash_equals((string) $pesapal_session['ref'], $merchant_reference)) {
            $package_id = $pesapal_session['package_id'];

            $business_id = (int) $request->session()->get('user.business_id');
            $user_id = (int) $request->session()->get('user.id');
            if ((int) $pesapal_session['business_id'] !== $business_id || (int) $pesapal_session['user_id'] !== $user_id) {
                throw ValidationException::withMessages(['payment' => 'This payment belongs to a different company session.']);
            }
            if (empty($transaction_id)) {
                throw ValidationException::withMessages(['payment' => 'PesaPal did not return a transaction reference.']);
            }

            $package = Package::withTrashed()->findOrFail((int) $package_id);
            $this->pricing->assertResourceCompatibility($package, $business_id);
            $quote = [
                'amount' => (float) $pesapal_session['amount'],
                'coupon_code' => $pesapal_session['coupon_code'] ?: null,
            ];
            $this->pricing->assertExpectedCurrency($pesapal_session['currency'] ?? null, strtoupper(System::getCurrency()->code));

            DB::transaction(function () use ($quote, $business_id, $package, $transaction_id, $user_id) {
                $this->_add_subscription($quote['coupon_code'], $quote['amount'], $business_id, $package, 'pesapal', $transaction_id, $user_id);
            });
            $output = ['success' => 1, 'msg' => __('superadmin::lang.waiting_for_confirmation')];

            return redirect()
                ->action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'index'])
                ->with('status', $output);
        }

        throw ValidationException::withMessages(['payment' => 'This PesaPal checkout session is invalid or has expired.']);
    }

    public function pesapalCallback($package_id, Request $request)
    {
        if (! auth()->user()->can('superadmin.access_package_subscriptions')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $sessionPackageId = (int) $request->session()->get('pesapal.package_id');
            if ($sessionPackageId !== (int) $package_id) {
                throw ValidationException::withMessages(['payment' => 'The PesaPal package does not match this checkout.']);
            }
            $transactionId = $request->input('pesapal_transaction_tracking_id', $request->input('transaction_id'));
            return $this->confirm_pesapal($transactionId, $request);
        } catch (\Exception $e) {
            \Log::error('PesaPal subscription callback failed', ['exception' => $e]);
            return redirect()->action([self::class, 'index'])
                ->with('status', ['success' => 0, 'msg' => $e instanceof ValidationException
                    ? collect($e->errors())->flatten()->first()
                    : __('messages.something_went_wrong')]);
        }
    }

    /**
     * Stripe payment method
     *
     * @return Response
     */
    protected function pay_stripe($business_id, $business_name, $package, $request, $amount)
    {
        $request->validate([
            'stripeToken' => ['required', 'string', 'max:500'],
            'stripeEmail' => ['required', 'email:rfc', 'max:255'],
        ]);
        Stripe::setApiKey(env('STRIPE_SECRET_KEY'));

        $metadata = ['business_id' => $business_id, 'business_name' => $business_name, 'stripe_email' => $request->stripeEmail, 'package_name' => $package->name];

        $customer = Customer::create([
            'name' => 'Stripe User',
            'email' => $request->stripeEmail,
            'source' => $request->stripeToken,
            'metadata' => $metadata,
            'description' => 'Stripe payment',
        ]);

        // "address" => ["city" => $city, "country" => $country, "line1" => $address, "line2" => "", "postal_code" => $zipCode, "state" => $state]

        $system_currency = System::getCurrency();

        $charge = Charge::create([
            'amount' => $this->toMinorUnits($amount, $system_currency->code),
            'currency' => strtolower($system_currency->code),
            //"source" => $request->stripeToken,
            'customer' => $customer,
            'metadata' => $metadata,
        ]);

        if (! $charge->paid || (int) $charge->amount !== $this->toMinorUnits($amount, $system_currency->code)
            || strtoupper((string) $charge->currency) !== strtoupper($system_currency->code)) {
            throw ValidationException::withMessages(['payment' => 'Stripe did not verify the expected amount and currency.']);
        }

        return $charge->id;
    }

    /**
     * Offline payment method
     *
     * @return Response
     */
    protected function pay_offline($business_id, $business_name, $package, $request, $amount)
    {

        //Disable in demo
        if (config('app.env') == 'demo') {
            $output = ['success' => 0,
                'msg' => 'Feature disabled in demo!!',
            ];

            return back()->with('status', $output);
        }

        //Send notification
        $email = System::getProperty('email');
        $business = Business::find($business_id);

        if (! $this->moduleUtil->IsMailConfigured()) {
            return null;
        }
        $system_currency = System::getCurrency();
        $displayPackage = clone $package;
        $displayPackage->price = $system_currency->symbol.number_format($amount, 2, $system_currency->decimal_separator, $system_currency->thousand_separator);

        Notification::route('mail', $email)
            ->notify(new SubscriptionOfflinePaymentActivationConfirmation($business, $displayPackage));

        return null;
    }


    /**
     * Paypal payment method - redirect to paypal url for payments
     *
     * @return Response
     */
    public function paypalExpressCheckout(Request $request)
    {
        if (! auth()->user()->can('superadmin.access_package_subscriptions')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'package_id' => ['required', 'integer'],
            'coupon_code' => ['nullable', 'string', 'max:100'],
        ]);

        $businessId = (int) $request->session()->get('user.business_id');
        $package = $this->pricing->packageForBusiness(
            (int) $request->input('package_id'),
            $businessId,
            auth()->user()->can('superadmin')
        );
        $this->pricing->assertCanSubscribe($package, $businessId);
        $quote = $this->pricing->quote($package, $businessId, $request->input('coupon_code'), true);

        $accessToken = $this->generatePaypalAccessToken();
        $url = $this->paypalBaseUrl().'/v2/checkout/orders';
        $system_currency = System::getCurrency();
        $currency_code = strtoupper($system_currency->code);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken,
        ])->post($url, [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'amount' => [
                        'currency_code' => $currency_code,
                        'value' => number_format($quote['amount'], 2, '.', ''),
                    ],
                    'description' => $package->name,
                    'custom_id' => $package->id.':'.$businessId,
                ],
            ],
        ]);
        $response->throw();
        $data = $response->json();

        $request->session()->put('paypal_checkout', [
            'order_id' => $data['id'] ?? null,
            'package_id' => $package->id,
            'business_id' => $businessId,
            'user_id' => (int) $request->session()->get('user.id'),
            'coupon_code' => $quote['coupon_code'],
            'amount' => $quote['amount'],
            'currency' => $currency_code,
        ]);

        return $data;
    }

    public function capturePaypalOrder(Request $request)
    {
        if (! auth()->user()->can('superadmin.access_package_subscriptions')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $request->validate(['orderID' => ['required', 'string', 'max:255']]);
            $checkout = $request->session()->pull('paypal_checkout');
            if (! $checkout || ! hash_equals((string) $checkout['order_id'], (string) $request->input('orderID'))) {
                throw ValidationException::withMessages(['payment' => 'This PayPal checkout session is invalid or has expired.']);
            }

            $businessId = (int) $request->session()->get('user.business_id');
            $userId = (int) $request->session()->get('user.id');
            if ((int) $checkout['business_id'] !== $businessId || (int) $checkout['user_id'] !== $userId) {
                throw ValidationException::withMessages(['payment' => 'This payment belongs to a different company session.']);
            }

            $package = Package::withTrashed()->findOrFail((int) $checkout['package_id']);
            $this->pricing->assertResourceCompatibility($package, $businessId);
            $quote = [
                'amount' => (float) $checkout['amount'],
                'coupon_code' => $checkout['coupon_code'] ?: null,
            ];

            $accessToken = $this->generatePaypalAccessToken();
            $url = $this->paypalBaseUrl().'/v2/checkout/orders/'.rawurlencode($request->input('orderID')).'/capture';
            $response = Http::withToken($accessToken)->acceptJson()->post($url, ['intent' => 'CAPTURE']);
            $response->throw();
            $data = $response->json();

            if (($data['status'] ?? null) !== 'COMPLETED') {
                throw ValidationException::withMessages(['payment' => 'PayPal did not confirm this payment as completed.']);
            }

            $capture = $data['purchase_units'][0]['payments']['captures'][0] ?? [];
            $this->pricing->assertExpectedAmount($capture['amount']['value'] ?? null, $quote['amount']);
            $this->pricing->assertExpectedCurrency($capture['amount']['currency_code'] ?? null, $checkout['currency']);
            $transactionId = $capture['id'] ?? null;
            if (! $transactionId) {
                throw ValidationException::withMessages(['payment' => 'PayPal did not return a transaction reference.']);
            }

            DB::transaction(function () use ($quote, $businessId, $package, $transactionId, $userId) {
                $this->_add_subscription($quote['coupon_code'], $quote['amount'], $businessId, $package, 'paypal', $transactionId, $userId);
            });

            Session::flash('status', ['success' => 1, 'msg' => __('lang_v1.success')]);
            return ['success' => true, 'msg' => __('lang_v1.success')];
        } catch (\Exception $e) {
            \Log::error('PayPal subscription capture failed', ['exception' => $e]);
            return ['success' => false, 'msg' => $e instanceof ValidationException
                ? collect($e->errors())->flatten()->first()
                : __('messages.something_went_wrong')];
        }
    }

    public function generatePaypalAccessToken(){
        // Construct the credentials
        $credentials = base64_encode(config('paypal.client_id'). ':' . config('paypal.app_secret'));

        $url = $this->paypalBaseUrl().'/v1/oauth2/token';

        // Send the request to obtain the access token
        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . $credentials,
        ])
            ->asForm()
            ->post($url, [
                'grant_type' => 'client_credentials',
            ]);

        $response->throw();
        $data = $response->json();
        $accessToken = $data['access_token'] ?? null;
        if (! $accessToken) {
            throw new \RuntimeException('PayPal did not return an access token.');
        }

        return $accessToken;
    }

    private function paypalBaseUrl(): string
    {
        $mode = env('PAYPAL_MODE', 'sandbox');
        if (! in_array($mode, ['sandbox', 'live'], true)) {
            throw ValidationException::withMessages(['gateway' => 'PayPal mode is not configured correctly.']);
        }

        return $mode === 'live'
            ? rtrim(config('paypal.baseURL.production'), '/')
            : rtrim(config('paypal.baseURL.sandbox'), '/');
    }

    /**
     * Razor pay payment method
     *
     * @return Response
     */
    protected function pay_razorpay($business_id, $business_name, $package, $request, $amount)
    {
        $request->validate(['razorpay_payment_id' => ['required', 'string', 'max:255']]);
        $razorpay_payment_id = $request->razorpay_payment_id;
        $razorpay_api = new Api(env('RAZORPAY_KEY_ID'), env('RAZORPAY_KEY_SECRET'));

        $payment = $razorpay_api->payment->fetch($razorpay_payment_id)->capture(['amount' => (int) round($amount * 100)]); // Captures a payment

        if (empty($payment->error_code)) {
            if ((int) $payment->amount !== (int) round($amount * 100)
                || strtoupper((string) $payment->currency) !== strtoupper(System::getCurrency()->code)) {
                throw ValidationException::withMessages(['payment' => 'Razorpay did not verify the expected amount and currency.']);
            }
            return $payment->id;
        } else {
            $error_description = $payment->error_description;
            throw new \Exception($error_description);
        }
    }

    /**
     * Redirect the User to Paystack Payment Page
     *
     * @return Url
     */
    public function getRedirectToPaystack(Request $request)
    {
        if (! auth()->user()->can('superadmin.access_package_subscriptions')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'package_id' => ['required', 'integer'],
            'coupon_code' => ['nullable', 'string', 'max:100'],
        ]);
        $businessId = (int) $request->session()->get('user.business_id');
        $userId = (int) $request->session()->get('user.id');
        $package = $this->pricing->packageForBusiness((int) $request->input('package_id'), $businessId, auth()->user()->can('superadmin'));
        $this->pricing->assertCanSubscribe($package, $businessId);
        $quote = $this->pricing->quote($package, $businessId, $request->input('coupon_code'), true);
        $currency = $this->gatewayCurrencyCode();

        $request->merge([
            'email' => auth()->user()->email,
            'amount' => (int) round($quote['amount'] * 100),
            'quantity' => 1,
            'currency' => $currency,
            'reference' => Paystack::genTranxRef(),
            'metadata' => json_encode([
                'package_id' => $package->id,
                'business_id' => $businessId,
                'user_id' => $userId,
                'coupon_code' => $quote['coupon_code'],
                'gateway' => 'paystack',
            ]),
        ]);
        $request->session()->put('paystack_checkout', [
            'package_id' => $package->id,
            'business_id' => $businessId,
            'user_id' => $userId,
            'coupon_code' => $quote['coupon_code'],
            'amount' => $quote['amount'],
            'currency' => $currency,
            'reference' => $request->input('reference'),
        ]);

        return Paystack::getAuthorizationUrl()->redirectNow();
    }

    /**
     * Obtain Paystack payment information
     *
     * @return void
     */
    public function postPaymentPaystackCallback(Request $request)
    {
        if (! auth()->user()->can('superadmin.access_package_subscriptions')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $payment = Paystack::getPaymentData();
            $data = $payment['data'] ?? [];
            $meta = $data['metadata'] ?? [];
            $businessId = (int) $request->session()->get('user.business_id');
            $userId = (int) $request->session()->get('user.id');
            $checkout = $request->session()->pull('paystack_checkout');

            if (! ($payment['status'] ?? false) || ($data['status'] ?? null) !== 'success') {
                throw ValidationException::withMessages(['payment' => 'Paystack did not confirm this payment as successful.']);
            }
            if ((int) ($meta['business_id'] ?? 0) !== $businessId || (int) ($meta['user_id'] ?? 0) !== $userId) {
                throw ValidationException::withMessages(['payment' => 'This payment belongs to a different company session.']);
            }
            if (! $checkout
                || (int) $checkout['business_id'] !== $businessId
                || (int) $checkout['user_id'] !== $userId
                || (int) $checkout['package_id'] !== (int) ($meta['package_id'] ?? 0)
                || (string) ($checkout['reference'] ?? '') !== (string) ($data['reference'] ?? '')
                || (string) ($checkout['coupon_code'] ?? '') !== (string) ($meta['coupon_code'] ?? '')) {
                throw ValidationException::withMessages(['payment' => 'This Paystack checkout session is invalid or has expired.']);
            }

            $package = Package::withTrashed()->findOrFail((int) ($meta['package_id'] ?? 0));
            $this->pricing->assertResourceCompatibility($package, $businessId);
            $quote = ['amount' => (float) $checkout['amount'], 'coupon_code' => $checkout['coupon_code'] ?: null];
            $this->pricing->assertExpectedAmount(((float) ($data['amount'] ?? 0)) / 100, $quote['amount']);
            $this->pricing->assertExpectedCurrency($data['currency'] ?? null, $checkout['currency']);
            $transactionId = $data['reference'] ?? null;
            if (! $transactionId) {
                throw ValidationException::withMessages(['payment' => 'Paystack did not return a transaction reference.']);
            }

            DB::transaction(function () use ($quote, $businessId, $package, $transactionId, $userId) {
                $this->_add_subscription($quote['coupon_code'], $quote['amount'], $businessId, $package, 'paystack', $transactionId, $userId);
            });

            return redirect()->action([self::class, 'index'])
                ->with('status', ['success' => 1, 'msg' => __('lang_v1.success')]);
        } catch (\Exception $e) {
            \Log::error('Paystack subscription callback failed', ['exception' => $e]);
            return redirect()->action([self::class, 'index'])
                ->with('status', ['success' => 0, 'msg' => $e instanceof ValidationException
                    ? collect($e->errors())->flatten()->first()
                    : __('messages.something_went_wrong')]);
        }
    }

    /**
     * Obtain Flutterwave payment information
     *
     * @return response
     */
    public function postFlutterwavePaymentCallback(Request $request)
    {
        if (! auth()->user()->can('superadmin.access_package_subscriptions')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $validated = $request->validate(['transaction_id' => ['required', 'regex:/^[0-9]+$/']]);
            $response = Http::withToken(env('FLUTTERWAVE_SECRET_KEY'))
                ->acceptJson()
                ->timeout(30)
                ->get('https://api.flutterwave.com/v3/transactions/'.rawurlencode($validated['transaction_id']).'/verify');
            $response->throw();
            $payment = $response->json();
            $data = $payment['data'] ?? [];
            $meta = $data['meta'] ?? [];
            if (($payment['status'] ?? null) !== 'success' || ($data['status'] ?? null) !== 'successful') {
                throw ValidationException::withMessages(['payment' => 'Flutterwave did not confirm this payment as successful.']);
            }

            $businessId = (int) $request->session()->get('user.business_id');
            $userId = (int) $request->session()->get('user.id');
            $checkout = $request->session()->pull('subscription_checkout');
            if (! $checkout
                || (int) ($checkout['business_id'] ?? 0) !== $businessId
                || (int) ($checkout['user_id'] ?? 0) !== $userId
                || (int) ($checkout['package_id'] ?? 0) !== (int) ($meta['package_id'] ?? 0)
                || (string) ($checkout['coupon_code'] ?? '') !== (string) ($meta['coupon_code'] ?? '')) {
                throw ValidationException::withMessages(['payment' => 'This Flutterwave checkout session is invalid or has expired.']);
            }
            if ((int) ($meta['business_id'] ?? 0) !== $businessId || (int) ($meta['user_id'] ?? 0) !== $userId) {
                throw ValidationException::withMessages(['payment' => 'This payment belongs to a different company session.']);
            }

            $package = Package::withTrashed()->findOrFail((int) ($meta['package_id'] ?? 0));
            $this->pricing->assertResourceCompatibility($package, $businessId);
            $quote = ['amount' => (float) $checkout['amount'], 'coupon_code' => $checkout['coupon_code'] ?: null];
            $this->pricing->assertExpectedAmount($data['amount'] ?? null, $quote['amount']);
            $this->pricing->assertExpectedCurrency($data['currency'] ?? null, $checkout['currency']);
            $transactionId = $data['tx_ref'] ?? null;
            if (! $transactionId) {
                throw ValidationException::withMessages(['payment' => 'Flutterwave did not return a transaction reference.']);
            }

            DB::transaction(function () use ($quote, $businessId, $package, $transactionId, $userId) {
                $this->_add_subscription($quote['coupon_code'], $quote['amount'], $businessId, $package, 'flutterwave', $transactionId, $userId);
            });

            return redirect()->action([self::class, 'index'])
                ->with('status', ['success' => 1, 'msg' => __('lang_v1.success')]);
        } catch (\Exception $e) {
            \Log::error('Flutterwave subscription callback failed', ['exception' => $e]);
            return redirect()->action([self::class, 'index'])
                ->with('status', ['success' => 0, 'msg' => $e instanceof ValidationException
                    ? collect($e->errors())->flatten()->first()
                    : __('messages.something_went_wrong')]);
        }
    }

    /**
     * Show the specified resource.
     *
     * @return Response
     */
    public function show($id)
    {
        if (! auth()->user()->can('superadmin.access_package_subscriptions')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $subscription = Subscription::whereIn('business_id', $this->accountBusinessIds((int) $business_id))
                                    ->with(['package', 'created_user', 'business'])
                                    ->findOrFail($id);

        $system_settings = System::getProperties([
            'invoice_business_name',
            'email',
            'invoice_business_landmark',
            'invoice_business_city',
            'invoice_business_zip',
            'invoice_business_state',
            'invoice_business_country',
        ]);
        $system = [];
        foreach ($system_settings as $setting) {
            $system[$setting['key']] = $setting['value'];
        }

        return view('superadmin::subscription.show_subscription_modal')
            ->with(compact('subscription', 'system'));
    }

     /**
     * Get MyFatoorah Payment Information
     * Provide the callback method with the paymentId
     */

    public function myfatoorahcallback(Request $request) {
        if (! auth()->user()->can('superadmin.access_package_subscriptions')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $paymentId = request('paymentId');

            $mfObj = new MyFatoorahPaymentStatus($this->mfConfig);
            $data  = $mfObj->getPaymentStatus($paymentId, 'PaymentId');

           
            
            if ($data->InvoiceStatus == 'Paid') {
                $checkout = $request->session()->pull('myfatoorah_checkout');
                $metadata = json_decode((string) $data->UserDefinedField, true) ?: [];
                if (! $checkout || ! hash_equals((string) $checkout['token'], (string) ($metadata['checkout_token'] ?? ''))) {
                    throw ValidationException::withMessages(['payment' => 'This MyFatoorah checkout session is invalid or has expired.']);
                }

                $business_id = (int) $request->session()->get('user.business_id');
                $user_id = (int) $request->session()->get('user.id');
                if ((int) $checkout['business_id'] !== $business_id || (int) $checkout['user_id'] !== $user_id
                    || (int) ($metadata['business_id'] ?? 0) !== $business_id || (int) ($metadata['user_id'] ?? 0) !== $user_id) {
                    throw ValidationException::withMessages(['payment' => 'This payment belongs to a different company session.']);
                }

                $package = Package::withTrashed()->findOrFail((int) $data->CustomerReference);
                if ((int) $checkout['package_id'] !== (int) $package->id) {
                    throw ValidationException::withMessages(['payment' => 'The paid package does not match this checkout.']);
                }
                $this->pricing->assertResourceCompatibility($package, $business_id);
                $quote = ['amount' => (float) $checkout['amount'], 'coupon_code' => $checkout['coupon_code'] ?: null];
                $this->pricing->assertExpectedAmount($data->InvoiceValue, $quote['amount']);
                $paidCurrency = $data->DisplayCurrencyIso ?? null;
                if (! $paidCurrency && ! empty($data->InvoiceTransactions) && isset($data->InvoiceTransactions[0]->Currency)) {
                    $paidCurrency = $data->InvoiceTransactions[0]->Currency;
                }
                $paidCurrency = $paidCurrency ?: $checkout['currency'];
                $this->pricing->assertExpectedCurrency($paidCurrency, $checkout['currency']);
                $payment_transaction_id = $data->InvoiceReference;
                if (! $payment_transaction_id) {
                    throw ValidationException::withMessages(['payment' => 'MyFatoorah did not return a transaction reference.']);
                }

                DB::transaction(function () use ($quote, $business_id, $package, $payment_transaction_id, $user_id) {
                    $this->_add_subscription($quote['coupon_code'], $quote['amount'], $business_id, $package, 'myfatoorah', $payment_transaction_id, $user_id);
                });

                return redirect()
                ->action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'index'])
                ->with('status', ['success' => 1, 'msg' => __('lang_v1.success')]);
                                
            }elseif($data->InvoiceStatus == 'Failed'){

                return redirect()
                ->action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'index'])
                ->with('status', ['success' => 0, 'msg' => $data->InvoiceError]);

            }else if($data->InvoiceStatus == 'Expired'){

                return redirect()
                ->action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'index'])
                ->with('status', ['success' => 0, 'msg' => $data->InvoiceError]);

            }


        } catch (\Exception $ex) {
            \Log::error('MyFatoorah subscription callback failed', ['exception' => $ex]);
            return redirect()->action([self::class, 'index'])
                ->with('status', ['success' => 0, 'msg' => $ex instanceof ValidationException
                    ? collect($ex->errors())->flatten()->first()
                    : __('messages.something_went_wrong')]);
        }
    }



    /**
     * Retrieves list of all subscriptions for the current business
     *
     * @return \Illuminate\Http\Response
     */
    public function allSubscriptions()
    {
        if (! auth()->user()->can('superadmin.access_package_subscriptions')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $subscriptions = Subscription::whereIn('subscriptions.business_id', $this->accountBusinessIds((int) $business_id))
                        ->whereNull('subscriptions.covered_by_subscription_id')
                        ->leftjoin(
                            'packages as P',
                            'subscriptions.package_id',
                            '=',
                            'P.id'
                        )
                        ->leftjoin(
                            'users as U',
                            'subscriptions.created_id',
                            '=',
                            'U.id'
                        )
                        ->addSelect(
                            'P.name as package_name',
                            DB::raw("CONCAT(COALESCE(U.surname, ''), ' ', COALESCE(U.first_name, ''), ' ', COALESCE(U.last_name, '')) as created_by"),
                            'subscriptions.*'
                        );

        return Datatables::of($subscriptions)
             ->editColumn(
                 'start_date',
                 '@if(!empty($start_date)){{@format_date($start_date)}}@endif'
             )
             ->editColumn(
                 'end_date',
                 '@if(!empty($end_date)){{@format_date($end_date)}}@endif'
             )
             ->editColumn(
                 'trial_end_date',
                 '@if(!empty($trial_end_date)){{@format_date($trial_end_date)}}@endif'
             )
             ->editColumn(
                 'package_price',
                 '<span class="display_currency" data-currency_symbol="true">{{$package_price}}</span>'
             )
             ->editColumn(
                 'created_at',
                 '@if(!empty($created_at)){{@format_date($created_at)}}@endif'
             )
             ->filterColumn('created_by', function ($query, $keyword) {
                 $query->whereRaw("CONCAT(COALESCE(U.surname, ''), ' ', COALESCE(U.first_name, ''), ' ', COALESCE(U.last_name, '')) like ?", ["%{$keyword}%"]);
             })
             ->addColumn('action', function ($row) {
                 return '<button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline  tw-dw-btn-primary btn-modal" data-container=".view_modal" data-href="'.action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'show'], $row->id).'" ><i class="fa fa-eye" aria-hidden="true"></i> '.__('messages.view').'</button>';
             })
             ->rawColumns(['package_price', 'action'])
             ->make(true);
    }

    public function forceActive($id)
    {
        if (! auth()->user()->can('superadmin.access_package_subscriptions')) {
            abort(403, 'Unauthorized action.');
        }
        abort_unless(request()->ajax(), 404);

        try {
            $businessId = (int) request()->session()->get('user.business_id');
            DB::transaction(function () use ($businessId, $id) {
                $today = Carbon::today();
                $businessIds = $this->accountBusinessIds($businessId);
                $subscription = Subscription::whereIn('business_id', $businessIds)
                    ->whereNull('covered_by_subscription_id')
                    ->approved()
                    ->whereDate('start_date', '>', $today)
                    ->lockForUpdate()
                    ->findOrFail($id);
                $packageTerm = $this->packageTermSnapshot($subscription);

                Subscription::whereIn('business_id', $businessIds)
                    ->approved()
                    ->whereDate('start_date', '<=', $today)
                    ->whereDate('end_date', '>=', $today)
                    ->lockForUpdate()
                    ->get()
                    ->each(function ($active) use ($today) {
                        $active->update(['end_date' => $today->copy()->subDay()->toDateString()]);
                    });

                $endDate = $this->calculate_end_date($packageTerm);
                $trialEndDate = null;
                if ((int) $packageTerm->trial_days > 0) {
                    $trialEnd = $today->copy()->addDays($packageTerm->trial_days);
                    $planEnd = Carbon::parse($endDate);
                    $trialEndDate = ($trialEnd->gt($planEnd) ? $planEnd : $trialEnd)->toDateString();
                }

                $subscription->update([
                    'start_date' => $today->toDateString(),
                    'end_date' => $endDate,
                    'trial_end_date' => $trialEndDate,
                ]);
            });

            return ['success' => true, 'msg' => __('lang_v1.success')];
        } catch (\Exception $e) {
            \Log::error('Subscription activation failed', ['exception' => $e]);
            return ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }
    }

    public function calculate_end_date($package) {
       
        $start_date = \Carbon::today();
        if ($package->interval == 'days') {
            $end_date = $start_date->addDays($package->interval_count)->toDateString();
        } elseif ($package->interval == 'months') {
            $end_date = $start_date->addMonthsNoOverflow($package->interval_count)->toDateString();
        } elseif ($package->interval == 'years') {
            $end_date = $start_date->addYearsNoOverflow($package->interval_count)->toDateString();
        } else {
            throw ValidationException::withMessages(['package' => 'The package billing interval is invalid.']);
        }

        return $end_date;
    }

    private function toMinorUnits(float $amount, string $currency): int
    {
        $zeroDecimalCurrencies = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];
        return (int) round($amount * (in_array(strtoupper($currency), $zeroDecimalCurrencies, true) ? 1 : 100));
    }

    private function gatewayCurrencyCode(): string
    {
        $currency = System::getCurrency();
        if ($currency->country === 'Nigeria') {
            return 'NGN';
        }
        if ($currency->country === 'Ghana') {
            return 'GHS';
        }

        return strtoupper($currency->code);
    }

    private function accountBusinessIds(int $businessId)
    {
        $ownerId = Business::whereKey($businessId)->value('owner_id');

        return $ownerId
            ? Business::where('owner_id', $ownerId)->pluck('id')
            : collect([$businessId]);
    }

    private function snapshotPackage(Package $package): array
    {
        return [
            'name' => $package->name,
            'price' => (float) $package->price,
            'max_businesses' => (int) $package->max_businesses,
            'location_count' => (int) $package->location_count,
            'user_count' => (int) $package->user_count,
            'product_count' => (int) $package->product_count,
            'invoice_count' => (int) $package->invoice_count,
            'interval' => $package->interval,
            'interval_count' => (int) $package->interval_count,
            'trial_days' => (int) $package->trial_days,
            'is_one_time' => (bool) $package->is_one_time,
            'custom_permissions' => (array) $package->custom_permissions,
        ];
    }

    private function packageFromAttempt(SubscriptionPaymentAttempt $attempt): Package
    {
        $package = Package::withTrashed()->findOrFail($attempt->package_id);
        foreach ((array) $attempt->package_snapshot as $key => $value) {
            $package->setAttribute($key, $value);
        }

        return $package;
    }

    private function dpoSignature(string $reference): string
    {
        return hash_hmac('sha256', 'dpo-subscription|'.$reference, (string) config('app.key'));
    }

    private function dpoResultRedirect(bool $success, string $message)
    {
        $target = auth()->check()
            ? action([self::class, 'index'])
            : route('login');

        return redirect($target)->with('status', [
            'success' => $success ? 1 : 0,
            'msg' => $message,
        ]);
    }
}
