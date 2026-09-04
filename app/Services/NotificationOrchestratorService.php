<?php

namespace App\Services;

use App\Business;
use App\BusinessNotificationSetting;
use App\NotificationDelivery;
use App\NotificationEvent;
use App\Notifications\OperationalAlertNotification;
use App\User;
use App\UserNotificationPreference;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class NotificationOrchestratorService
{
    /**
     * Record and deliver one operational event. Repeating the same fingerprint
     * is safe: an event and each recipient/channel delivery are created once.
     *
     * @param  array{business_id?:int|null,subject_type?:string|null,subject_id?:string|int|null,title:string,message:string,action_url?:string|null,due_at?:mixed,metadata?:array,bucket?:string}  $payload
     */
    public function dispatch(string $eventKey, array $payload): ?NotificationEvent
    {
        if (! Schema::hasTable('notification_events') || ! Schema::hasTable('notification_deliveries')) {
            return null;
        }

        $definition = config('notification_events.events.'.$eventKey);
        if (! is_array($definition)) {
            throw new \InvalidArgumentException('Unknown CashERP notification event: '.$eventKey);
        }

        $businessId = isset($payload['business_id']) ? (int) $payload['business_id'] : null;
        if ($businessId && ! $this->eventAppliesToBusiness($definition['category'], $businessId)) {
            return null;
        }

        $subjectType = (string) ($payload['subject_type'] ?? 'system');
        $subjectId = (string) ($payload['subject_id'] ?? 'global');
        $bucket = (string) ($payload['bucket'] ?? now()->toDateString());
        $fingerprint = hash('sha256', implode('|', [$eventKey, $businessId ?: 'platform', $subjectType, $subjectId, $bucket]));

        $event = NotificationEvent::firstOrCreate(
            ['fingerprint' => $fingerprint],
            [
                'uuid' => (string) Str::uuid(),
                'business_id' => $businessId,
                'event_key' => $eventKey,
                'category' => $definition['category'],
                'severity' => $payload['severity'] ?? $definition['severity'] ?? 'info',
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'title' => Str::limit(strip_tags((string) $payload['title']), 255, ''),
                'message' => Str::limit(strip_tags((string) $payload['message']), 4000, ''),
                'action_url' => isset($payload['action_url']) ? Str::limit((string) $payload['action_url'], 1000, '') : null,
                'due_at' => $payload['due_at'] ?? null,
                'metadata' => $payload['metadata'] ?? [],
            ]
        );

        if ($event->resolved_at) {
            return $event;
        }

        $businessSetting = $this->businessSetting($businessId, $eventKey, $definition);
        if (! $businessSetting['is_enabled']) {
            return $event;
        }

        foreach ($this->recipients($event, $definition, $payload) as $recipient) {
            $this->deliverToUser($event, $recipient, $businessSetting, $definition);
        }

        return $event;
    }

    public function resolve(string $eventKey, ?int $businessId, string $subjectType, string|int $subjectId): int
    {
        if (! Schema::hasTable('notification_events')) {
            return 0;
        }

        return NotificationEvent::query()
            ->where('event_key', $eventKey)
            ->where('business_id', $businessId)
            ->where('subject_type', $subjectType)
            ->where('subject_id', (string) $subjectId)
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now(), 'updated_at' => now()]);
    }

    /** @return array<string, mixed> */
    public function catalogForBusiness(Business $business): array
    {
        $categories = config('notification_events.industry_categories.'.optional($business->industry)->code, []);
        $events = collect(config('notification_events.events', []))
            ->filter(fn ($definition) => in_array($definition['category'], $categories, true))
            ->map(function ($definition, $key) use ($business) {
                $setting = $this->businessSetting((int) $business->id, $key, $definition);

                return [
                    'key' => $key,
                    'label' => Str::headline(str_replace('.', ' ', $key)),
                    'category' => $definition['category'],
                    'severity' => $definition['severity'] ?? 'info',
                    'is_enabled' => $setting['is_enabled'],
                    'database_enabled' => $setting['database_enabled'],
                    'email_enabled' => $setting['email_enabled'],
                    'lead_days' => $setting['lead_days'],
                    'overdue_after_hours' => $setting['overdue_after_hours'],
                    'mandatory' => $this->isMandatory($definition),
                ];
            })
            ->values()
            ->all();

        return ['categories' => array_values($categories), 'events' => $events];
    }

    /** @return array<string, mixed> */
    private function businessSetting(?int $businessId, string $eventKey, array $definition): array
    {
        $defaults = config('notification_events.defaults', []);
        $setting = $businessId && Schema::hasTable('business_notification_settings')
            ? BusinessNotificationSetting::where('business_id', $businessId)->where('event_key', $eventKey)->first()
            : null;

        return [
            'is_enabled' => $this->isMandatory($definition) ? true : (bool) ($setting?->is_enabled ?? true),
            'database_enabled' => $this->isMandatory($definition) ? true : (bool) ($setting?->database_enabled ?? ($defaults['database_enabled'] ?? true)),
            'email_enabled' => (bool) ($setting?->email_enabled ?? ($definition['email'] ?? $defaults['email_enabled'] ?? false)),
            'lead_days' => (int) ($setting?->lead_days ?? $definition['lead_days'] ?? $defaults['lead_days'] ?? 7),
            'overdue_after_hours' => (int) ($setting?->overdue_after_hours ?? $definition['overdue_after_hours'] ?? $defaults['overdue_after_hours'] ?? 24),
        ];
    }

    private function eventAppliesToBusiness(string $category, int $businessId): bool
    {
        $business = Business::with('industry')->find($businessId);
        if (! $business) {
            return false;
        }

        $industry = optional($business->industry)->code ?: 'general_business';

        return in_array($category, config('notification_events.industry_categories.'.$industry, []), true);
    }

    /** @return Collection<int, User> */
    private function recipients(NotificationEvent $event, array $definition, array $payload): Collection
    {
        if (! $event->business_id) {
            return collect();
        }

        $business = Business::find($event->business_id);
        if (! $business) {
            return collect();
        }

        $requestedIds = collect(data_get($payload, 'metadata.recipient_user_ids', []))
            ->map(fn ($id) => (int) $id)->filter()->unique();
        $users = User::forBusiness((int) $business->id)
            ->where('allow_login', 1)
            ->whereNull('deleted_at')
            ->get()
            ->filter(fn (User $user) => $user->canAccessBusiness((int) $business->id));

        if ($requestedIds->isNotEmpty()) {
            $users = $users->whereIn('id', $requestedIds);
        } else {
            $permissions = collect($definition['permissions'] ?? [])->filter();
            $users = $users->filter(function (User $user) use ($business, $permissions) {
                if ((int) $business->owner_id === (int) $user->id || $user->can('superadmin')) {
                    return true;
                }

                return $permissions->contains(fn ($permission) => $user->canForBusiness($permission, (int) $business->id));
            });
        }

        if ($business->owner && ! $users->contains('id', $business->owner->id)) {
            $users->push($business->owner);
        }

        return $users->unique('id')->values();
    }

    private function deliverToUser(NotificationEvent $event, User $user, array $businessSetting, array $definition): void
    {
        $preference = Schema::hasTable('user_notification_preferences')
            ? UserNotificationPreference::where('user_id', $user->id)
                ->where('business_id', $event->business_id)
                ->whereIn('event_key', [$event->event_key, '*'])
                ->orderByRaw("CASE WHEN event_key = ? THEN 0 ELSE 1 END", [$event->event_key])
                ->first()
            : null;

        $mandatory = $this->isMandatory($definition) || $event->severity === 'critical';
        $database = $mandatory || ($businessSetting['database_enabled'] && ($preference?->database_enabled ?? true));
        $email = $businessSetting['email_enabled'] && ($preference?->email_enabled ?? true) && ! empty($user->email);

        if ($database) {
            $this->deliver($event, $user, 'database');
        }
        if ($email) {
            $this->deliver($event, $user, 'mail');
        }
    }

    private function deliver(NotificationEvent $event, User $user, string $channel): void
    {
        try {
            $delivery = NotificationDelivery::firstOrCreate(
                ['notification_event_id' => $event->id, 'user_id' => $user->id, 'channel' => $channel],
                ['recipient_email' => $channel === 'mail' ? $user->email : null, 'status' => 'pending']
            );
        } catch (QueryException) {
            return;
        }

        if ($delivery->status === 'sent'
            || ($delivery->status === 'failed' && (int) $delivery->attempts >= 5)
            || ($delivery->next_retry_at && $delivery->next_retry_at->isFuture())) {
            return;
        }

        $delivery->increment('attempts');
        $delivery->refresh();
        try {
            $user->notify(new OperationalAlertNotification($event, [$channel]));
            $delivery->forceFill(['status' => 'sent', 'sent_at' => now(), 'failed_at' => null, 'next_retry_at' => null, 'last_error' => null])->save();
        } catch (\Throwable $exception) {
            $backoffMinutes = min(720, 15 * (2 ** max(0, (int) $delivery->attempts - 1)));
            $delivery->forceFill([
                'status' => 'failed',
                'failed_at' => now(),
                'next_retry_at' => (int) $delivery->attempts < 5 ? now()->addMinutes($backoffMinutes) : null,
                'last_error' => Str::limit($exception->getMessage(), 2000, ''),
            ])->save();
            Log::error('CashERP notification delivery failed.', [
                'event_uuid' => $event->uuid,
                'user_id' => $user->id,
                'channel' => $channel,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private function isMandatory(array $definition): bool
    {
        return (bool) ($definition['mandatory'] ?? false)
            || ($definition['severity'] ?? 'info') === 'critical';
    }
}
