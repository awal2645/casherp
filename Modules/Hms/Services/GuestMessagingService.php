<?php

namespace Modules\Hms\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Hms\Entities\HmsGuestMessageLog;
use Modules\Hms\Entities\HmsGuestMessageRule;
use Modules\Hms\Entities\HmsTransactionClass;
use Modules\Hms\Notifications\CustomerNotification;

class GuestMessagingService
{
    public function dispatchDue(): array
    {
        $sent = 0;
        $failed = 0;

        HmsGuestMessageRule::where('is_active', true)->chunkById(100, function ($rules) use (&$sent, &$failed) {
            foreach ($rules as $rule) {
                if (! app(HmsSaasService::class)->allows((int) $rule->business_id, 'hms_guest_experience')) {
                    continue;
                }
                HmsTransactionClass::where('business_id', $rule->business_id)->where('type', 'hms_booking')
                    ->when($rule->hms_property_id, fn ($query) => $query->where('hms_property_id', $rule->hms_property_id))
                    ->whereIn('hms_booking_status', ['reserved', 'checked_in', 'checked_out'])
                    ->with(['contact', 'hms_guest_profile', 'hms_property'])->chunkById(100, function ($bookings) use ($rule, &$sent, &$failed) {
                        foreach ($bookings as $booking) {
                            $scheduledFor = $this->scheduledAt($booking, $rule);
                            if (! $this->isDue($booking, $rule, $scheduledFor) || ! $this->canContact($booking, $rule)) {
                                continue;
                            }
                            $key = 'guest-message:' . $rule->id . ':' . $booking->id;
                            $log = HmsGuestMessageLog::firstOrCreate([
                                'business_id' => $rule->business_id,
                                'idempotency_key' => $key,
                            ], [
                                'hms_guest_message_rule_id' => $rule->id,
                                'transaction_id' => $booking->id,
                                'contact_id' => $booking->contact_id,
                                'channel' => $rule->channel,
                                'status' => 'queued',
                                'scheduled_for' => $scheduledFor,
                            ]);
                            $claimed = HmsGuestMessageLog::whereKey($log->id)
                                ->where('attempts', '<', 3)
                                ->where(function ($query) {
                                    $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now());
                                })
                                ->where(function ($query) {
                                    $query->whereIn('status', ['queued', 'failed'])
                                        ->orWhere(function ($stale) {
                                            $stale->where('status', 'processing')
                                                ->where('last_attempt_at', '<=', now()->subMinutes(15));
                                        });
                                })
                                ->update([
                                    'status' => 'processing',
                                    'attempts' => DB::raw('attempts + 1'),
                                    'last_attempt_at' => now(),
                                    'next_attempt_at' => null,
                                    'failure_reason' => null,
                                    'updated_at' => now(),
                                ]);
                            if ($claimed !== 1) {
                                continue;
                            }
                            $log->refresh();
                            try {
                                $content = $this->replaceTags($rule->body, $booking);
                                Notification::route('mail', $booking->contact->email)->notify(new CustomerNotification([
                                    'business_id' => $rule->business_id,
                                    'subject' => $this->replaceTags($rule->subject ?: $rule->name, $booking),
                                    'email_body' => $content,
                                ]));
                                $log->update(['status' => 'sent', 'sent_at' => now(), 'next_attempt_at' => null]);
                                $sent++;
                            } catch (\Throwable $e) {
                                report($e);
                                $delayMinutes = min(60, 5 * (2 ** max(0, (int) $log->attempts - 1)));
                                $log->update([
                                    'status' => 'failed',
                                    'failure_reason' => mb_substr($e->getMessage(), 0, 2000),
                                    'next_attempt_at' => now()->addMinutes($delayMinutes),
                                ]);
                                $failed++;
                            }
                        }
                    });
            }
        });

        return compact('sent', 'failed');
    }

    private function scheduledAt(HmsTransactionClass $booking, HmsGuestMessageRule $rule): Carbon
    {
        $anchor = in_array($rule->event_type, ['pre_arrival', 'arrival_day', 'in_stay'], true)
            ? Carbon::parse($booking->hms_booking_arrival_date_time)
            : Carbon::parse($booking->hms_booking_departure_date_time);

        return $anchor->copy()->addMinutes((int) $rule->timing_offset_minutes);
    }

    private function isDue(HmsTransactionClass $booking, HmsGuestMessageRule $rule, Carbon $scheduled): bool
    {
        $now = now();
        if ($scheduled->gt($now->copy()->addMinutes(10)) || $scheduled->lt($now->copy()->subDay())) {
            return false;
        }

        if (in_array($rule->event_type, ['pre_arrival', 'arrival_day', 'pre_departure'], true)
            && $now->gte(Carbon::parse($booking->hms_booking_departure_date_time))) {
            return false;
        }

        return true;
    }

    private function canContact(HmsTransactionClass $booking, HmsGuestMessageRule $rule): bool
    {
        if (! $booking->contact || empty($booking->contact->email)) {
            return false;
        }
        $profile = $booking->hms_guest_profile;
        if ($profile && ($profile->do_not_contact || ($rule->requires_marketing_consent && ! $profile->marketing_consent))) {
            return false;
        }

        return true;
    }

    private function replaceTags(string $text, HmsTransactionClass $booking): string
    {
        return strtr($text, [
            '{guest_name}' => optional($booking->contact)->name ?: '',
            '{booking_reference}' => $booking->ref_no ?: '',
            '{arrival}' => Carbon::parse($booking->hms_booking_arrival_date_time)->toDayDateTimeString(),
            '{departure}' => Carbon::parse($booking->hms_booking_departure_date_time)->toDayDateTimeString(),
            '{property_name}' => optional($booking->hms_property)->name ?: '',
        ]);
    }
}
