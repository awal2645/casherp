<?php

namespace App\Http\Controllers;

use App\Business;
use App\Http\Requests\BusinessSmtpSettingsRequest;
use App\Notifications\SmtpConfigurationNotification;
use App\Services\BusinessMailConfigurationService;
use App\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class BusinessSmtpSettingsController extends Controller
{
    public function show(BusinessSmtpSettingsRequest $request, BusinessMailConfigurationService $mailConfiguration)
    {
        return response()->json([
            'success' => true,
            'data' => $mailConfiguration->publicPayload($this->business($request)),
        ]);
    }

    public function update(BusinessSmtpSettingsRequest $request, BusinessMailConfigurationService $mailConfiguration)
    {
        $business = $this->business($request);
        $settings = $mailConfiguration->store($business, $request->validated());
        $correlationId = (string) Str::uuid();

        $mailConfiguration->recordEvent($business, [
            'user_id' => $request->user()->id,
            'correlation_id' => $correlationId,
            'action' => 'configuration_saved',
            'status' => 'success',
            'provider' => $settings['provider'] ?? 'custom',
            'metadata' => [
                'using_system_settings' => (bool) ($settings['use_system_settings'] ?? false),
                'encryption' => $settings['encryption'] ?? null,
                'port' => $settings['port'] ?? null,
            ],
        ]);
        $this->notifyStakeholders($business, $request->user()->id, 'configuration_saved', $correlationId);

        return response()->json([
            'success' => true,
            'message' => 'Email delivery settings were saved securely.',
            'correlation_id' => $correlationId,
            'data' => $mailConfiguration->publicPayload($business->fresh()),
        ]);
    }

    public function test(BusinessSmtpSettingsRequest $request, BusinessMailConfigurationService $mailConfiguration)
    {
        $business = $this->business($request);
        $input = $request->validated();
        $recipient = $input['test_recipient'];
        $candidate = $mailConfiguration->prepareForPersistence($input, $business->email_settings ?? []);
        $correlationId = (string) Str::uuid();
        $started = microtime(true);

        try {
            $mailConfiguration->withSettings($candidate, function () use ($business, $recipient, $correlationId) {
                Mail::mailer('smtp')->raw(
                    "CashERP successfully connected to your SMTP service.\n\nCompany: {$business->name}\nReference: {$correlationId}",
                    function ($message) use ($business, $recipient) {
                        $message->to($recipient)
                            ->subject('CashERP email delivery test - '.$business->name);
                    }
                );
            });

            $duration = (int) round((microtime(true) - $started) * 1000);
            $mailConfiguration->recordEvent($business, [
                'user_id' => $request->user()->id,
                'correlation_id' => $correlationId,
                'action' => 'test_email',
                'status' => 'success',
                'provider' => $candidate['provider'] ?? 'custom',
                'recipient' => $recipient,
                'duration_ms' => $duration,
                'metadata' => ['using_saved_configuration' => false],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Test email accepted by the SMTP service. Check the recipient inbox and spam folder.',
                'correlation_id' => $correlationId,
                'duration_ms' => $duration,
            ]);
        } catch (\Throwable $exception) {
            $duration = (int) round((microtime(true) - $started) * 1000);
            $category = $mailConfiguration->classifyFailure($exception);

            $mailConfiguration->recordEvent($business, [
                'user_id' => $request->user()->id,
                'correlation_id' => $correlationId,
                'action' => 'test_email',
                'status' => 'failure',
                'provider' => $candidate['provider'] ?? 'custom',
                'recipient' => $recipient,
                'failure_category' => $category,
                'duration_ms' => $duration,
                'metadata' => ['exception_class' => get_class($exception)],
            ]);

            Log::warning('Company SMTP test failed.', [
                'business_id' => $business->id,
                'user_id' => $request->user()->id,
                'correlation_id' => $correlationId,
                'category' => $category,
                'exception' => $exception,
            ]);

            $notificationKey = 'smtp-test-failure-notification:'.$business->id.':'.$category;
            if (Cache::add($notificationKey, true, now()->addMinutes(15))) {
                $this->notifyStakeholders(
                    $business,
                    $request->user()->id,
                    'test_failed',
                    $correlationId,
                    $category
                );
            }

            return response()->json([
                'success' => false,
                'message' => $mailConfiguration->safeFailureMessage($category, $correlationId),
                'failure_category' => $category,
                'correlation_id' => $correlationId,
                'duration_ms' => $duration,
            ], 422);
        }
    }

    private function business(BusinessSmtpSettingsRequest $request): Business
    {
        $businessId = (int) $request->session()->get('user.business_id');

        return $request->user()->accessibleBusinesses()
            ->whereKey($businessId)
            ->firstOrFail();
    }

    private function notifyStakeholders(
        Business $business,
        int $actorId,
        string $event,
        string $correlationId,
        ?string $failureCategory = null
    ): void {
        try {
            $recipients = User::whereIn('id', array_unique([$business->owner_id, $actorId]))->get();
            \Notification::send(
                $recipients,
                new SmtpConfigurationNotification($business, $event, $correlationId, $failureCategory)
            );
        } catch (\Throwable $exception) {
            // A secondary in-app alert must never make a valid settings save
            // or SMTP diagnosis appear to have failed.
            Log::notice('SMTP security notification could not be recorded.', [
                'business_id' => $business->id,
                'event' => $event,
                'correlation_id' => $correlationId,
                'exception' => $exception,
            ]);
        }
    }
}
