<?php

namespace App\Notifications;

use App\NotificationEvent;
use App\Services\BusinessMailConfigurationService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OperationalAlertNotification extends Notification
{
    use Queueable;

    public function __construct(private NotificationEvent $event, private array $channels = ['database'])
    {
    }

    public function via($notifiable): array
    {
        return $this->channels;
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->event->title,
            'msg' => $this->event->message,
            'icon_class' => $this->iconClass(),
            'link' => $this->event->action_url ?: route('home'),
            'event' => $this->event->event_key,
            'event_uuid' => $this->event->uuid,
            'business_id' => $this->event->business_id,
            'category' => $this->event->category,
            'severity' => $this->event->severity,
            'due_at' => optional($this->event->due_at)->toIso8601String(),
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $business = $this->event->business_id ? $this->event->business()->first() : null;
        if ($business) {
            app(BusinessMailConfigurationService::class)
                ->applyFromSettings($business->email_settings ?? []);
        }

        $message = (new MailMessage())
            ->subject($this->event->title)
            ->greeting('Hello '.($notifiable->first_name ?: 'there').',')
            ->line($this->event->message);

        if ($this->event->action_url) {
            $message->action('Review in CashERP', $this->event->action_url);
        }

        return $message->line('This message was generated for the company and role shown after you sign in.');
    }

    private function iconClass(): string
    {
        return match ($this->event->severity) {
            'critical' => 'fas fa-exclamation-circle bg-red',
            'warning' => 'fas fa-exclamation-triangle bg-yellow',
            'success' => 'fas fa-check-circle bg-green',
            default => 'fas fa-info-circle bg-blue',
        };
    }
}
