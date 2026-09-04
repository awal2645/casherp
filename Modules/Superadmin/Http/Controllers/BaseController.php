<?php

namespace Modules\Superadmin\Http\Controllers;

use App\System;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\Routing\Controller;
use Modules\Superadmin\Entities\Package;
use Modules\Superadmin\Entities\Subscription;
use Modules\Superadmin\Notifications\NewSubscriptionNotification;
use Notification;

class BaseController extends Controller
{
    /**
     * Returns the list of all configured payment gateway
     *
     * @return Response
     */
    public function _payment_gateways()
    {
        $gateways = [];

        //Check if stripe is configured or not
        if (env('STRIPE_PUB_KEY') && env('STRIPE_SECRET_KEY')) {
            $gateways['stripe'] = 'Stripe';
        }

        //Check if paypal is configured or not
        if (env('PAYPAL_CLIENT_ID') && env('PAYPAL_APP_SECRET')) {
            $gateways['paypal'] = 'PayPal';
        }

        //Check if Razorpay is configured or not
        if ((env('RAZORPAY_KEY_ID') && env('RAZORPAY_KEY_SECRET'))) {
            $gateways['razorpay'] = 'Razor Pay';
        }

        //Check if Pesapal is configured or not
        if ((config('pesapal.consumer_key') && config('pesapal.consumer_secret'))) {
            $gateways['pesapal'] = 'PesaPal';
        }

        //check if Paystack is configured or not
        $system = System::getCurrency();
        if (in_array($system->country, ['Nigeria', 'Ghana']) && (config('paystack.publicKey') && config('paystack.secretKey'))) {
            $gateways['paystack'] = 'Paystack';
        }

        //check if Flutterwave is configured or not
        if (env('FLUTTERWAVE_PUBLIC_KEY') && env('FLUTTERWAVE_SECRET_KEY') && env('FLUTTERWAVE_ENCRYPTION_KEY')) {
            $gateways['flutterwave'] = 'Flutterwave';
        }

         //check if MY FATOORAH is configured or not
        if (env('MY_FATOORAH_API_KEY') && env('MY_FATOORAH_COUNTRY_ISO')) {
            $gateways['myfatoorah'] = 'My Fatoorah';
        }

        // DPO Pay uses a server-side token and hosted checkout. Never expose
        // the company token to the browser.
        if (config('dpo.enabled') && config('dpo.company_token') && config('dpo.service_type')) {
            $gateways['dpo'] = 'DPO Pay';
        }
        // check if offline payment is enabled or not
        $is_offline_payment_enabled = System::getProperty('enable_offline_payment');

        if ($is_offline_payment_enabled) {
            $gateways['offline'] = 'Offline';
        }

        return $gateways;
    }

