<?php

namespace Modules\Superadmin\Http\Controllers;

use App\System;
use App\Rules\PublicSmtpHost;
use App\Utils\BusinessUtil;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SuperadminSettingsController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $businessUtil;

    protected $mailDrivers;

    protected $backupDisk;

    public function __construct(BusinessUtil $businessUtil)
    {
        $this->businessUtil = $businessUtil;

        $this->mailDrivers = [
            'smtp' => 'SMTP',
        ];

        $this->backupDisk = ['local' => 'Local', 'dropbox' => 'Dropbox'];
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function edit()
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        $settings = System::pluck('value', 'key');
        $currencies = $this->businessUtil->allCurrencies();

        $superadmin_version = System::getProperty('superadmin_version');
        $is_demo = env('APP_ENV') == 'demo' ? true : false;

        $default_values = [
            'APP_NAME' => env('APP_NAME'),
            'APP_TITLE' => env('APP_TITLE'),
            'APP_LOCALE' => env('APP_LOCALE'),
            'MAIL_MAILER' => $is_demo ? null : 'smtp',
            'MAIL_HOST' => $is_demo ? null : config('mail.system_smtp.host'),
            'MAIL_PORT' => $is_demo ? null : config('mail.system_smtp.port'),
            'MAIL_USERNAME' => $is_demo ? null : config('mail.system_smtp.username'),
            // Secret values must never be rendered back into an admin page.
            'MAIL_PASSWORD' => null,
            'MAIL_PASSWORD_CONFIGURED' => ! $is_demo && ! empty(config('mail.system_smtp.password')),
            'MAIL_ENCRYPTION' => $is_demo ? null : config('mail.system_smtp.encryption'),
            'MAIL_FROM_ADDRESS' => $is_demo ? null : config('mail.system_smtp.from_address'),
            'MAIL_FROM_NAME' => $is_demo ? null : config('mail.system_smtp.from_name'),
            'STRIPE_PUB_KEY' => $is_demo ? null : env('STRIPE_PUB_KEY'),
            'STRIPE_SECRET_KEY' => $is_demo ? null : env('STRIPE_SECRET_KEY'),
            'PAYPAL_MODE' => env('PAYPAL_MODE'),
            'PAYPAL_CLIENT_ID' => $is_demo ? null : env('PAYPAL_CLIENT_ID'),
            'PAYPAL_APP_SECRET' => $is_demo ? null : env('PAYPAL_APP_SECRET'),
            'BACKUP_DISK' => env('BACKUP_DISK'),
            'DROPBOX_ACCESS_TOKEN' => $is_demo ? null : env('DROPBOX_ACCESS_TOKEN'),
            'RAZORPAY_KEY_ID' => $is_demo ? null : env('RAZORPAY_KEY_ID'),
            'RAZORPAY_KEY_SECRET' => $is_demo ? null : env('RAZORPAY_KEY_SECRET'),

            'PESAPAL_CONSUMER_KEY' => $is_demo ? null : env('PESAPAL_CONSUMER_KEY'),
            'PESAPAL_CONSUMER_SECRET' => $is_demo ? null : env('PESAPAL_CONSUMER_SECRET'),
            'PESAPAL_LIVE' => $is_demo ? null : (env('PESAPAL_LIVE') ? 'true' : 'false'),
            'PUSHER_APP_ID' => $is_demo ? null : env('PUSHER_APP_ID'),
            'PUSHER_APP_KEY' => $is_demo ? null : env('PUSHER_APP_KEY'),
            'PUSHER_APP_SECRET' => $is_demo ? null : env('PUSHER_APP_SECRET'),
            'PUSHER_APP_CLUSTER' => $is_demo ? null : env('PUSHER_APP_CLUSTER'),
            'GOOGLE_MAP_API_KEY' => $is_demo ? null : env('GOOGLE_MAP_API_KEY'),
            'GOOGLE_SOCIAL_LOGIN_ENABLED' => $is_demo ? null : (config('services.google.enabled') ? 'true' : 'false'),
            'GOOGLE_OAUTH_CLIENT_ID' => $is_demo ? null : config('services.google.client_id'),
            'GOOGLE_OAUTH_CLIENT_SECRET' => null,
            'GOOGLE_OAUTH_CLIENT_SECRET_CONFIGURED' => ! $is_demo && ! empty(config('services.google.client_secret')),
            'GOOGLE_OAUTH_REDIRECT_URI' => $is_demo ? null : config('services.google.redirect'),
            'ALLOW_REGISTRATION' => $is_demo ? null : env('ALLOW_REGISTRATION'),
            'PAYSTACK_PUBLIC_KEY' => $is_demo ? null : env('PAYSTACK_PUBLIC_KEY'),
            'PAYSTACK_SECRET_KEY' => $is_demo ? null : env('PAYSTACK_SECRET_KEY'),
            'FLUTTERWAVE_PUBLIC_KEY' => $is_demo ? null : env('FLUTTERWAVE_PUBLIC_KEY'),
            'FLUTTERWAVE_SECRET_KEY' => $is_demo ? null : env('FLUTTERWAVE_SECRET_KEY'),
            'FLUTTERWAVE_ENCRYPTION_KEY' => $is_demo ? null : env('FLUTTERWAVE_ENCRYPTION_KEY'),
            'MY_FATOORAH_API_KEY' => $is_demo ? null : env('MY_FATOORAH_API_KEY'),
            'MY_FATOORAH_COUNTRY_ISO' => $is_demo ? null : env('MY_FATOORAH_COUNTRY_ISO'),
            'MY_FATOORAH_IS_TEST' => $is_demo ? null : (env('MY_FATOORAH_IS_TEST') ? 'true' : 'false'),
            'DPO_ENABLED' => $is_demo ? null : (config('dpo.enabled') ? 'true' : 'false'),
            'DPO_MODE' => $is_demo ? null : config('dpo.mode'),
            // DPO company tokens are secrets and are never rendered back.
            'DPO_COMPANY_TOKEN' => null,
            'DPO_COMPANY_TOKEN_CONFIGURED' => ! $is_demo && ! empty(config('dpo.company_token')),
            'DPO_SERVICE_TYPE' => $is_demo ? null : config('dpo.service_type'),
            'DPO_SERVICE_DESCRIPTION' => $is_demo ? null : config('dpo.service_description'),

            // Captcha settings
            'ENABLE_RECAPTCHA' => $is_demo ? null : env('ENABLE_RECAPTCHA'),
            'GOOGLE_RECAPTCHA_KEY' => $is_demo ? null : env('GOOGLE_RECAPTCHA_KEY'),
            'GOOGLE_RECAPTCHA_SECRET' => $is_demo ? null : env('GOOGLE_RECAPTCHA_SECRET'),
            'DO_NOT_ALLOW_DISPOSABLE_EMAIL' => $is_demo ? null : env('DO_NOT_ALLOW_DISPOSABLE_EMAIL'),

        ];
        $mail_drivers = $this->mailDrivers;

        $config_languages = config('constants.langs');
        $languages = [];
        foreach ($config_languages as $key => $value) {
            $languages[$key] = $value['full_name'];
        }
        $backup_disk = $this->backupDisk;

        $cron_job_command = $this->businessUtil->getCronJobCommand();

        return view('superadmin::superadmin_settings.edit')
            ->with(compact(
                'currencies',
                'settings',
                'superadmin_version',
                'mail_drivers',
                'languages',
                'default_values',
                'backup_disk',
                'cron_job_command'
            ));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function update(Request $request)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        try {

            //Disable .ENV settings in demo
            if (config('app.env') == 'demo') {
                $output = ['success' => 0,
                    'msg' => 'Feature disabled in demo!!',
                ];

                return back()->with('status', $output);
            }

            $request->validate([
                'MAIL_MAILER' => ['required', Rule::in(['smtp'])],
                'MAIL_HOST' => ['required', 'string', 'max:253', new PublicSmtpHost],
                'MAIL_PORT' => ['required', 'integer', 'between:1,65535'],
                'MAIL_USERNAME' => ['nullable', 'string', 'max:255'],
                'MAIL_PASSWORD' => ['nullable', 'string', 'max:4096'],
                'CLEAR_MAIL_PASSWORD' => ['nullable', 'boolean'],
                'MAIL_ENCRYPTION' => ['nullable', Rule::in(['tls', 'ssl'])],
                'MAIL_FROM_ADDRESS' => ['required', 'email:rfc', 'max:255'],
                'MAIL_FROM_NAME' => ['required', 'string', 'max:255'],
                'DPO_ENABLED' => ['nullable', 'boolean'],
                'DPO_MODE' => ['nullable', Rule::in(['test', 'live'])],
                'DPO_COMPANY_TOKEN' => ['nullable', 'uuid'],
                'CLEAR_DPO_COMPANY_TOKEN' => ['nullable', 'boolean'],
                'DPO_SERVICE_TYPE' => ['nullable', 'integer', 'min:1'],
                'DPO_SERVICE_DESCRIPTION' => ['nullable', 'string', 'max:100'],
                'GOOGLE_SOCIAL_LOGIN_ENABLED' => ['nullable', 'boolean'],
                'GOOGLE_OAUTH_CLIENT_ID' => ['nullable', 'string', 'max:512'],
                'GOOGLE_OAUTH_CLIENT_SECRET' => ['nullable', 'string', 'max:4096'],
                'CLEAR_GOOGLE_OAUTH_CLIENT_SECRET' => ['nullable', 'boolean'],
                'GOOGLE_OAUTH_REDIRECT_URI' => ['required', 'url', 'starts_with:https://', 'max:2048'],
            ]);

            $dpoEnabled = $request->boolean('DPO_ENABLED');
            $dpoTokenWillExist = $request->filled('DPO_COMPANY_TOKEN')
                || (! $request->boolean('CLEAR_DPO_COMPANY_TOKEN') && ! empty(config('dpo.company_token')));
            if ($dpoEnabled && (! $dpoTokenWillExist || ! $request->filled('DPO_SERVICE_TYPE') || ! $request->filled('DPO_SERVICE_DESCRIPTION'))) {
                throw ValidationException::withMessages([
                    'DPO_COMPANY_TOKEN' => 'DPO Pay requires a company token, service type and service description before it can be enabled.',
                ]);
            }

            $googleEnabled = $request->boolean('GOOGLE_SOCIAL_LOGIN_ENABLED');
            $googleSecretWillExist = $request->filled('GOOGLE_OAUTH_CLIENT_SECRET')
                || (! $request->boolean('CLEAR_GOOGLE_OAUTH_CLIENT_SECRET') && ! empty(config('services.google.client_secret')));
            if ($googleEnabled && (! $request->filled('GOOGLE_OAUTH_CLIENT_ID') || ! $googleSecretWillExist)) {
                throw ValidationException::withMessages([
                    'GOOGLE_OAUTH_CLIENT_ID' => 'Google sign-in requires a client ID, client secret and HTTPS callback URI before it can be enabled.',
                ]);
            }

            $system_settings = $request->only(['app_currency_id', 'invoice_business_name', 'email', 'invoice_business_landmark', 'invoice_business_zip', 'invoice_business_state', 'invoice_business_city', 'invoice_business_country', 'package_expiry_alert_days', 'superadmin_register_tc', 'welcome_email_subject', 'welcome_email_body', 'additional_js', 'additional_css', 'offline_payment_details']);

            //Checkboxes
            $checkboxes = ['enable_business_based_username', 'superadmin_enable_register_tc', 'allow_email_settings_to_businesses', 'enable_new_business_registration_notification', 'enable_new_subscription_notification', 'enable_welcome_email', 'enable_offline_payment'];
            $input = $request->input();
            foreach ($checkboxes as $checkbox) {
                $system_settings[$checkbox] = ! empty($input[$checkbox]) ? 1 : 0;
            }

            foreach ($system_settings as $key => $setting) {
                System::updateOrCreate(
                    ['key' => $key],
                    ['value' => $setting]
                            );
            }

            $env_settings = $request->only(['APP_NAME', 'APP_TITLE',
                'APP_LOCALE', 'MAIL_MAILER', 'MAIL_HOST', 'MAIL_PORT',
                'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_ENCRYPTION',
                'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME', 'STRIPE_PUB_KEY',
                'STRIPE_SECRET_KEY', 'PAYPAL_MODE',
                'PAYPAL_CLIENT_ID', 'PAYPAL_APP_SECRET',
                'BACKUP_DISK', 'DROPBOX_ACCESS_TOKEN',
                'RAZORPAY_KEY_ID', 'RAZORPAY_KEY_SECRET',
                'PESAPAL_CONSUMER_KEY', 'PESAPAL_CONSUMER_SECRET', 'PESAPAL_LIVE',
                'PUSHER_APP_ID', 'PUSHER_APP_KEY', 'PUSHER_APP_SECRET',
                'PUSHER_APP_CLUSTER', 'GOOGLE_MAP_API_KEY', 'PAYSTACK_SECRET_KEY',
                'PAYSTACK_PUBLIC_KEY', 'FLUTTERWAVE_PUBLIC_KEY',
                'FLUTTERWAVE_SECRET_KEY', 'FLUTTERWAVE_ENCRYPTION_KEY', 'MAPBOX_ACCESS_TOKEN', 'MY_FATOORAH_API_KEY', 'MY_FATOORAH_IS_TEST', 'MY_FATOORAH_COUNTRY_ISO',
                'ENABLE_RECAPTCHA', 'GOOGLE_RECAPTCHA_KEY', 'GOOGLE_RECAPTCHA_SECRET', 'DO_NOT_ALLOW_DISPOSABLE_EMAIL',
                'DPO_ENABLED', 'DPO_MODE', 'DPO_COMPANY_TOKEN', 'DPO_SERVICE_TYPE', 'DPO_SERVICE_DESCRIPTION',
                'GOOGLE_SOCIAL_LOGIN_ENABLED', 'GOOGLE_OAUTH_CLIENT_ID', 'GOOGLE_OAUTH_CLIENT_SECRET', 'GOOGLE_OAUTH_REDIRECT_URI',
            ]);

            $env_settings['ALLOW_REGISTRATION'] = ! empty($request->input('ALLOW_REGISTRATION')) ? 'true' : 'false';
            $env_settings['ENABLE_RECAPTCHA'] = ! empty($request->input('ENABLE_RECAPTCHA')) ? 'true' : 'false';
            $env_settings['DO_NOT_ALLOW_DISPOSABLE_EMAIL'] = ! empty($request->input('DO_NOT_ALLOW_DISPOSABLE_EMAIL')) ? 'true' : 'false';
            $env_settings['DPO_ENABLED'] = $dpoEnabled ? 'true' : 'false';
            $env_settings['GOOGLE_SOCIAL_LOGIN_ENABLED'] = $googleEnabled ? 'true' : 'false';
            $env_settings['BROADCAST_DRIVER'] = 'pusher';

            // A blank secret means "keep the existing password". Clearing a
            // production credential requires the explicit checkbox.
            if (! $request->filled('MAIL_PASSWORD') && ! $request->boolean('CLEAR_MAIL_PASSWORD')) {
                unset($env_settings['MAIL_PASSWORD']);
            } elseif ($request->boolean('CLEAR_MAIL_PASSWORD')) {
                $env_settings['MAIL_PASSWORD'] = '';
            }

            if (! $request->filled('DPO_COMPANY_TOKEN') && ! $request->boolean('CLEAR_DPO_COMPANY_TOKEN')) {
                unset($env_settings['DPO_COMPANY_TOKEN']);
            } elseif ($request->boolean('CLEAR_DPO_COMPANY_TOKEN')) {
                $env_settings['DPO_COMPANY_TOKEN'] = '';
                $env_settings['DPO_ENABLED'] = 'false';
            }

            if (! $request->filled('GOOGLE_OAUTH_CLIENT_SECRET') && ! $request->boolean('CLEAR_GOOGLE_OAUTH_CLIENT_SECRET')) {
                unset($env_settings['GOOGLE_OAUTH_CLIENT_SECRET']);
            } elseif ($request->boolean('CLEAR_GOOGLE_OAUTH_CLIENT_SECRET')) {
                $env_settings['GOOGLE_OAUTH_CLIENT_SECRET'] = '';
                $env_settings['GOOGLE_SOCIAL_LOGIN_ENABLED'] = 'false';
            }

            $found_envs = [];
            $env_path = base_path('.env');
            $env_lines = file($env_path);
            foreach ($env_settings as $index => $value) {
                foreach ($env_lines as $key => $line) {
                    //Check if present then replace it.
                    if (preg_match('/^\s*'.preg_quote($index, '/').'\s*=/', $line)) {
                        $env_lines[$key] = $this->formatEnvironmentLine($index, $value);

                        $found_envs[] = $index;
                    }
                }
            }

            //Add the missing env settings
            $missing_envs = array_diff(array_keys($env_settings), $found_envs);
            if (! empty($missing_envs)) {
                $missing_envs = array_values($missing_envs);
                foreach ($missing_envs as $k => $key) {
                    if ($k == 0) {
                        $env_lines[] = PHP_EOL.$this->formatEnvironmentLine($key, $env_settings[$key]);
                    } else {
                        $env_lines[] = $this->formatEnvironmentLine($key, $env_settings[$key]);
                    }
                }
            }

            $env_content = implode('', $env_lines);

            if (is_writable($env_path) && file_put_contents($env_path, $env_content, LOCK_EX)) {
                Artisan::call('config:clear');
                $output = ['success' => 1,
                    'msg' => __('lang_v1.success'),
                ];
            } else {
                $output = ['success' => 0, 'msg' => 'Some setting could not be saved, make sure .env file has 644 permission & owned by www-data user'];
            }
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => $e instanceof ValidationException
                    ? collect($e->errors())->flatten()->first()
                    : __('messages.something_went_wrong'),
            ];
        }

        return redirect()
            ->action([\Modules\Superadmin\Http\Controllers\SuperadminSettingsController::class, 'edit'])
            ->with('status', $output);
    }

    private function formatEnvironmentLine(string $key, $value): string
    {
        $escaped = str_replace(
            ["\\", '"', "\r", "\n"],
            ["\\\\", '\\"', '', ''],
            (string) $value
        );

        return $key.'="'.$escaped.'"'.PHP_EOL;
    }
}
