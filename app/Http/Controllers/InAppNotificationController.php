<?php

namespace App\Http\Controllers;

use App\Business;
use App\BusinessNotificationSetting;
use App\NotificationDelivery;
use App\NotificationEvent;
use App\Services\NotificationOrchestratorService;
use App\UserNotificationPreference;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class InAppNotificationController extends Controller
{
    public function index(Request $request, Util $util)
    {
        $data = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'unread_only' => ['nullable', 'boolean'],
            'category' => ['nullable', 'string', 'max:40'],
            'severity' => ['nullable', Rule::in(['info', 'success', 'warning', 'critical'])],
        ]);
        $query = $this->query($request)->orderByDesc('created_at');
        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }
        if (! empty($data['category'])) {
            $query->where('data->category', $data['category']);
        }
        if (! empty($data['severity'])) {
            $query->where('data->severity', $data['severity']);
        }

        $notifications = $query->paginate((int) ($data['per_page'] ?? 20));
        $items = $notifications->getCollection()->map(function ($notification) use ($util) {
            $parsed = $util->parseNotifications(collect([$notification]));
            $display = $parsed[0] ?? [];
            $payload = is_array($notification->data) ? $notification->data : [];

            return [
                'id' => $notification->id,
                'type' => class_basename($notification->type),
                'title' => $payload['title'] ?? $payload['event'] ?? 'CashERP update',
                'message' => $display['msg'] ?? ($payload['msg'] ?? null),
                'icon_class' => $display['icon_class'] ?? ($payload['icon_class'] ?? 'fas fa-bell bg-blue'),
                'link' => $display['link'] ?? ($payload['link'] ?? '#'),
                'event' => $payload['event'] ?? null,
                'category' => $payload['category'] ?? 'general',
                'severity' => $payload['severity'] ?? 'info',
                'due_at' => $payload['due_at'] ?? null,
                'business_id' => $payload['business_id'] ?? null,
                'read' => $notification->read_at !== null,
                'read_at' => optional($notification->read_at)->toIso8601String(),
                'created_at' => $notification->created_at->toIso8601String(),
                'created_at_human' => $notification->created_at->diffForHumans(),
            ];
        })->values();

        $scoped = $this->query($request);

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'unread' => (clone $scoped)->whereNull('read_at')->count(),
                'critical_unread' => (clone $scoped)->whereNull('read_at')->where('data->severity', 'critical')->count(),
            ],
        ]);
    }

    public function preferences(Request $request, NotificationOrchestratorService $notifications)
    {
        $business = $this->activeBusiness($request);
        $preference = Schema::hasTable('user_notification_preferences')
            ? UserNotificationPreference::firstOrNew([
                'user_id' => $request->user()->id,
                'business_id' => $business->id,
                'event_key' => '*',
            ])
            : null;

        return response()->json([
            'data' => [
                'database_enabled' => (bool) ($preference?->database_enabled ?? true),
                'email_enabled' => (bool) ($preference?->email_enabled ?? true),
                'digest' => $preference?->digest ?? 'immediate',
                'timezone' => $preference?->timezone ?? config('app.timezone'),
                'critical_in_app_locked' => true,
                'catalog' => $notifications->catalogForBusiness($business),
                'can_manage_company_policy' => $this->canManagePolicy($request, $business),
            ],
        ]);
    }

    public function updatePreferences(Request $request)
    {
        $this->requireGovernanceTables();
        $business = $this->activeBusiness($request);
        $data = $request->validate([
            'database_enabled' => ['required', 'boolean'],
            'email_enabled' => ['required', 'boolean'],
            'digest' => ['nullable', Rule::in(['immediate'])],
            'timezone' => ['nullable', 'timezone'],
        ]);

        $preference = UserNotificationPreference::updateOrCreate([
            'user_id' => $request->user()->id,
            'business_id' => $business->id,
            'event_key' => '*',
        ], [
            'database_enabled' => (bool) $data['database_enabled'],
            'email_enabled' => (bool) $data['email_enabled'],
            'digest' => $data['digest'] ?? 'immediate',
            'timezone' => $data['timezone'] ?? config('app.timezone'),
        ]);

        return response()->json([
            'message' => 'Your notification preferences were saved for this company.',
            'data' => $preference,
        ]);
    }

    public function settings(Request $request, NotificationOrchestratorService $notifications)
    {
        $this->requireGovernanceTables();
        $business = $this->activeBusiness($request);
        abort_unless($this->canManagePolicy($request, $business), 403);

        return response()->json([
            'data' => [
                'catalog' => $notifications->catalogForBusiness($business),
                'health' => [
                    'open_events' => NotificationEvent::where('business_id', $business->id)->whereNull('resolved_at')->count(),
                    'critical_open' => NotificationEvent::where('business_id', $business->id)->whereNull('resolved_at')->where('severity', 'critical')->count(),
                    'failed_deliveries' => NotificationDelivery::whereHas('event', fn ($query) => $query->where('business_id', $business->id))->where('status', 'failed')->count(),
                ],
            ],
        ]);
    }

    public function updateSettings(Request $request, NotificationOrchestratorService $notifications)
    {
        $this->requireGovernanceTables();
        $business = $this->activeBusiness($request);
        abort_unless($this->canManagePolicy($request, $business), 403);
        $catalog = collect($notifications->catalogForBusiness($business)['events'])->keyBy('key');
        $data = $request->validate([
            'events' => ['required', 'array', 'max:100'],
            'events.*.key' => ['required', 'string', Rule::in($catalog->keys()->all())],
            'events.*.is_enabled' => ['required', 'boolean'],
            'events.*.database_enabled' => ['required', 'boolean'],
            'events.*.email_enabled' => ['required', 'boolean'],
            'events.*.lead_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'events.*.overdue_after_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
        ]);

        foreach ($data['events'] as $row) {
            $definition = $catalog->get($row['key']);
            $mandatory = (bool) ($definition['mandatory'] ?? false);
            BusinessNotificationSetting::updateOrCreate([
                'business_id' => $business->id,
                'event_key' => $row['key'],
            ], [
                'is_enabled' => $mandatory ? true : (bool) $row['is_enabled'],
                'database_enabled' => $mandatory ? true : (bool) $row['database_enabled'],
                'email_enabled' => (bool) $row['email_enabled'],
                'lead_days' => $row['lead_days'] ?? null,
                'overdue_after_hours' => $row['overdue_after_hours'] ?? null,
                'updated_by' => $request->user()->id,
            ]);
        }

        return response()->json([
            'message' => 'Company notification policy was saved.',
            'data' => $notifications->catalogForBusiness($business),
        ]);
    }

    public function markRead(Request $request, string $notification)
    {
        $record = $this->query($request)->findOrFail($notification);
        if ($record->read_at === null) {
            $record->markAsRead();
        }

        return response()->json([
            'data' => [
                'id' => $record->id,
                'read' => true,
                'read_at' => optional($record->fresh()->read_at)->toIso8601String(),
            ],
        ]);
    }

    public function markAllRead(Request $request)
    {
        $updated = $this->query($request)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'data' => ['updated' => $updated],
        ]);
    }

    private function query(Request $request)
    {
        $businessId = (int) $request->session()->get('user.business_id');
        $platformTypes = [
            \App\Notifications\BusinessClosureNotification::class,
            \Modules\Superadmin\Notifications\SuperadminCommunicator::class,
        ];

        return $request->user()->notifications()->where(function ($query) use ($businessId, $platformTypes) {
            if ($businessId > 0) {
                $query->where('data->business_id', $businessId)
                    ->orWhere(function ($platform) use ($platformTypes) {
                        $platform->whereNull('data->business_id')->whereIn('type', $platformTypes);
                    });
                return;
            }

            $query->whereNull('data->business_id')->whereIn('type', $platformTypes);
        });
    }

    private function activeBusiness(Request $request): Business
    {
        $businessId = (int) $request->session()->get('user.business_id');
        abort_if($businessId < 1 || ! $request->user()->canAccessBusiness($businessId), 403);

        return Business::with('industry')->findOrFail($businessId);
    }

    private function canManagePolicy(Request $request, Business $business): bool
    {
        return (int) $business->owner_id === (int) $request->user()->id
            || $request->user()->can('superadmin')
            || $request->user()->canForBusiness('business_settings.access', (int) $business->id);
    }

    private function requireGovernanceTables(): void
    {
        abort_unless(
            Schema::hasTable('notification_events')
                && Schema::hasTable('notification_deliveries')
                && Schema::hasTable('business_notification_settings')
                && Schema::hasTable('user_notification_preferences'),
            503,
            'Notification governance is waiting for the database migration.'
        );
    }
}