    /**
     * Enter details for subscriptions
     *
     * @return object
     */
    public function _add_subscription($code, $price, $business_id, $package, $gateway, $payment_transaction_id, $user_id, $is_superadmin = false)
    {
        if (! is_object($package)) {
            $package = Package::active()->findOrFail($package);
        }

        if (in_array($gateway, ['offline', 'pesapal'], true) && ! $is_superadmin) {
            $pending = Subscription::where('business_id', $business_id)
                ->where('package_id', $package->id)
                ->waiting()
                ->lockForUpdate()
                ->first();
            if ($pending) {
                return $pending;
            }
        }

        $dedupeKey = null;
        if (! empty($gateway) && ! empty($payment_transaction_id)) {
            $dedupeKey = $this->paymentDedupeKey($gateway, $payment_transaction_id);
            $existing = Subscription::where(function ($query) use ($dedupeKey, $gateway, $payment_transaction_id) {
                if (Schema::hasColumn('subscriptions', 'payment_dedupe_key')) {
                    $query->where('payment_dedupe_key', $dedupeKey);
                } else {
                    $query->where('paid_via', $gateway)
                        ->where('payment_transaction_id', $payment_transaction_id);
                }
            })->lockForUpdate()->first();

            if ($existing) {
                if ((int) $existing->business_id !== (int) $business_id || (int) $existing->package_id !== (int) $package->id) {
                    throw ValidationException::withMessages(['payment' => 'This payment transaction has already been used.']);
                }

                return $existing;
            }
        }

        $subscription = ['business_id' => $business_id,
            'package_id' => $package->id,
            'paid_via' => $gateway,
            'payment_transaction_id' => $payment_transaction_id,
        ];
        if (Schema::hasColumn('subscriptions', 'payment_dedupe_key')) {
            $subscription['payment_dedupe_key'] = $dedupeKey;
        }

        if ($package->price != 0 && (in_array($gateway, ['offline', 'pesapal']) && ! $is_superadmin)) {
            //If offline then dates will be decided when approved by superadmin
            $subscription['start_date'] = null;
            $subscription['end_date'] = null;
            $subscription['trial_end_date'] = null;
            $subscription['status'] = 'waiting';
        } else {
            $dates = $this->_get_package_dates($business_id, $package);

            $subscription['start_date'] = $dates['start'];
            $subscription['end_date'] = $dates['end'];
            $subscription['trial_end_date'] = $dates['trial'];
            $subscription['status'] = 'approved';
        }

        $subscription['package_price'] = round(max(0, (float) $price), 4);
        $subscription['coupon_code'] = $code;
        $subscription['original_price'] = $package->price;
        if (Schema::hasColumn('subscriptions', 'currency_code')) {
            $subscription['currency_code'] = strtoupper(System::getCurrency()->code);
        }
        $subscription['package_details'] = [
            'max_businesses' => $package->max_businesses,
            'location_count' => $package->location_count,
            'user_count' => $package->user_count,
            'product_count' => $package->product_count,
            'invoice_count' => $package->invoice_count,
            'name' => $package->name,
            'price' => (float) $package->price,
            'interval' => $package->interval,
            'interval_count' => (int) $package->interval_count,
            'trial_days' => (int) $package->trial_days,
            'is_one_time' => (bool) $package->is_one_time,
            'currency_code' => strtoupper(System::getCurrency()->code),
        ];
        //Custom permissions.
        if (! empty($package->custom_permissions)) {
            foreach ($package->custom_permissions as $name => $value) {
                $subscription['package_details'][$name] = $value;
            }
        }

        $subscription['created_id'] = $user_id;
        $subscription = Subscription::create($subscription);

        if (! $is_superadmin) {
            $email = System::getProperty('email');
            $is_notif_enabled = System::getProperty('enable_new_subscription_notification');

            if (! empty($email) && $is_notif_enabled == 1) {
                Notification::route('mail', $email)
                ->notify(new NewSubscriptionNotification($subscription));
            }
        }

        return $subscription;
    }

    /**
     * The function returns the start/end/trial end date for a package.
     *
     * @param  int  $business_id
     * @param  object  $package
     * @return array
     */
    protected function _get_package_dates($business_id, $package)
    {
        $output = ['start' => '', 'end' => '', 'trial' => ''];

        //calculate start date
        $start_date = Subscription::end_date($business_id)->copy();
        $output['start'] = $start_date->toDateString();

        //Calculate end date
        if ($package->interval == 'days') {
            $output['end'] = $start_date->copy()->addDays($package->interval_count)->toDateString();
        } elseif ($package->interval == 'months') {
            $output['end'] = $start_date->copy()->addMonthsNoOverflow($package->interval_count)->toDateString();
        } elseif ($package->interval == 'years') {
            $output['end'] = $start_date->copy()->addYearsNoOverflow($package->interval_count)->toDateString();
        } else {
            throw ValidationException::withMessages(['package' => 'The package billing interval is invalid.']);
        }

        if ((int) $package->trial_days > 0) {
            $trialEnd = $start_date->copy()->addDays($package->trial_days);
            $planEnd = \Carbon\Carbon::parse($output['end']);
            $output['trial'] = ($trialEnd->gt($planEnd) ? $planEnd : $trialEnd)->toDateString();
        } else {
            $output['trial'] = null;
        }

        return $output;
    }

    protected function paymentDedupeKey($gateway, $transactionId): ?string
    {
        if (empty($gateway) || empty($transactionId)) {
            return null;
        }

        return hash('sha256', strtolower(trim((string) $gateway)).'|'.trim((string) $transactionId));
    }

    protected function packageTermSnapshot(Subscription $subscription): object
    {
        $details = (array) $subscription->package_details;
        $package = $subscription->package;

        return (object) [
            'interval' => $details['interval'] ?? $package->interval,
            'interval_count' => (int) ($details['interval_count'] ?? $package->interval_count),
            'trial_days' => (int) ($details['trial_days'] ?? $package->trial_days),
        ];
    }
}
