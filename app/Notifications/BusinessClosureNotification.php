<?php

namespace App\Notifications;

use App\BusinessClosureRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BusinessClosureNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected BusinessClosureRequest $closure,
        protected string $event
    ) {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $businessName = optional($this->closure->business)->name
            ?: ($this->closure->company_snapshot['name'] ?? 'Company');
        $scheduledFor = optional($this->closure->scheduled_for)->format('Y-m-d H:i T');
        $messages = [
            'scheduled' => $businessName.' is scheduled for closure on '.$scheduledFor.'. It can be recovered before that time.',
            'cancelled' => $businessName.' company closure was cancelled and access remains active.',
            'closed' => $businessName.' reached the end of its recovery period and was deactivated.',
        ];

        $payload = [
            'msg' => $messages[$this->event] ?? $businessName.' account status changed.',
            'icon_class' => $this->event === 'cancelled'
                ? 'fas fa-undo bg-green'
                : ($this->event === 'closed' ? 'fas fa-lock bg-red' : 'fas fa-exclamation-triangle bg-yellow'),
            'link' => $this->event === 'closed' ? route('home') : route('business.account-closure.show'),
            'event' => $this->event,
            'business_closure_request_id' => $this->closure->id,
            'status' => $this->closure->status,
        ];

        // A final closure is an account-level notice because the deactivated
        // company can no longer be selected as the active context.
        if ($this->event !== 'closed') {
            $payload['business_id'] = $this->closure->business_id;
        }

        return $payload;
    }
}
