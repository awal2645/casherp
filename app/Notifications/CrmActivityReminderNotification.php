<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\Crm\Entities\Activity;

class CrmActivityReminderNotification extends Notification
{
    use Queueable;

    public function __construct(private Activity $activity)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'msg' => 'CRM reminder: '.$this->activity->subject,
            'icon_class' => 'fas fa-bell bg-orange',
            'link' => route('crm.workspace', ['opportunity' => optional($this->activity->opportunity)->uuid]),
            'business_id' => $this->activity->business_id,
            'crm_activity_uuid' => $this->activity->uuid,
        ];
    }
}
