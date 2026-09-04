<?php

namespace App\Notifications;

use App\BusinessModuleOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PremiumModuleOrderNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected BusinessModuleOrder $order,
        protected string $event
    ) {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $plan = optional($this->order->plan)->name ?: 'Premium module';
        $messages = [
            'submitted' => $plan.' upgrade request was submitted for review.',
            'cancelled' => $plan.' upgrade request was cancelled.',
            'approved' => $plan.' upgrade was approved and is now available.',
            'declined' => $plan.' upgrade request was declined. Review the administrator note for details.',
        ];
        $icons = [
            'submitted' => 'fas fa-clock bg-blue',
            'cancelled' => 'fas fa-ban bg-gray',
            'approved' => 'fas fa-check-circle bg-green',
            'declined' => 'fas fa-times-circle bg-red',
        ];

        return [
            'msg' => $messages[$this->event] ?? $plan.' upgrade status changed.',
            'icon_class' => $icons[$this->event] ?? 'fas fa-star bg-blue',
            'link' => route('premium-modules.index'),
            'event' => $this->event,
            'business_id' => $this->order->business_id,
            'business_module_order_id' => $this->order->id,
            'status' => $this->order->status,
        ];
    }
}
