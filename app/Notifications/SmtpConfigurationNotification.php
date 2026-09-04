<?php

namespace App\Notifications;

use App\Business;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SmtpConfigurationNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Business $business,
        protected string $event,
        protected string $correlationId,
        protected ?string $failureCategory = null
    ) {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $failed = $this->event === 'test_failed';

        return [
            'title' => $failed ? 'Email delivery test failed' : 'Email delivery settings changed',
            'msg' => $failed
                ? 'An email delivery test for '.$this->business->name.' needs attention.'
                : 'Email delivery settings for '.$this->business->name.' were changed securely.',
            'icon_class' => $failed ? 'fas fa-envelope-open-text bg-yellow' : 'fas fa-shield-alt bg-blue',
            'link' => route('business.getBusinessSettings'),
            'event' => $failed ? 'smtp.configuration_failed' : 'smtp.configuration_changed',
            'business_id' => $this->business->id,
            'category' => 'system',
            'severity' => $failed ? 'critical' : 'info',
            'failure_category' => $this->failureCategory,
            'correlation_id' => $this->correlationId,
        ];
    }
}
